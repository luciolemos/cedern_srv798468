<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

trait MemberProfilePhotoStorageTrait
{
    private const DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_DIR = 'var/storage/member-photos';
    private const DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX = 'media/membros/fotos';
    private const LEGACY_MEMBER_PROFILE_PHOTO_UPLOAD_DIR = 'public/assets/img/member-photos';
    private const LEGACY_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX = 'assets/img/member-photos';
    private const LEGACY_MEMBER_AVATAR_UPLOAD_DIR = 'public/assets/img/avatar';
    private const LEGACY_MEMBER_AVATAR_UPLOAD_PUBLIC_PREFIX = 'assets/img/avatar';

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    protected function resolveWritableMemberProfilePhotoStorage(): ?array
    {
        $attemptedDirectories = [];

        foreach ($this->resolveMemberProfilePhotoStorageDefinitions() as $definition) {
            $directory = $definition['directory'];
            $attemptedDirectories[] = [
                'directory' => $directory,
                'public_prefix' => $definition['public_prefix'],
            ];

            if ($this->prepareWritableMemberProfilePhotoDirectory($directory)) {
                return $definition;
            }
        }

        $this->logger->warning('Nenhum diretório de foto de perfil ficou gravável.', [
            'attempted' => $attemptedDirectories,
        ]);

        return null;
    }

    protected function buildManagedMemberProfilePhotoRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        $normalizedPrefix = trim((string) ($publicPrefix ?? $this->resolveMemberProfilePhotoUploadPublicPrefix()), '/');

        return $normalizedPrefix . '/' . ltrim($fileName, '/');
    }

    protected function resolveManagedMemberProfilePhotoAbsolutePath(?string $relativePath): ?string
    {
        $normalizedPath = ltrim(trim((string) $relativePath), '/');
        $fallbackPath = null;

        foreach ($this->resolveMemberProfilePhotoStorageDefinitions() as $definition) {
            $absolutePath = $this->resolveManagedAbsoluteMemberProfilePhotoPath(
                $normalizedPath,
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

        $fileNameFallbackPath = $this->resolveManagedMemberProfilePhotoFallbackByFileName($normalizedPath);
        if ($fileNameFallbackPath !== null) {
            return $fileNameFallbackPath;
        }

        return $fallbackPath;
    }

    /**
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    protected function resolveMemberProfilePhotoStorageDefinitions(): array
    {
        $definitions = [];
        $configuredDirectory = $this->resolveOptionalConfiguredMemberProfilePhotoUploadDirectory();
        $configuredPublicPrefix = $this->resolveOptionalConfiguredMemberProfilePhotoUploadPublicPrefix();

        if ($configuredDirectory !== null || $configuredPublicPrefix !== null) {
            $definitions[] = [
                'directory' => $configuredDirectory ?? $this->resolveManagedStorageDefaultDirectory(
                    self::DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_DIR
                ),
                'public_prefix' => $configuredPublicPrefix
                    ?? $this->normalizeMemberProfilePhotoPublicPrefix(
                        self::DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX
                    ),
            ];
        }

        $definitions[] = [
            'directory' => $this->resolveManagedStorageDefaultDirectory(self::DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_DIR),
            'public_prefix' => $this->normalizeMemberProfilePhotoPublicPrefix(
                self::DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX
            ),
        ];
        $definitions[] = [
            'directory' => $this->resolveMemberProfilePhotoDirectoryPath(self::LEGACY_MEMBER_PROFILE_PHOTO_UPLOAD_DIR),
            'public_prefix' => $this->normalizeMemberProfilePhotoPublicPrefix(
                self::LEGACY_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX
            ),
        ];
        $definitions[] = [
            'directory' => $this->resolveMemberProfilePhotoDirectoryPath(self::LEGACY_MEMBER_AVATAR_UPLOAD_DIR),
            'public_prefix' => $this->normalizeMemberProfilePhotoPublicPrefix(
                self::LEGACY_MEMBER_AVATAR_UPLOAD_PUBLIC_PREFIX
            ),
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

    protected function resolveMemberProfilePhotoUploadPublicPrefix(): string
    {
        $configuredPrefix = $this->resolveOptionalConfiguredMemberProfilePhotoUploadPublicPrefix();

        if ($configuredPrefix !== null) {
            return $configuredPrefix;
        }

        return $this->normalizeMemberProfilePhotoPublicPrefix(
            self::DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX
        );
    }

    private function resolveOptionalConfiguredMemberProfilePhotoUploadDirectory(): ?string
    {
        $configuredDirectory = trim((string) ($_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'] ?? ''));

        if ($configuredDirectory === '') {
            return null;
        }

        return $this->resolveMemberProfilePhotoDirectoryPath($configuredDirectory);
    }

    private function resolveOptionalConfiguredMemberProfilePhotoUploadPublicPrefix(): ?string
    {
        $configuredPrefix = trim((string) ($_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'] ?? ''));

        if ($configuredPrefix === '') {
            return null;
        }

        return $this->normalizeMemberProfilePhotoPublicPrefix($configuredPrefix);
    }

    private function prepareWritableMemberProfilePhotoDirectory(string $directory): bool
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

    private function resolveManagedStorageDefaultDirectory(string $defaultDirectory): string
    {
        $managedStorageRoot = $this->resolveManagedStorageRoot();
        if ($managedStorageRoot === null) {
            return $this->resolveMemberProfilePhotoDirectoryPath($defaultDirectory);
        }

        $normalizedDefaultDirectory = ltrim(str_replace('\\', '/', $defaultDirectory), '/');
        $storagePrefix = 'var/storage/';

        if (!str_starts_with($normalizedDefaultDirectory, $storagePrefix)) {
            return $this->resolveMemberProfilePhotoDirectoryPath($defaultDirectory);
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

        return $this->resolveMemberProfilePhotoDirectoryPath($configuredRoot);
    }

    private function resolveMemberProfilePhotoDirectoryPath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);

        if ($this->isAbsoluteMemberProfilePhotoPath($normalizedPath)) {
            return rtrim($normalizedPath, '/');
        }

        return $this->resolveMemberProfilePhotoProjectRoot() . '/' . ltrim($normalizedPath, '/');
    }

    private function normalizeMemberProfilePhotoPublicPrefix(string $prefix): string
    {
        return trim(str_replace('\\', '/', $prefix), '/');
    }

    private function resolveManagedAbsoluteMemberProfilePhotoPath(
        ?string $relativePath,
        string $publicPrefix,
        string $directory
    ): ?string {
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

    private function resolveManagedMemberProfilePhotoFallbackByFileName(string $normalizedPath): ?string
    {
        if ($normalizedPath === '') {
            return null;
        }

        $fileName = basename(str_replace('\\', '/', $normalizedPath));
        if (
            $fileName === ''
            || $fileName === '.'
            || $fileName === '..'
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $fileName) !== 1
        ) {
            return null;
        }

        foreach ($this->resolveMemberProfilePhotoStorageDefinitions() as $definition) {
            $candidatePath = $definition['directory'] . '/' . $fileName;

            if (is_file($candidatePath)) {
                return $candidatePath;
            }
        }

        return null;
    }

    private function resolveMemberProfilePhotoProjectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private function isAbsoluteMemberProfilePhotoPath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
