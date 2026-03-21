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
    private const DEFAULT_LIBRARY_UPLOAD_DIR = 'public/assets/docs/library';
    private const DEFAULT_LIBRARY_UPLOAD_PUBLIC_PREFIX = 'assets/docs/library';
    private const DEFAULT_LIBRARY_COVER_UPLOAD_DIR = 'public/assets/img/library-covers';
    private const DEFAULT_LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX = 'assets/img/library-covers';

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

        $targetDirectory = $this->resolveLibraryUploadDirectory();
        $publicPrefix = $this->resolveLibraryUploadPublicPrefix();

        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            $this->logger->warning('Diretório de PDFs da biblioteca indisponível.', [
                'directory' => $targetDirectory,
                'public_prefix' => $publicPrefix,
            ]);

            return ['error' => 'Não foi possível preparar o armazenamento de PDFs da biblioteca no servidor.'];
        }

        if (!is_writable($targetDirectory)) {
            $this->logger->warning('Diretório de PDFs da biblioteca sem permissão de escrita.', [
                'directory' => $targetDirectory,
                'public_prefix' => $publicPrefix,
            ]);

            return ['error' => 'O armazenamento de PDFs da biblioteca está sem permissão de escrita no servidor.'];
        }

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
            'path' => $this->buildManagedLibraryPdfRelativePath($fileName),
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

        $targetDirectory = $this->resolveLibraryCoverUploadDirectory();
        $publicPrefix = $this->resolveLibraryCoverUploadPublicPrefix();

        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            $this->logger->warning('Diretório de capas da biblioteca indisponível.', [
                'directory' => $targetDirectory,
                'public_prefix' => $publicPrefix,
            ]);

            return ['error' => 'Não foi possível preparar o armazenamento de capas da biblioteca no servidor.'];
        }

        if (!is_writable($targetDirectory)) {
            $this->logger->warning('Diretório de capas da biblioteca sem permissão de escrita.', [
                'directory' => $targetDirectory,
                'public_prefix' => $publicPrefix,
            ]);

            return ['error' => 'O armazenamento de capas da biblioteca está sem permissão de escrita no servidor.'];
        }

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
            'path' => $this->buildManagedLibraryCoverRelativePath($fileName),
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
        $configuredDirectory = trim((string) ($_ENV['LIBRARY_UPLOAD_DIR'] ?? ''));
        $normalizedDirectory = $configuredDirectory !== ''
            ? $configuredDirectory
            : self::DEFAULT_LIBRARY_UPLOAD_DIR;

        $normalizedDirectory = str_replace('\\', '/', $normalizedDirectory);

        if ($this->isAbsolutePath($normalizedDirectory)) {
            return rtrim($normalizedDirectory, '/');
        }

        return $this->resolveProjectRoot() . '/' . ltrim($normalizedDirectory, '/');
    }

    protected function resolveLibraryUploadPublicPrefix(): string
    {
        $configuredPrefix = trim((string) ($_ENV['LIBRARY_UPLOAD_PUBLIC_PREFIX'] ?? ''));
        $normalizedPrefix = $configuredPrefix !== ''
            ? $configuredPrefix
            : self::DEFAULT_LIBRARY_UPLOAD_PUBLIC_PREFIX;

        return trim(str_replace('\\', '/', $normalizedPrefix), '/');
    }

    protected function buildManagedLibraryPdfRelativePath(string $fileName): string
    {
        return $this->resolveLibraryUploadPublicPrefix() . '/' . ltrim($fileName, '/');
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

    protected function buildManagedLibraryCoverRelativePath(string $fileName): string
    {
        return $this->resolveLibraryCoverUploadPublicPrefix() . '/' . ltrim($fileName, '/');
    }

    protected function resolveManagedLibraryPdfAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedAbsolutePath(
            $relativePath,
            $this->resolveLibraryUploadPublicPrefix(),
            $this->resolveLibraryUploadDirectory()
        );
    }

    protected function resolveManagedLibraryCoverAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedAbsolutePath(
            $relativePath,
            $this->resolveLibraryCoverUploadPublicPrefix(),
            $this->resolveLibraryCoverUploadDirectory()
        );
    }

    private function resolveProjectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private function resolveConfiguredUploadDirectory(string $envKey, string $defaultDirectory): string
    {
        $configuredDirectory = trim((string) ($_ENV[$envKey] ?? ''));
        $normalizedDirectory = $configuredDirectory !== ''
            ? $configuredDirectory
            : $defaultDirectory;

        $normalizedDirectory = str_replace('\\', '/', $normalizedDirectory);

        if ($this->isAbsolutePath($normalizedDirectory)) {
            return rtrim($normalizedDirectory, '/');
        }

        return $this->resolveProjectRoot() . '/' . ltrim($normalizedDirectory, '/');
    }

    private function resolveConfiguredUploadPublicPrefix(string $envKey, string $defaultPrefix): string
    {
        $configuredPrefix = trim((string) ($_ENV[$envKey] ?? ''));
        $normalizedPrefix = $configuredPrefix !== ''
            ? $configuredPrefix
            : $defaultPrefix;

        return trim(str_replace('\\', '/', $normalizedPrefix), '/');
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
