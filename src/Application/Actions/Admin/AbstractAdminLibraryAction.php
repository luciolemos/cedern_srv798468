<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Library\LibraryRepository;
use App\Support\ManagedMediaLocator;
use App\Support\ManagedUploadStorage;
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
        return $this->libraryStorage()->resolveUploadDirectory(
            'LIBRARY_UPLOAD_DIR',
            self::DEFAULT_LIBRARY_UPLOAD_DIR
        );
    }

    protected function resolveLibraryUploadPublicPrefix(): string
    {
        return $this->libraryStorage()->resolveUploadPublicPrefix(
            'LIBRARY_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_LIBRARY_UPLOAD_PUBLIC_PREFIX
        );
    }

    protected function buildManagedLibraryPdfRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        return $this->libraryStorage()->buildRelativePath(
            $fileName,
            (string) ($publicPrefix ?? $this->resolveLibraryUploadPublicPrefix())
        );
    }

    protected function resolveLibraryCoverUploadDirectory(): string
    {
        return $this->libraryStorage()->resolveUploadDirectory(
            'LIBRARY_COVER_UPLOAD_DIR',
            self::DEFAULT_LIBRARY_COVER_UPLOAD_DIR
        );
    }

    protected function resolveLibraryCoverUploadPublicPrefix(): string
    {
        return $this->libraryStorage()->resolveUploadPublicPrefix(
            'LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX
        );
    }

    protected function buildManagedLibraryCoverRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        return $this->libraryStorage()->buildRelativePath(
            $fileName,
            (string) ($publicPrefix ?? $this->resolveLibraryCoverUploadPublicPrefix())
        );
    }

    protected function resolveManagedLibraryPdfAbsolutePath(?string $relativePath): ?string
    {
        return ManagedMediaLocator::resolve(
            $relativePath,
            $this->resolveLibraryPdfStorageDefinitions(),
            true,
            [],
            $this->resolveLibraryRecursiveSearchRoots()
        );
    }

    protected function resolveManagedLibraryCoverAbsolutePath(?string $relativePath): ?string
    {
        return ManagedMediaLocator::resolve(
            $relativePath,
            $this->resolveLibraryCoverStorageDefinitions(),
            true,
            [],
            $this->resolveLibraryRecursiveSearchRoots()
        );
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
        return $this->libraryStorage()->buildReadDefinitions(
            $directoryEnvKey,
            $publicPrefixEnvKey,
            $defaultDirectory,
            $defaultPublicPrefix,
            [
                [
                    'directory' => $legacyDirectory,
                    'public_prefix' => $legacyPublicPrefix,
                    'directory_mode' => 'project',
                    'requires_legacy_fallback' => true,
                ],
            ]
        );
    }

    /**
     * @param array<int, array{directory: string, public_prefix: string}> $definitions
     * @return array{directory: string, public_prefix: string}|null
     */
    private function resolveWritableUploadStorage(array $definitions, string $storageLabel): ?array
    {
        $storage = $this->libraryStorage();

        if ($storage->managedWriteModeEnabled()) {
            $primaryDefinition = $definitions[0] ?? null;

            if (is_array($primaryDefinition) && $storage->prepareWritableDirectory($primaryDefinition['directory'])) {
                return $primaryDefinition;
            }

            $this->logger->warning('Diretório principal da biblioteca indisponível para escrita.', [
                'storage_label' => $storageLabel,
                'managed_storage_root' => $storage->resolveManagedStorageRoot(),
                'primary_definition' => $primaryDefinition,
            ]);

            return null;
        }

        $attemptedDirectories = [];

        foreach ($definitions as $definition) {
            $directory = $definition['directory'];
            $attemptedDirectories[] = [
                'directory' => $directory,
                'public_prefix' => $definition['public_prefix'],
            ];

            if ($storage->prepareWritableDirectory($directory)) {
                return $definition;
            }
        }

        $this->logger->warning('Nenhum diretório da biblioteca ficou gravável.', [
            'storage_label' => $storageLabel,
            'attempted' => $attemptedDirectories,
        ]);

        return null;
    }

    private function libraryStorage(): ManagedUploadStorage
    {
        return new ManagedUploadStorage(dirname(__DIR__, 4), $_ENV);
    }

    /**
     * @return array<int, string>
     */
    private function resolveLibraryRecursiveSearchRoots(): array
    {
        $managedStorageRoot = $this->libraryStorage()->resolveManagedStorageRoot();

        return $managedStorageRoot !== null ? [$managedStorageRoot] : [];
    }
}
