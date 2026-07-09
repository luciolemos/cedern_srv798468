<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ManagedUploadStorage;
use PHPUnit\Framework\TestCase;

final class ManagedUploadStorageTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        rsort($this->temporaryDirectories);

        foreach ($this->temporaryDirectories as $directoryPath) {
            if (is_dir($directoryPath)) {
                @rmdir($directoryPath);
            }
        }

        parent::tearDown();
    }

    public function testBuildReadDefinitionsIncludesManagedProjectAndLegacyCandidates(): void
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
                ],
            ]
        );

        $this->assertSame([
            [
                'directory' => '/var/www/_cedern_storage/bookshop/covers',
                'public_prefix' => 'media/livraria/capas',
            ],
            [
                'directory' => '/var/www/cedern/var/storage/bookshop/covers',
                'public_prefix' => 'media/livraria/capas',
            ],
            [
                'directory' => '/var/www/cedern/public/assets/img/bookshop-covers',
                'public_prefix' => 'assets/img/bookshop-covers',
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

    public function testResolveManagedStorageDefaultDirectoryAutoDetectsExistingSharedStorageOutsidePublishedTree(): void
    {
        $baseDirectory = sys_get_temp_dir() . '/cedern-managed-upload-storage-' . bin2hex(random_bytes(4));
        $projectRoot = $baseDirectory . '/domains/cedern.org/public_html';
        $sharedRoot = $baseDirectory . '/_cedern_storage';
        $sharedMemberDirectory = $sharedRoot . '/member-photos';

        mkdir($projectRoot, 0775, true);
        mkdir($sharedMemberDirectory, 0775, true);

        $this->temporaryDirectories[] = $sharedMemberDirectory;
        $this->temporaryDirectories[] = $sharedRoot;
        $this->temporaryDirectories[] = $baseDirectory . '/domains/cedern.org';
        $this->temporaryDirectories[] = $baseDirectory . '/domains';
        $this->temporaryDirectories[] = $projectRoot;
        $this->temporaryDirectories[] = $baseDirectory;

        $storage = new ManagedUploadStorage($projectRoot, []);

        $this->assertSame(
            $sharedMemberDirectory,
            $storage->resolveManagedStorageDefaultDirectory('var/storage/member-photos')
        );
        $this->assertSame(
            $sharedMemberDirectory,
            $storage->resolveUploadDirectory('MEMBER_PROFILE_PHOTO_UPLOAD_DIR', 'var/storage/member-photos')
        );
    }
}
