<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Support\ManagedMediaLocator;
use App\Support\ManagedUploadStorage;

trait MemberProfilePhotoStorageTrait
{
    private const DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_DIR = 'var/storage/member-photos';
    private const DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX = 'media/membros/fotos';
    private const LEGACY_MEMBER_PROFILE_PHOTO_UPLOAD_DIR = 'public/assets/img/member-photos';
    private const LEGACY_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX = 'assets/img/member-photos';
    private const LEGACY_MEMBER_AVATAR_UPLOAD_DIR = 'public/assets/img/avatar';
    private const LEGACY_MEMBER_AVATAR_UPLOAD_PUBLIC_PREFIX = 'assets/img/avatar';
    private const LEGACY_MEMBER_GENERIC_IMAGE_UPLOAD_DIR = 'public/assets/img';

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    protected function resolveWritableMemberProfilePhotoStorage(): ?array
    {
        $storage = $this->memberProfilePhotoStorage();

        if ($storage->managedWriteModeEnabled()) {
            $primaryDefinition = $this->resolvePrimaryMemberProfilePhotoStorageDefinition();

            if ($primaryDefinition === null) {
                return null;
            }

            if ($storage->prepareWritableDirectory($primaryDefinition['directory'])) {
                return $primaryDefinition;
            }

            $this->logger->warning('Diretório principal de foto de perfil indisponível para escrita.', [
                'directory' => $primaryDefinition['directory'],
                'public_prefix' => $primaryDefinition['public_prefix'],
                'managed_storage_root' => $storage->resolveManagedStorageRoot(),
            ]);

            return null;
        }

        $attemptedDirectories = [];

        foreach ($this->resolveMemberProfilePhotoStorageDefinitions() as $definition) {
            $directory = $definition['directory'];
            $attemptedDirectories[] = [
                'directory' => $directory,
                'public_prefix' => $definition['public_prefix'],
            ];

            if ($storage->prepareWritableDirectory($directory)) {
                return $definition;
            }
        }

        $this->logger->warning('Nenhum diretório de foto de perfil ficou gravável.', [
            'attempted' => $attemptedDirectories,
        ]);

        return null;
    }

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    private function resolvePrimaryMemberProfilePhotoStorageDefinition(): ?array
    {
        $definitions = $this->resolveMemberProfilePhotoStorageDefinitions();

        return $definitions[0] ?? null;
    }

    protected function buildManagedMemberProfilePhotoRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        return $this->memberProfilePhotoStorage()->buildRelativePath(
            $fileName,
            (string) ($publicPrefix ?? $this->resolveMemberProfilePhotoUploadPublicPrefix())
        );
    }

    protected function resolveManagedMemberProfilePhotoAbsolutePath(?string $relativePath): ?string
    {
        return ManagedMediaLocator::resolve(
            $relativePath,
            $this->resolveMemberProfilePhotoStorageDefinitions(),
            [
                $this->resolveMemberProfilePhotoDirectoryPath(self::LEGACY_MEMBER_GENERIC_IMAGE_UPLOAD_DIR),
            ]
        );
    }

    /**
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    protected function resolveMemberProfilePhotoStorageDefinitions(): array
    {
        return $this->memberProfilePhotoStorage()->buildReadDefinitions(
            'MEMBER_PROFILE_PHOTO_UPLOAD_DIR',
            'MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_DIR,
            self::DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX,
            [
                [
                    'directory' => self::LEGACY_MEMBER_PROFILE_PHOTO_UPLOAD_DIR,
                    'public_prefix' => self::LEGACY_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX,
                    'directory_mode' => 'project',
                ],
                [
                    'directory' => self::LEGACY_MEMBER_AVATAR_UPLOAD_DIR,
                    'public_prefix' => self::LEGACY_MEMBER_AVATAR_UPLOAD_PUBLIC_PREFIX,
                    'directory_mode' => 'project',
                ],
            ]
        );
    }

    protected function resolveMemberProfilePhotoUploadPublicPrefix(): string
    {
        return $this->memberProfilePhotoStorage()->resolveUploadPublicPrefix(
            'MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX
        );
    }

    private function resolveMemberProfilePhotoDirectoryPath(string $path): string
    {
        return $this->memberProfilePhotoStorage()->resolveProjectPath($path);
    }

    private function memberProfilePhotoStorage(): ManagedUploadStorage
    {
        return new ManagedUploadStorage(dirname(__DIR__, 4), $_ENV);
    }
}
