<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Page;

use App\Application\Actions\Page\MemberProfilePhotoStorageTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class TestableMemberProfilePhotoStorageHarness
{
    use MemberProfilePhotoStorageTrait;

    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function exposedResolveManagedMemberProfilePhotoAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedMemberProfilePhotoAbsolutePath($relativePath);
    }
}

final class MemberProfilePhotoStorageTraitTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->managedEnvKeys() as $key) {
            $this->originalEnv[$key] = array_key_exists($key, $_ENV) ? (string) $_ENV[$key] : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        foreach ($this->managedEnvKeys() as $key) {
            $originalValue = $this->originalEnv[$key] ?? null;

            if ($originalValue === null) {
                unset($_ENV[$key]);
                continue;
            }

            $_ENV[$key] = $originalValue;
        }

        parent::tearDown();
    }

    public function testResolvesManagedMemberProfilePhotoFromConfiguredStorage(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/cedern-member-photo-test-' . bin2hex(random_bytes(4));
        if (!is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0775, true);
        }

        $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'] = $temporaryDirectory;
        $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'] = 'media/membros/fotos';

        $fileName = 'member_test_resolve.png';
        $absolutePath = $temporaryDirectory . '/' . $fileName;
        file_put_contents($absolutePath, 'test-image');
        $this->temporaryFiles[] = $absolutePath;

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $absolutePath,
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/' . $fileName)
        );
    }

    public function testFallsBackToLegacyMemberPhotoDirectoryUsingOnlyFileName(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['APP_MANAGED_STORAGE_ROOT']
        );

        $projectRoot = dirname(__DIR__, 4);
        $fileName = 'member_test_legacy_fallback_' . bin2hex(random_bytes(4)) . '.jpg';
        $legacyPath = $projectRoot . '/public/assets/img/member-photos/' . $fileName;
        file_put_contents($legacyPath, 'legacy-image');
        $this->temporaryFiles[] = $legacyPath;

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $legacyPath,
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/' . $fileName)
        );
    }

    public function testUsesSharedManagedStorageRootWhenConfigured(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX']
        );
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            '/srv/cede-managed-storage/member-photos/member_demo.png',
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/member_demo.png')
        );
    }

    public function testRebasesRelativeConfiguredMemberPhotoDirectoryIntoManagedRoot(): void
    {
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';
        $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'] = './var/storage/member-photos';
        $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'] = 'media/membros/fotos';

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            '/srv/cede-managed-storage/member-photos/member_demo.png',
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/member_demo.png')
        );
    }

    /**
     * @return list<string>
     */
    private function managedEnvKeys(): array
    {
        return [
            'MEMBER_PROFILE_PHOTO_UPLOAD_DIR',
            'MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX',
            'APP_MANAGED_STORAGE_ROOT',
        ];
    }
}
