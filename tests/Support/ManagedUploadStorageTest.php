<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ManagedUploadStorage;
use PHPUnit\Framework\TestCase;

final class ManagedUploadStorageTest extends TestCase
{
    public function testBuildReadDefinitionsSkipsProjectStorageFallbackWhenManagedRootIsActive(): void
    {
        $storage = new ManagedUploadStorage('/var/www/cedern', [
            'APP_MANAGED_STORAGE_ROOT' => '/var/www/_cedern_storage',
        ]);

        $definitions = $storage->buildReadDefinitions(
            'BOOKSHOP_COVER_UPLOAD_DIR',
            'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
            'var/storage/bookshop/covers',
            'media/livraria/capas'
        );

        $this->assertSame([
            [
                'directory' => '/var/www/_cedern_storage/bookshop/covers',
                'public_prefix' => 'media/livraria/capas',
            ],
        ], $definitions);
    }

    public function testBuildReadDefinitionsIncludesLegacyCandidatesWhenFlagIsEnabled(): void
    {
        $storage = new ManagedUploadStorage('/var/www/cedern', [
            'APP_MANAGED_STORAGE_ROOT' => '/var/www/_cedern_storage',
            'APP_ENABLE_LEGACY_MEDIA_FALLBACK' => 'true',
        ]);

        $definitions = $storage->buildReadDefinitions(
            'BOOKSHOP_COVER_UPLOAD_DIR',
            'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
            'var/storage/bookshop/covers',
            'media/livraria/capas',
            [
                [
                    'directory' => 'public/assets/img/bookshop-covers',
                    'public_prefix' => 'assets/img/bookshop-covers',
                    'directory_mode' => 'project',
                    'requires_legacy_fallback' => true,
                ],
            ]
        );

        $this->assertSame([
            [
                'directory' => '/var/www/_cedern_storage/bookshop/covers',
                'public_prefix' => 'media/livraria/capas',
            ],
            [
                'directory' => '/var/www/cedern/public/assets/img/bookshop-covers',
                'public_prefix' => 'assets/img/bookshop-covers',
            ],
        ], $definitions);
    }

    public function testBuildReadDefinitionsSkipsLegacyCandidatesWhenFlagIsDisabled(): void
    {
        $storage = new ManagedUploadStorage('/var/www/cedern', [
            'APP_MANAGED_STORAGE_ROOT' => '/var/www/_cedern_storage',
        ]);

        $definitions = $storage->buildReadDefinitions(
            'BOOKSHOP_COVER_UPLOAD_DIR',
            'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
            'var/storage/bookshop/covers',
            'media/livraria/capas',
            [
                [
                    'directory' => 'public/assets/img/bookshop-covers',
                    'public_prefix' => 'assets/img/bookshop-covers',
                    'directory_mode' => 'project',
                    'requires_legacy_fallback' => true,
                ],
            ]
        );

        $this->assertSame([
            [
                'directory' => '/var/www/_cedern_storage/bookshop/covers',
                'public_prefix' => 'media/livraria/capas',
            ],
        ], $definitions);
    }

    public function testBuildReadDefinitionsIncludesProjectStorageFallbackWithoutManagedRoot(): void
    {
        $storage = new ManagedUploadStorage('/var/www/cedern');

        $definitions = $storage->buildReadDefinitions(
            'BOOKSHOP_COVER_UPLOAD_DIR',
            'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
            'var/storage/bookshop/covers',
            'media/livraria/capas'
        );

        $this->assertSame([
            [
                'directory' => '/var/www/cedern/var/storage/bookshop/covers',
                'public_prefix' => 'media/livraria/capas',
            ],
        ], $definitions);
    }

    public function testResolveUploadDirectoryPrefersConfiguredPath(): void
    {
        $storage = new ManagedUploadStorage('/var/www/cedern', [
            'APP_MANAGED_STORAGE_ROOT' => '/var/www/_cedern_storage',
            'MEMBER_PROFILE_PHOTO_UPLOAD_DIR' => 'var/storage/member-photos',
        ]);

        $this->assertSame(
            '/var/www/_cedern_storage/member-photos',
            $storage->resolveUploadDirectory(
                'MEMBER_PROFILE_PHOTO_UPLOAD_DIR',
                'var/storage/member-photos'
            )
        );
    }

    public function testBuildRelativePathNormalizesPublicPrefix(): void
    {
        $storage = new ManagedUploadStorage('/var/www/cedern');

        $this->assertSame(
            'media/membros/fotos/member_demo.png',
            $storage->buildRelativePath('member_demo.png', '/media/membros/fotos/')
        );
    }
}
