<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Library\LibraryRepository;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractAdminLibraryAction extends AbstractPageAction
{
    private const DEFAULT_LIBRARY_UPLOAD_DIR = 'var/storage/library/docs';
    private const DEFAULT_LIBRARY_UPLOAD_PUBLIC_PREFIX = 'media/biblioteca/docs';
    private const DEFAULT_LIBRARY_COVER_UPLOAD_DIR = 'var/storage/library/covers';
    private const DEFAULT_LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX = 'media/biblioteca/capas';
    private const LEGACY_LIBRARY_UPLOAD_DIR = 'public/assets/docs/library';
    private const LEGACY_LIBRARY_UPLOAD_PUBLIC_PREFIX = 'assets/docs/library';
    private const LEGACY_LIBRARY_COVER_UPLOAD_DIR = 'public/assets/img/library-covers';
    private const LEGACY_LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX = 'assets/img/library-covers';

    protected LibraryRepository $libraryRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, LibraryRepository $libraryRepository)
    {
        parent::__construct($logger, $twig);
        $this->libraryRepository = $libraryRepository;
    }

    protected function slugify(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = (string) preg_replace('/[^a-z0-9-]+/', '-', $normalized);

        return trim($normalized, '-');
    }

    /**
     * @return array{path?: string, mime_type?: string, size_bytes?: int, error?: string}
     */
    protected function storeBookPdf(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['error' => 'Não foi possível enviar o PDF. Tente novamente.'];
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (50 * 1024 * 1024)) {
            return ['error' => 'O PDF deve ter no máximo 50MB.'];
        }

        $clientMimeType = strtolower((string) $file->getClientMediaType());
        $clientFilename = strtolower(trim((string) $file->getClientFilename()));
        $hasPdfExtension = $clientFilename !== '' && substr($clientFilename, -4) === '.pdf';
        $allowedMimeTypes = ['application/pdf', 'application/x-pdf'];

        if (!$hasPdfExtension && !in_array($clientMimeType, $allowedMimeTypes, true)) {
            return ['error' => 'Formato inválido. Envie um arquivo PDF.'];
        }

        $storage = $this->resolveWritableLibraryPdfStorage();
        if ($storage === null) {
            return ['error' => 'O armazenamento de PDFs da biblioteca está sem permissão de escrita no servidor.'];
        }

        $targetDirectory = $storage['directory'];
        $publicPrefix = $storage['public_prefix'];

        try {
            $timestamp = date('YmdHis');
            $randomSuffix = bin2hex(random_bytes(4));
            $fileName = sprintf('book_%s_%s.pdf', $timestamp, $randomSuffix);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gerar nome de arquivo para PDF da biblioteca.', [
                'exception' => $exception,
            ]);

            return ['error' => 'Falha ao processar o PDF enviado.'];
        }

        $targetPath = $targetDirectory . '/' . $fileName;

        try {
            $file->moveTo($targetPath);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gravar PDF da biblioteca.', [
                'exception' => $exception,
                'target_path' => $targetPath,
            ]);

            return ['error' => 'Não foi possível salvar o PDF no servidor.'];
        }

        return [
            'path' => $this->buildManagedLibraryPdfRelativePath($fileName, $publicPrefix),
            'mime_type' => $clientMimeType !== '' ? $clientMimeType : 'application/pdf',
            'size_bytes' => $size,
        ];
    }

    /**
     * @return array{path?: string, mime_type?: string, size_bytes?: int, error?: string}
     */
    protected function storeBookCover(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['error' => 'Não foi possível enviar a capa do livro. Tente novamente.'];
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (5 * 1024 * 1024)) {
            return ['error' => 'A capa deve ter no máximo 5MB.'];
        }

        $clientMimeType = strtolower((string) $file->getClientMediaType());
        $clientFilename = strtolower(trim((string) $file->getClientFilename()));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $fileExtension = strtolower((string) pathinfo($clientFilename, PATHINFO_EXTENSION));

        if (!isset($allowedMimeTypes[$clientMimeType]) && !in_array($fileExtension, $allowedExtensions, true)) {
            return ['error' => 'Formato inválido para a capa. Use JPG, PNG ou WEBP.'];
        }

        $storage = $this->resolveWritableLibraryCoverStorage();
        if ($storage === null) {
            return ['error' => 'O armazenamento de capas da biblioteca está sem permissão de escrita no servidor.'];
        }

        $targetDirectory = $storage['directory'];
        $publicPrefix = $storage['public_prefix'];

        try {
            $timestamp = date('YmdHis');
            $randomSuffix = bin2hex(random_bytes(4));
            $resolvedExtension = $allowedMimeTypes[$clientMimeType] ?? ($fileExtension !== '' ? $fileExtension : 'jpg');
            $fileName = sprintf('cover_%s_%s.%s', $timestamp, $randomSuffix, $resolvedExtension);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gerar nome de arquivo para capa da biblioteca.', [
                'exception' => $exception,
            ]);

            return ['error' => 'Falha ao processar a capa enviada.'];
        }

        $targetPath = $targetDirectory . '/' . $fileName;

        try {
            $file->moveTo($targetPath);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gravar capa da biblioteca.', [
                'exception' => $exception,
                'target_path' => $targetPath,
            ]);

            return ['error' => 'Não foi possível salvar a capa no servidor.'];
        }

        return [
            'path' => $this->buildManagedLibraryCoverRelativePath($fileName, $publicPrefix),
            'mime_type' => $clientMimeType !== '' ? $clientMimeType : 'image/jpeg',
            'size_bytes' => $size,
        ];
    }

    protected function deleteStoredPdfIfManaged(?string $relativePath): void
    {
        $absolutePath = $this->resolveManagedLibraryPdfAbsolutePath($relativePath);
        if ($absolutePath === null) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    protected function deleteStoredBookCoverIfManaged(?string $relativePath): void
    {
        $absolutePath = $this->resolveManagedLibraryCoverAbsolutePath($relativePath);
        if ($absolutePath === null) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    protected function resolveLibraryUploadDirectory(): string
    {
        $configuredDirectory = $this->resolveOptionalConfiguredUploadDirectory('LIBRARY_UPLOAD_DIR');

        if ($configuredDirectory !== null) {
            return $configuredDirectory;
        }

        return $this->resolveManagedStorageDefaultDirectory(self::DEFAULT_LIBRARY_UPLOAD_DIR);
    }

    protected function resolveLibraryUploadPublicPrefix(): string
    {
        $configuredPrefix = trim((string) ($_ENV['LIBRARY_UPLOAD_PUBLIC_PREFIX'] ?? ''));
        $normalizedPrefix = $configuredPrefix !== ''
            ? $configuredPrefix
            : self::DEFAULT_LIBRARY_UPLOAD_PUBLIC_PREFIX;

        return trim(str_replace('\\', '/', $normalizedPrefix), '/');
    }

    protected function buildManagedLibraryPdfRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        $normalizedPrefix = trim((string) ($publicPrefix ?? $this->resolveLibraryUploadPublicPrefix()), '/');

        return $normalizedPrefix . '/' . ltrim($fileName, '/');
    }

    protected function resolveLibraryCoverUploadDirectory(): string
    {
        return $this->resolveConfiguredUploadDirectory(
            'LIBRARY_COVER_UPLOAD_DIR',
            self::DEFAULT_LIBRARY_COVER_UPLOAD_DIR
        );
    }

    protected function resolveLibraryCoverUploadPublicPrefix(): string
    {
        return $this->resolveConfiguredUploadPublicPrefix(
            'LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX
        );
    }

    protected function buildManagedLibraryCoverRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        $normalizedPrefix = trim((string) ($publicPrefix ?? $this->resolveLibraryCoverUploadPublicPrefix()), '/');

        return $normalizedPrefix . '/' . ltrim($fileName, '/');
    }

    protected function resolveManagedLibraryPdfAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedLibraryScopedAbsolutePath(
            $relativePath,
            $this->resolveLibraryPdfStorageDefinitions()
        );
    }

    protected function resolveManagedLibraryCoverAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedLibraryScopedAbsolutePath(
            $relativePath,
            $this->resolveLibraryCoverStorageDefinitions()
        );
    }

    private function resolveProjectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    private function resolveWritableLibraryPdfStorage(): ?array
    {
        return $this->resolveWritableUploadStorage(
            $this->resolveLibraryPdfStorageDefinitions(),
            'PDFs da biblioteca'
        );
    }

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    private function resolveWritableLibraryCoverStorage(): ?array
    {
        return $this->resolveWritableUploadStorage(
            $this->resolveLibraryCoverStorageDefinitions(),
            'capas da biblioteca'
        );
    }

    /**
     * @param array<int, array{directory: string, public_prefix: string}> $definitions
     */
    private function resolveManagedLibraryScopedAbsolutePath(?string $relativePath, array $definitions): ?string
    {
        $fallbackPath = null;

        foreach ($definitions as $definition) {
            $absolutePath = $this->resolveManagedAbsolutePath(
                $relativePath,
                $definition['public_prefix'],
                $definition['directory']
            );

            if ($absolutePath === null) {
                continue;
            }

            if (is_file($absolutePath)) {
                return $absolutePath;
            }

            $fallbackPath ??= $absolutePath;
        }

        return $fallbackPath;
    }

    /**
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    private function resolveLibraryPdfStorageDefinitions(): array
    {
        return $this->resolveUploadStorageDefinitions(
            'LIBRARY_UPLOAD_DIR',
            'LIBRARY_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_LIBRARY_UPLOAD_DIR,
            self::DEFAULT_LIBRARY_UPLOAD_PUBLIC_PREFIX,
            self::LEGACY_LIBRARY_UPLOAD_DIR,
            self::LEGACY_LIBRARY_UPLOAD_PUBLIC_PREFIX
        );
    }

    /**
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    private function resolveLibraryCoverStorageDefinitions(): array
    {
        return $this->resolveUploadStorageDefinitions(
            'LIBRARY_COVER_UPLOAD_DIR',
            'LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_LIBRARY_COVER_UPLOAD_DIR,
            self::DEFAULT_LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX,
            self::LEGACY_LIBRARY_COVER_UPLOAD_DIR,
            self::LEGACY_LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX
        );
    }

    /**
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    private function resolveUploadStorageDefinitions(
        string $directoryEnvKey,
        string $publicPrefixEnvKey,
        string $defaultDirectory,
        string $defaultPublicPrefix,
        string $legacyDirectory,
        string $legacyPublicPrefix
    ): array {
        $definitions = [];
        $configuredDirectory = $this->resolveOptionalConfiguredUploadDirectory($directoryEnvKey);
        $configuredPublicPrefix = $this->resolveOptionalConfiguredUploadPublicPrefix($publicPrefixEnvKey);

        if ($configuredDirectory !== null || $configuredPublicPrefix !== null) {
            $definitions[] = [
                'directory' => $configuredDirectory ?? $this->resolveManagedStorageDefaultDirectory($defaultDirectory),
                'public_prefix' => $configuredPublicPrefix ?? $this->normalizePublicPrefix($defaultPublicPrefix),
            ];
        }

        $definitions[] = [
            'directory' => $this->resolveManagedStorageDefaultDirectory($defaultDirectory),
            'public_prefix' => $this->normalizePublicPrefix($defaultPublicPrefix),
        ];
        $definitions[] = [
            'directory' => $this->resolveDirectoryPath($legacyDirectory),
            'public_prefix' => $this->normalizePublicPrefix($legacyPublicPrefix),
        ];

        $uniqueDefinitions = [];
        $seenDefinitions = [];

        foreach ($definitions as $definition) {
            $definitionHash = $definition['directory'] . '|' . $definition['public_prefix'];
            if (isset($seenDefinitions[$definitionHash])) {
                continue;
            }

            $seenDefinitions[$definitionHash] = true;
            $uniqueDefinitions[] = $definition;
        }

        return $uniqueDefinitions;
    }

    /**
     * @param array<int, array{directory: string, public_prefix: string}> $definitions
     * @return array{directory: string, public_prefix: string}|null
     */
    private function resolveWritableUploadStorage(array $definitions, string $storageLabel): ?array
    {
        $attemptedDirectories = [];

        foreach ($definitions as $definition) {
            $directory = $definition['directory'];
            $attemptedDirectories[] = [
                'directory' => $directory,
                'public_prefix' => $definition['public_prefix'],
            ];

            if ($this->prepareWritableDirectory($directory)) {
                return $definition;
            }
        }

        $this->logger->warning('Nenhum diretório da biblioteca ficou gravável.', [
            'storage_label' => $storageLabel,
            'attempted' => $attemptedDirectories,
        ]);

        return null;
    }

    private function prepareWritableDirectory(string $directory): bool
    {
        clearstatcache(true, $directory);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        if (!is_writable($directory)) {
            @chmod($directory, 0775);
            clearstatcache(true, $directory);
        }

        return is_writable($directory);
    }

    private function resolveConfiguredUploadDirectory(string $envKey, string $defaultDirectory): string
    {
        $configuredDirectory = $this->resolveOptionalConfiguredUploadDirectory($envKey);

        if ($configuredDirectory !== null) {
            return $configuredDirectory;
        }

        return $this->resolveManagedStorageDefaultDirectory($defaultDirectory);
    }

    private function resolveConfiguredUploadPublicPrefix(string $envKey, string $defaultPrefix): string
    {
        $configuredPrefix = $this->resolveOptionalConfiguredUploadPublicPrefix($envKey);

        if ($configuredPrefix !== null) {
            return $configuredPrefix;
        }

        return $this->normalizePublicPrefix($defaultPrefix);
    }

    private function resolveOptionalConfiguredUploadDirectory(string $envKey): ?string
    {
        $configuredDirectory = trim((string) ($_ENV[$envKey] ?? ''));

        if ($configuredDirectory === '') {
            return null;
        }

        return $this->resolveDirectoryPath($configuredDirectory);
    }

    private function resolveOptionalConfiguredUploadPublicPrefix(string $envKey): ?string
    {
        $configuredPrefix = trim((string) ($_ENV[$envKey] ?? ''));

        if ($configuredPrefix === '') {
            return null;
        }

        return $this->normalizePublicPrefix($configuredPrefix);
    }

    private function resolveManagedStorageDefaultDirectory(string $defaultDirectory): string
    {
        $managedStorageRoot = $this->resolveManagedStorageRoot();
        if ($managedStorageRoot === null) {
            return $this->resolveDirectoryPath($defaultDirectory);
        }

        $normalizedDefaultDirectory = ltrim(str_replace('\\', '/', $defaultDirectory), '/');
        $storagePrefix = 'var/storage/';

        if (!str_starts_with($normalizedDefaultDirectory, $storagePrefix)) {
            return $this->resolveDirectoryPath($defaultDirectory);
        }

        $storageSuffix = ltrim(substr($normalizedDefaultDirectory, strlen($storagePrefix)), '/');

        return $managedStorageRoot . '/' . $storageSuffix;
    }

    private function resolveManagedStorageRoot(): ?string
    {
        $configuredRoot = trim((string) ($_ENV['APP_MANAGED_STORAGE_ROOT'] ?? ''));

        if ($configuredRoot === '') {
            return null;
        }

        return $this->resolveDirectoryPath($configuredRoot);
    }

    private function resolveDirectoryPath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);

        if ($this->isAbsolutePath($normalizedPath)) {
            return rtrim($normalizedPath, '/');
        }

        return $this->resolveProjectRoot() . '/' . ltrim($normalizedPath, '/');
    }

    private function normalizePublicPrefix(string $prefix): string
    {
        return trim(str_replace('\\', '/', $prefix), '/');
    }

    private function resolveManagedAbsolutePath(?string $relativePath, string $publicPrefix, string $directory): ?string
    {
        $normalizedPath = ltrim(trim((string) $relativePath), '/');

        if (
            $normalizedPath === ''
            || $publicPrefix === ''
            || !str_starts_with($normalizedPath, $publicPrefix . '/')
        ) {
            return null;
        }

        $relativeFilePath = ltrim(substr($normalizedPath, strlen($publicPrefix)), '/');
        if (
            $relativeFilePath === ''
            || str_contains($relativeFilePath, '../')
            || str_contains($relativeFilePath, '..\\')
        ) {
            return null;
        }

        return $directory . '/' . $relativeFilePath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
