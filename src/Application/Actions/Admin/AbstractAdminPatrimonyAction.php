<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Patrimony\PatrimonyRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractAdminPatrimonyAction extends AbstractPageAction
{
    private const DEFAULT_PATRIMONY_DOC_UPLOAD_DIR = 'var/storage/patrimony/docs';
    private const DEFAULT_PATRIMONY_DOC_UPLOAD_PUBLIC_PREFIX = 'media/patrimonio/docs';
    private const DEFAULT_PATRIMONY_IMAGE_UPLOAD_DIR = 'var/storage/patrimony/img';
    private const DEFAULT_PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX = 'media/patrimonio/img';
    private const LEGACY_PATRIMONY_DOC_UPLOAD_DIR = 'public/assets/docs/patrimony';
    private const LEGACY_PATRIMONY_DOC_UPLOAD_PUBLIC_PREFIX = 'assets/docs/patrimony';
    private const LEGACY_PATRIMONY_IMAGE_UPLOAD_DIR = 'public/assets/img/patrimony';
    private const LEGACY_PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX = 'assets/img/patrimony';

    protected PatrimonyRepository $patrimonyRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, PatrimonyRepository $patrimonyRepository)
    {
        parent::__construct($logger, $twig);
        $this->patrimonyRepository = $patrimonyRepository;
    }

    /**
     * @return array<string, string>
     */
    protected function acquisitionTypeOptions(): array
    {
        return [
            'compra' => 'Compra',
            'doacao' => 'Doação',
            'campanha_beneficente' => 'Campanha beneficente',
            'contribuicao_trabalhador' => 'Contribuição de trabalhador',
            'transferencia' => 'Transferência',
            'permuta' => 'Permuta',
            'comodato' => 'Comodato',
            'inventario_inicial' => 'Inventário inicial',
            'outro' => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        return [
            'em_uso' => 'Em uso',
            'em_estoque' => 'Em estoque',
            'reservado' => 'Reservado',
            'em_manutencao' => 'Em manutenção',
            'emprestado' => 'Emprestado',
            'danificado' => 'Danificado',
            'baixado' => 'Baixado',
            'extraviado' => 'Extraviado',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function editableStatusOptions(): array
    {
        $options = $this->statusOptions();
        unset($options['baixado']);

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function conservationOptions(): array
    {
        return [
            'novo' => 'Novo',
            'excelente' => 'Excelente',
            'bom' => 'Bom',
            'regular' => 'Regular',
            'ruim' => 'Ruim',
            'inservivel' => 'Inservível',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function disposalReasonOptions(): array
    {
        return [
            'obsolescencia' => 'Obsolescência',
            'quebra' => 'Quebra',
            'venda' => 'Venda',
            'doacao' => 'Doação',
            'descarte' => 'Descarte',
            'extravio' => 'Extravio',
            'furto' => 'Furto',
            'incendio' => 'Incêndio',
            'substituicao' => 'Substituição',
            'outro' => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attachmentTypeOptions(): array
    {
        return [
            'foto_complementar' => 'Foto complementar',
            'manual' => 'Manual',
            'nota_fiscal' => 'Nota fiscal',
            'garantia' => 'Garantia',
            'certificado' => 'Certificado',
            'outro' => 'Outro',
        ];
    }

    protected function slugify(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = (string) preg_replace('/[^a-z0-9-]+/', '-', $normalized);

        return trim($normalized, '-');
    }

    protected function assetListPath(): string
    {
        return '/painel/patrimonio';
    }

    protected function dashboardPath(): string
    {
        return '/painel';
    }

    protected function assetFormPath(?int $assetId): string
    {
        return ($assetId !== null && $assetId > 0)
            ? '/painel/patrimonio/' . $assetId . '/editar'
            : '/painel/patrimonio/novo';
    }

    protected function assetViewPath(int $assetId): string
    {
        return '/painel/patrimonio/' . $assetId;
    }

    protected function assetMovementPath(int $assetId): string
    {
        return '/painel/patrimonio/' . $assetId . '/movimentar';
    }

    protected function assetDisposalPath(int $assetId): string
    {
        return '/painel/patrimonio/' . $assetId . '/baixa';
    }

    protected function assetMaintenancePath(int $assetId): string
    {
        return '/painel/patrimonio/' . $assetId . '/manutencoes/nova';
    }

    protected function assetAttachmentPath(int $assetId): string
    {
        return '/painel/patrimonio/' . $assetId . '/anexos/novo';
    }

    protected function categoryListPath(): string
    {
        return '/painel/patrimonio/categorias';
    }

    protected function categoryFormPath(?int $categoryId): string
    {
        return ($categoryId !== null && $categoryId > 0)
            ? '/painel/patrimonio/categorias/' . $categoryId . '/editar'
            : '/painel/patrimonio/categorias/nova';
    }

    protected function assetDetailFlashKey(int $assetId): string
    {
        return 'admin_patrimony_asset_detail_' . $assetId;
    }

    protected function withBasePath(Request $request, string $path): string
    {
        $appBasePath = $this->resolveAppBasePath($request);

        if (
            $appBasePath === ''
            || $path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
        ) {
            return $path;
        }

        if ($path === $appBasePath || str_starts_with($path, $appBasePath . '/')) {
            return $path;
        }

        return $appBasePath . $path;
    }

    protected function redirectTo(Request $request, Response $response, string $path, int $status = 303): Response
    {
        return $response->withHeader('Location', $this->withBasePath($request, $path))->withStatus($status);
    }

    protected function absoluteUrl(Request $request, string $path): string
    {
        $uri = $request->getUri();
        $host = trim($uri->getHost());
        $prefixedPath = $this->withBasePath($request, $path);

        if ($host === '') {
            return $prefixedPath;
        }

        $scheme = trim($uri->getScheme());
        if ($scheme === '') {
            $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
            $isHttps = $forwardedProto === 'https'
                || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
            $scheme = $isHttps ? 'https' : 'http';
        }

        $authority = $host;
        $port = $uri->getPort();
        if (
            $port !== null
            && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
        ) {
            $authority .= ':' . $port;
        }

        return $scheme . '://' . $authority . $prefixedPath;
    }

    /**
     * @return array{path?: string, mime_type?: string, size_bytes?: int, original_file_name?: string, error?: string}
     */
    protected function storePatrimonyDocument(UploadedFileInterface $file, string $prefix = 'document'): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['error' => 'Não foi possível enviar o arquivo. Tente novamente.'];
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (20 * 1024 * 1024)) {
            return ['error' => 'O arquivo deve ter no máximo 20MB.'];
        }

        $clientMimeType = strtolower((string) $file->getClientMediaType());
        $clientFilename = trim((string) $file->getClientFilename());
        $extension = strtolower((string) pathinfo($clientFilename, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (!in_array($extension, $allowedExtensions, true) && !in_array($clientMimeType, $allowedMimeTypes, true)) {
            return ['error' => 'Formato inválido. Envie PDF, imagem ou documento Office.'];
        }

        $storage = $this->resolveWritablePatrimonyDocumentStorage();
        if ($storage === null) {
            return ['error' => 'Não foi possível preparar o armazenamento de documentos no servidor.'];
        }

        $targetDirectory = $storage['directory'];
        $publicPrefix = $storage['public_prefix'];

        try {
            $timestamp = date('YmdHis');
            $randomSuffix = bin2hex(random_bytes(4));
            $resolvedExtension = $extension !== '' ? $extension : 'pdf';
            $safePrefix = $this->slugify($prefix !== '' ? $prefix : 'document');
            if ($safePrefix === '') {
                $safePrefix = 'document';
            }

            $fileName = sprintf('%s_%s_%s.%s', $safePrefix, $timestamp, $randomSuffix, $resolvedExtension);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gerar nome de documento patrimonial.', [
                'exception' => $exception,
            ]);

            return ['error' => 'Falha ao processar o arquivo enviado.'];
        }

        $targetPath = $targetDirectory . '/' . $fileName;

        try {
            $file->moveTo($targetPath);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gravar documento patrimonial.', [
                'exception' => $exception,
                'target_path' => $targetPath,
            ]);

            return ['error' => 'Não foi possível salvar o arquivo no servidor.'];
        }

        return [
            'path' => $this->buildManagedPatrimonyDocumentRelativePath($fileName, $publicPrefix),
            'mime_type' => $clientMimeType !== '' ? $clientMimeType : null,
            'size_bytes' => $size,
            'original_file_name' => $clientFilename !== '' ? $clientFilename : $fileName,
        ];
    }

    /**
     * @return array{path?: string, mime_type?: string, size_bytes?: int, original_file_name?: string, error?: string}
     */
    protected function storePatrimonyImage(UploadedFileInterface $file, string $prefix = 'photo'): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['error' => 'Não foi possível enviar a imagem. Tente novamente.'];
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (8 * 1024 * 1024)) {
            return ['error' => 'A imagem deve ter no máximo 8MB.'];
        }

        $clientMimeType = strtolower((string) $file->getClientMediaType());
        $clientFilename = trim((string) $file->getClientFilename());
        $extension = strtolower((string) pathinfo($clientFilename, PATHINFO_EXTENSION));
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimeTypes[$clientMimeType]) && !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return ['error' => 'Formato inválido para imagem. Use JPG, PNG ou WEBP.'];
        }

        $storage = $this->resolveWritablePatrimonyImageStorage();
        if ($storage === null) {
            return ['error' => 'Não foi possível preparar o armazenamento de imagens no servidor.'];
        }

        $targetDirectory = $storage['directory'];
        $publicPrefix = $storage['public_prefix'];

        try {
            $timestamp = date('YmdHis');
            $randomSuffix = bin2hex(random_bytes(4));
            $resolvedExtension = $allowedMimeTypes[$clientMimeType] ?? ($extension !== '' ? $extension : 'jpg');
            $safePrefix = $this->slugify($prefix !== '' ? $prefix : 'photo');
            if ($safePrefix === '') {
                $safePrefix = 'photo';
            }

            $fileName = sprintf('%s_%s_%s.%s', $safePrefix, $timestamp, $randomSuffix, $resolvedExtension);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gerar nome de imagem patrimonial.', [
                'exception' => $exception,
            ]);

            return ['error' => 'Falha ao processar a imagem enviada.'];
        }

        $targetPath = $targetDirectory . '/' . $fileName;

        try {
            $file->moveTo($targetPath);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gravar imagem patrimonial.', [
                'exception' => $exception,
                'target_path' => $targetPath,
            ]);

            return ['error' => 'Não foi possível salvar a imagem no servidor.'];
        }

        return [
            'path' => $this->buildManagedPatrimonyImageRelativePath($fileName, $publicPrefix),
            'mime_type' => $clientMimeType !== '' ? $clientMimeType : 'image/jpeg',
            'size_bytes' => $size,
            'original_file_name' => $clientFilename !== '' ? $clientFilename : $fileName,
        ];
    }

    protected function deleteStoredPatrimonyFileIfManaged(?string $relativePath): void
    {
        $absolutePath = $this->resolveManagedPatrimonyAbsolutePath($relativePath);
        if ($absolutePath !== null && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    protected function formatDateInput(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('Y-m-d');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    protected function formatDateTimeLocalInput(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('Y-m-d\TH:i');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    private function resolvePatrimonyDocumentUploadDirectory(): string
    {
        return $this->resolveConfiguredUploadDirectory(
            'PATRIMONY_DOCUMENT_UPLOAD_DIR',
            self::DEFAULT_PATRIMONY_DOC_UPLOAD_DIR
        );
    }

    private function resolvePatrimonyDocumentUploadPublicPrefix(): string
    {
        return $this->resolveConfiguredUploadPublicPrefix(
            'PATRIMONY_DOCUMENT_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_PATRIMONY_DOC_UPLOAD_PUBLIC_PREFIX
        );
    }

    private function resolvePatrimonyImageUploadDirectory(): string
    {
        return $this->resolveConfiguredUploadDirectory(
            'PATRIMONY_IMAGE_UPLOAD_DIR',
            self::DEFAULT_PATRIMONY_IMAGE_UPLOAD_DIR
        );
    }

    private function resolvePatrimonyImageUploadPublicPrefix(): string
    {
        return $this->resolveConfiguredUploadPublicPrefix(
            'PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX
        );
    }

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    private function resolveWritablePatrimonyDocumentStorage(): ?array
    {
        return $this->resolveWritableUploadStorage(
            $this->resolvePatrimonyDocumentStorageDefinitions(),
            'documentos patrimoniais'
        );
    }

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    private function resolveWritablePatrimonyImageStorage(): ?array
    {
        return $this->resolveWritableUploadStorage(
            $this->resolvePatrimonyImageStorageDefinitions(),
            'imagens patrimoniais'
        );
    }

    private function buildManagedPatrimonyDocumentRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        $normalizedPrefix = trim((string) ($publicPrefix ?? $this->resolvePatrimonyDocumentUploadPublicPrefix()), '/');

        return $normalizedPrefix . '/' . ltrim($fileName, '/');
    }

    private function buildManagedPatrimonyImageRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        $normalizedPrefix = trim((string) ($publicPrefix ?? $this->resolvePatrimonyImageUploadPublicPrefix()), '/');

        return $normalizedPrefix . '/' . ltrim($fileName, '/');
    }

    protected function resolveManagedPatrimonyAbsolutePath(?string $relativePath): ?string
    {
        foreach ($this->resolvePatrimonyManagedStorageDefinitions() as $storage) {
            $absolutePath = $this->resolveManagedAbsolutePath(
                $relativePath,
                $storage['public_prefix'],
                $storage['directory']
            );

            if ($absolutePath !== null) {
                return $absolutePath;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    private function resolvePatrimonyManagedStorageDefinitions(): array
    {
        return array_merge(
            $this->resolvePatrimonyDocumentStorageDefinitions(),
            $this->resolvePatrimonyImageStorageDefinitions()
        );
    }

    /**
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    private function resolvePatrimonyDocumentStorageDefinitions(): array
    {
        return $this->resolveUploadStorageDefinitions(
            'PATRIMONY_DOCUMENT_UPLOAD_DIR',
            'PATRIMONY_DOCUMENT_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_PATRIMONY_DOC_UPLOAD_DIR,
            self::DEFAULT_PATRIMONY_DOC_UPLOAD_PUBLIC_PREFIX,
            self::LEGACY_PATRIMONY_DOC_UPLOAD_DIR,
            self::LEGACY_PATRIMONY_DOC_UPLOAD_PUBLIC_PREFIX
        );
    }

    /**
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    private function resolvePatrimonyImageStorageDefinitions(): array
    {
        return $this->resolveUploadStorageDefinitions(
            'PATRIMONY_IMAGE_UPLOAD_DIR',
            'PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_PATRIMONY_IMAGE_UPLOAD_DIR,
            self::DEFAULT_PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX,
            self::LEGACY_PATRIMONY_IMAGE_UPLOAD_DIR,
            self::LEGACY_PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX
        );
    }

    private function resolveProjectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private function resolveAppBasePath(Request $request): string
    {
        $appBaseEnv = getenv('APP_BASE');
        $appBaseRaw = trim((string) ($appBaseEnv !== false ? $appBaseEnv : ($_ENV['APP_BASE'] ?? '')));
        $configuredAppBasePath = $this->normalizeBasePath($appBaseRaw);
        $requestUriPath = trim($request->getUri()->getPath());

        if ($requestUriPath === '') {
            $requestUriPath = '/';
        }

        if (
            $configuredAppBasePath === ''
            || $requestUriPath === $configuredAppBasePath
            || str_starts_with($requestUriPath, $configuredAppBasePath . '/')
        ) {
            return $configuredAppBasePath;
        }

        return '';
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
                'directory' => $configuredDirectory ?? $this->resolveDirectoryPath($defaultDirectory),
                'public_prefix' => $configuredPublicPrefix ?? $this->normalizePublicPrefix($defaultPublicPrefix),
            ];
        }

        $definitions[] = [
            'directory' => $this->resolveDirectoryPath($defaultDirectory),
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

        $this->logger->warning('Nenhum diretório de upload patrimonial ficou gravável.', [
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

        return $this->resolveDirectoryPath($defaultDirectory);
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

    private function normalizeBasePath(string $rawBasePath): string
    {
        $trimmed = trim($rawBasePath);

        if ($trimmed === '' || $trimmed === '/') {
            return '';
        }

        return '/' . trim($trimmed, '/');
    }
}
