<?php

declare(strict_types=1);

namespace App\Support;

final class ManagedStorageArchiveImporter
{
    private const SNAPSHOT_SAMPLE_LIMIT = 10;

    private string $projectRoot;

    /** @var array<string, mixed> */
    private array $env;

    /**
     * @param array<string, mixed> $env
     */
    public function __construct(string $projectRoot, array $env = [])
    {
        $this->projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $this->env = $env;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(?string $kind = null, ?string $archive = null): array
    {
        $packages = $this->resolvePackageReports($kind, $archive);

        return [
            'status' => 'ok',
            'mode' => 'report',
            'requested_kind' => $kind !== null ? trim($kind) : null,
            'zip_archive_available' => class_exists(\ZipArchive::class),
            'managed_storage_root' => $this->describePath($this->storage()->resolveManagedStorageRoot(), true),
            'packages' => $packages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function import(?string $kind = null, ?string $archive = null, bool $deleteAfter = false): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return [
                'status' => 'error',
                'mode' => 'import',
                'error' => 'A extensão ZipArchive não está disponível neste ambiente PHP.',
            ];
        }

        $packageDefinitions = $this->resolveSelectedPackages($kind);
        if ($packageDefinitions === []) {
            return [
                'status' => 'error',
                'mode' => 'import',
                'error' => 'Kind de importação inválido.',
                'requested_kind' => $kind !== null ? trim($kind) : null,
                'available_kinds' => array_keys($this->packageDefinitions()),
            ];
        }

        $results = [];
        $overallStatus = 'ok';

        foreach ($packageDefinitions as $packageKind => $definition) {
            $results[$packageKind] = $this->importSinglePackage($packageKind, $definition, $archive, $deleteAfter);
            $packageStatus = (string) ($results[$packageKind]['status'] ?? 'error');

            if ($packageStatus === 'error') {
                $overallStatus = 'error';
                continue;
            }

            if ($packageStatus !== 'ok' && $overallStatus === 'ok') {
                $overallStatus = 'partial';
            }
        }

        return [
            'status' => $overallStatus,
            'mode' => 'import',
            'requested_kind' => $kind !== null ? trim($kind) : 'all',
            'delete_after' => $deleteAfter,
            'zip_archive_available' => true,
            'managed_storage_root' => $this->describePath($this->storage()->resolveManagedStorageRoot(), true),
            'results' => $results,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function resolvePackageReports(?string $kind, ?string $archive): array
    {
        $reports = [];

        foreach ($this->resolveSelectedPackages($kind) as $packageKind => $definition) {
            $reports[$packageKind] = $this->buildPackageReport($packageKind, $definition, $archive);
        }

        return $reports;
    }

    /**
     * @param array<string, string> $definition
     * @return array<string, mixed>
     */
    private function buildPackageReport(string $kind, array $definition, ?string $archive): array
    {
        $targetDirectory = $this->storage()->resolveUploadDirectory(
            $definition['directory_env_key'],
            $definition['default_directory']
        );
        $archiveCandidates = $this->resolveArchiveCandidates($definition['archive_name'], $archive);
        $selectedArchive = $this->selectArchiveCandidate($archiveCandidates);

        return [
            'kind' => $kind,
            'archive_name' => $definition['archive_name'] . '.zip',
            'target_directory' => $this->describePath($targetDirectory, true),
            'source_candidates' => array_map(
                fn (string $candidatePath): array => $this->describePath($candidatePath, false),
                $archiveCandidates
            ),
            'selected_archive' => $selectedArchive !== null ? $this->describePath($selectedArchive, false) : null,
        ];
    }

    /**
     * @param array<string, string> $definition
     * @return array<string, mixed>
     */
    private function importSinglePackage(
        string $kind,
        array $definition,
        ?string $archive,
        bool $deleteAfter
    ): array {
        $report = $this->buildPackageReport($kind, $definition, $archive);
        $targetDirectory = (string) ($report['target_directory']['path'] ?? '');
        $selectedArchive = isset($report['selected_archive']['path'])
            ? (string) $report['selected_archive']['path']
            : null;

        if ($selectedArchive === null || $selectedArchive === '') {
            return $report + [
                'status' => 'error',
                'error' => 'Nenhum arquivo .zip foi encontrado para este bucket.',
            ];
        }

        if (!$this->storage()->prepareWritableDirectory($targetDirectory)) {
            return $report + [
                'status' => 'error',
                'error' => 'O diretório de destino não está gravável para o PHP.',
            ];
        }

        $validation = $this->validateArchiveEntries($selectedArchive);
        if (($validation['status'] ?? 'error') !== 'ok') {
            return $report + $validation;
        }

        $extraction = $this->extractArchiveEntries(
            $selectedArchive,
            $targetDirectory,
            (array) ($validation['entries'] ?? [])
        );

        if (($extraction['status'] ?? 'error') !== 'ok') {
            return $report + $extraction;
        }

        $deletedArchive = false;
        if ($deleteAfter && is_file($selectedArchive)) {
            $deletedArchive = @unlink($selectedArchive);
        }

        return $report + [
            'status' => 'ok',
            'validated_files' => count((array) ($validation['entries'] ?? [])),
            'imported_files' => (int) ($extraction['imported_files'] ?? 0),
            'imported_bytes' => (int) ($extraction['imported_bytes'] ?? 0),
            'deleted_archive' => $deletedArchive,
            'post_import_snapshot' => $this->buildPostImportSnapshot(
                $targetDirectory,
                (array) ($validation['entries'] ?? [])
            ),
        ];
    }

    /**
     * @param array<int, array{entry_name: string, file_name: string}> $expectedEntries
     * @return array<string, mixed>
     */
    private function buildPostImportSnapshot(string $targetDirectory, array $expectedEntries): array
    {
        $directoryDescription = $this->describePath($targetDirectory, true);
        $fileCount = 0;
        $totalSizeBytes = 0;
        $sampleEntries = [];

        if (
            ($directoryDescription['exists'] ?? false) === true
            && ($directoryDescription['readable'] ?? false) === true
        ) {
            $directoryEntries = @scandir($targetDirectory);
            if (is_array($directoryEntries)) {
                foreach ($directoryEntries as $entryName) {
                    if ($entryName === '.' || $entryName === '..') {
                        continue;
                    }

                    $entryPath = rtrim($targetDirectory, '/') . '/' . $entryName;
                    if (!is_file($entryPath)) {
                        continue;
                    }

                    $entryDescription = $this->describePath($entryPath, false);
                    $fileCount++;
                    $totalSizeBytes += (int) ($entryDescription['size_bytes'] ?? 0);

                    if (count($sampleEntries) < self::SNAPSHOT_SAMPLE_LIMIT) {
                        $sampleEntries[] = ['file_name' => $entryName] + $entryDescription;
                    }
                }
            }
        }

        $expectedFileCount = 0;
        $visibleExpectedFileCount = 0;
        $expectedFileSample = [];
        $missingExpectedFileSample = [];

        foreach ($expectedEntries as $entry) {
            $fileName = trim($entry['file_name']);
            if ($fileName === '') {
                continue;
            }

            $expectedFileCount++;
            $entryPath = rtrim($targetDirectory, '/') . '/' . $fileName;
            $entryDescription = ['file_name' => $fileName] + $this->describePath($entryPath, false);

            if (($entryDescription['exists'] ?? false) === true) {
                $visibleExpectedFileCount++;
            } elseif (count($missingExpectedFileSample) < self::SNAPSHOT_SAMPLE_LIMIT) {
                $missingExpectedFileSample[] = $fileName;
            }

            if (count($expectedFileSample) < self::SNAPSHOT_SAMPLE_LIMIT) {
                $expectedFileSample[] = $entryDescription;
            }
        }

        return [
            'sample_limit' => self::SNAPSHOT_SAMPLE_LIMIT,
            'directory' => $directoryDescription,
            'file_count' => $fileCount,
            'total_size_bytes' => $totalSizeBytes,
            'sample_entries' => $sampleEntries,
            'expected_file_count' => $expectedFileCount,
            'visible_expected_file_count' => $visibleExpectedFileCount,
            'missing_expected_file_count' => max(0, $expectedFileCount - $visibleExpectedFileCount),
            'expected_file_sample' => $expectedFileSample,
            'missing_expected_file_sample' => $missingExpectedFileSample,
        ];
    }

    /**
     * @param array<int, array{entry_name: string, file_name: string}> $entries
     * @return array<string, mixed>
     */
    private function extractArchiveEntries(string $archivePath, string $targetDirectory, array $entries): array
    {
        $zip = new \ZipArchive();
        $openResult = $zip->open($archivePath);
        if ($openResult !== true) {
            return [
                'status' => 'error',
                'error' => 'Não foi possível abrir o arquivo .zip selecionado para leitura.',
                'zip_open_result' => $openResult,
            ];
        }

        $importedFiles = 0;
        $importedBytes = 0;

        try {
            foreach ($entries as $entry) {
                $stream = $zip->getStream($entry['entry_name']);
                if (!is_resource($stream)) {
                    return [
                        'status' => 'error',
                        'error' => 'Falha ao ler uma entrada do arquivo .zip.',
                        'entry_name' => $entry['entry_name'],
                    ];
                }

                $targetPath = rtrim($targetDirectory, '/') . '/' . $entry['file_name'];
                $temporaryPath = $targetPath . '.part-' . bin2hex(random_bytes(4));
                $targetHandle = @fopen($temporaryPath, 'wb');

                if (!is_resource($targetHandle)) {
                    fclose($stream);

                    return [
                        'status' => 'error',
                        'error' => 'Falha ao criar um arquivo temporário para importação.',
                        'target_path' => $targetPath,
                    ];
                }

                $bytesCopied = stream_copy_to_stream($stream, $targetHandle);
                fclose($stream);
                fclose($targetHandle);

                if ($bytesCopied === false) {
                    @unlink($temporaryPath);

                    return [
                        'status' => 'error',
                        'error' => 'Falha ao copiar o conteúdo do arquivo .zip para o destino.',
                        'target_path' => $targetPath,
                    ];
                }

                if (!@rename($temporaryPath, $targetPath)) {
                    @unlink($temporaryPath);

                    return [
                        'status' => 'error',
                        'error' => 'Falha ao publicar o arquivo importado no diretório de destino.',
                        'target_path' => $targetPath,
                    ];
                }

                @chmod($targetPath, 0644);

                $importedFiles++;
                $importedBytes += (int) $bytesCopied;
            }
        } finally {
            $zip->close();
        }

        return [
            'status' => 'ok',
            'imported_files' => $importedFiles,
            'imported_bytes' => $importedBytes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateArchiveEntries(string $archivePath): array
    {
        $zip = new \ZipArchive();
        $openResult = $zip->open($archivePath);
        if ($openResult !== true) {
            return [
                'status' => 'error',
                'error' => 'Não foi possível abrir o arquivo .zip selecionado.',
                'zip_open_result' => $openResult,
            ];
        }

        $entries = [];
        $invalidEntries = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);
                if ($entryName === false) {
                    continue;
                }

                $normalizedEntryName = trim(str_replace('\\', '/', $entryName), '/');
                if ($normalizedEntryName === '' || str_ends_with($entryName, '/')) {
                    continue;
                }

                if (str_starts_with($normalizedEntryName, '__MACOSX/')) {
                    continue;
                }

                if (
                    str_contains($normalizedEntryName, '../')
                    || str_contains($normalizedEntryName, '/')
                    || str_contains($normalizedEntryName, "\0")
                ) {
                    $invalidEntries[] = $entryName;
                    continue;
                }

                $fileName = basename($normalizedEntryName);
                if ($fileName === '' || $fileName === '.' || $fileName === '..') {
                    $invalidEntries[] = $entryName;
                    continue;
                }

                $entries[] = [
                    'entry_name' => $entryName,
                    'file_name' => $fileName,
                ];
            }
        } finally {
            $zip->close();
        }

        if ($invalidEntries !== []) {
            return [
                'status' => 'error',
                'error' => 'O arquivo .zip contém entradas inválidas para o storage gerenciado.',
                'invalid_entries' => $invalidEntries,
            ];
        }

        if ($entries === []) {
            return [
                'status' => 'error',
                'error' => 'O arquivo .zip não contém arquivos válidos para importação.',
            ];
        }

        return [
            'status' => 'ok',
            'entries' => $entries,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveArchiveCandidates(string $defaultArchiveName, ?string $archive): array
    {
        $archiveFileName = $this->normalizeArchiveFileName($archive, $defaultArchiveName);
        $candidates = [];

        foreach ($this->archiveSearchRoots() as $root) {
            $candidates[] = rtrim($root, '/') . '/' . $archiveFileName;
        }

        return array_values(array_unique($candidates));
    }

    private function normalizeArchiveFileName(?string $archive, string $defaultArchiveName): string
    {
        $selectedArchive = trim((string) $archive);
        if ($selectedArchive === '') {
            return $defaultArchiveName . '.zip';
        }

        $baseName = basename($selectedArchive);
        if ($baseName === '' || $baseName === '.' || $baseName === '..') {
            return $defaultArchiveName . '.zip';
        }

        if (!str_ends_with(strtolower($baseName), '.zip')) {
            $baseName .= '.zip';
        }

        return $baseName;
    }

    /**
     * @param array<int, string> $candidates
     */
    private function selectArchiveCandidate(array $candidates): ?string
    {
        foreach ($candidates as $candidatePath) {
            if (is_file($candidatePath) && is_readable($candidatePath)) {
                return $candidatePath;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function archiveSearchRoots(): array
    {
        $roots = [];
        $managedRoot = $this->storage()->resolveManagedStorageRoot();

        if ($managedRoot !== null) {
            $roots[] = $managedRoot . '/imports/managed-storage-zips';
            $roots[] = $managedRoot . '/imports';
        }

        $roots[] = $this->projectRoot . '/var/imports/managed-storage-zips';
        $roots[] = $this->projectRoot . '/var/imports';
        $roots[] = $this->projectRoot . '/var/exports/managed-storage-zips';

        return array_values(array_unique($roots));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function packageDefinitions(): array
    {
        return [
            'bookshop_covers' => [
                'archive_name' => 'bookshop-covers',
                'directory_env_key' => 'BOOKSHOP_COVER_UPLOAD_DIR',
                'default_directory' => 'var/storage/bookshop/covers',
            ],
            'library_docs' => [
                'archive_name' => 'library-docs',
                'directory_env_key' => 'LIBRARY_UPLOAD_DIR',
                'default_directory' => 'var/storage/library/docs',
            ],
            'library_covers' => [
                'archive_name' => 'library-covers',
                'directory_env_key' => 'LIBRARY_COVER_UPLOAD_DIR',
                'default_directory' => 'var/storage/library/covers',
            ],
            'member_photos' => [
                'archive_name' => 'member-photos',
                'directory_env_key' => 'MEMBER_PROFILE_PHOTO_UPLOAD_DIR',
                'default_directory' => 'var/storage/member-photos',
            ],
            'patrimony_docs' => [
                'archive_name' => 'patrimony-docs',
                'directory_env_key' => 'PATRIMONY_DOCUMENT_UPLOAD_DIR',
                'default_directory' => 'var/storage/patrimony/docs',
            ],
            'patrimony_images' => [
                'archive_name' => 'patrimony-img',
                'directory_env_key' => 'PATRIMONY_IMAGE_UPLOAD_DIR',
                'default_directory' => 'var/storage/patrimony/img',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function resolveSelectedPackages(?string $kind): array
    {
        $packages = $this->packageDefinitions();
        $normalizedKind = trim((string) $kind);

        if ($normalizedKind === '' || $normalizedKind === 'all') {
            return $packages;
        }

        return isset($packages[$normalizedKind]) ? [$normalizedKind => $packages[$normalizedKind]] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function describePath(?string $path, bool $directory): array
    {
        $normalizedPath = trim((string) $path);

        if ($normalizedPath === '') {
            return [
                'path' => '',
                'exists' => false,
                'readable' => false,
                'writable' => false,
                'permissions' => null,
                'realpath' => null,
                'size_bytes' => null,
            ];
        }

        $exists = $directory ? is_dir($normalizedPath) : is_file($normalizedPath);
        $realPath = $exists ? realpath($normalizedPath) : false;

        return [
            'path' => $normalizedPath,
            'exists' => $exists,
            'readable' => $exists && is_readable($normalizedPath),
            'writable' => $exists && is_writable($normalizedPath),
            'permissions' => $exists ? substr(sprintf('%o', (int) @fileperms($normalizedPath)), -4) : null,
            'realpath' => $realPath !== false ? $realPath : null,
            'size_bytes' => !$directory && $exists ? (int) @filesize($normalizedPath) : null,
        ];
    }

    private function storage(): ManagedUploadStorage
    {
        return new ManagedUploadStorage($this->projectRoot, $this->env);
    }
}
