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

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    public function exposedResolveWritableMemberProfilePhotoStorage(): ?array
    {
        return $this->resolveWritableMemberProfilePhotoStorage();
    }

    public function exposedDeleteStoredMemberProfilePhotoIfManaged(?string $relativePath): void
    {
        $this->deleteStoredMemberProfilePhotoIfManaged($relativePath);
    }
}

final class MemberProfilePhotoStorageTraitTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

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

        foreach ($this->temporaryDirectories as $directoryPath) {
            if (is_dir($directoryPath)) {
                @rmdir($directoryPath);
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

    public function testDoesNotFallBackToLegacyMemberPhotoDirectoryUsingOnlyFileName(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['APP_MANAGED_STORAGE_ROOT']
        );

        $projectRoot = dirname(__DIR__, 4);
        $fileName = 'member_test_legacy_fallback_' . bin2hex(random_bytes(4)) . '.jpg';
        $this->ensureDirectoryExists($projectRoot . '/public/assets/img/member-photos');
        $legacyPath = $projectRoot . '/public/assets/img/member-photos/' . $fileName;
        file_put_contents($legacyPath, 'legacy-image');
        $this->temporaryFiles[] = $legacyPath;

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $projectRoot . '/var/storage/member-photos/' . $fileName,
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/' . $fileName)
        );
    }

    public function testDoesNotFallBackToLegacyGenericImageDirectoryUsingOnlyFileName(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['APP_MANAGED_STORAGE_ROOT']
        );

        $projectRoot = dirname(__DIR__, 4);
        $fileName = 'member_test_generic_fallback_' . bin2hex(random_bytes(4)) . '.jpg';
        $legacyPath = $projectRoot . '/public/assets/img/' . $fileName;
        file_put_contents($legacyPath, 'legacy-generic-image');
        $this->temporaryFiles[] = $legacyPath;

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $projectRoot . '/var/storage/member-photos/' . $fileName,
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

    public function testDoesNotResolveLegacyAssetsPathWhenLegacyFallbackIsDisabled(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['APP_MANAGED_STORAGE_ROOT'],
            $_ENV['APP_ENABLE_LEGACY_MEDIA_FALLBACK']
        );

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertNull(
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath(
                'assets/img/member-photos/member_demo.png'
            )
        );
    }

    public function testResolvesLegacyAssetsPathWhenLegacyFallbackIsEnabled(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['APP_MANAGED_STORAGE_ROOT']
        );
        $_ENV['APP_ENABLE_LEGACY_MEDIA_FALLBACK'] = 'true';

        $projectRoot = dirname(__DIR__, 4);
        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $projectRoot . '/public/assets/img/member-photos/member_demo.png',
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath(
                'assets/img/member-photos/member_demo.png'
            )
        );
    }

    public function testFallsBackToProjectStorageDirectoryWhenManagedRootWasIntroducedLater(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX']
        );
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';

        $projectRoot = dirname(__DIR__, 4);
        $fileName = 'member_test_project_storage_' . bin2hex(random_bytes(4)) . '.jpg';
        $projectStoragePath = $projectRoot . '/var/storage/member-photos/' . $fileName;
        file_put_contents($projectStoragePath, 'project-storage-photo');
        $this->temporaryFiles[] = $projectStoragePath;

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $projectStoragePath,
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/' . $fileName)
        );
    }

    public function testUsesExistingAncestorManagedStorageRootWhenConfiguredWithBareRelativeName(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX']
        );

        $projectRoot = dirname(__DIR__, 4);
        $directoryName = '_cedern_storage_test_' . bin2hex(random_bytes(4));
        $sharedRoot = dirname($projectRoot) . '/' . $directoryName;
        mkdir($sharedRoot, 0775, true);
        $this->temporaryDirectories[] = $sharedRoot;

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = $directoryName;

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $sharedRoot . '/member-photos/member_demo.png',
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/member_demo.png')
        );
    }

    public function testKeepsExplicitProjectRelativeManagedStorageRootWhenConfiguredWithDotSlash(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX']
        );

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = './_cedern_storage_explicit';
        $projectRoot = dirname(__DIR__, 4);
        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $projectRoot . '/_cedern_storage_explicit/member-photos/member_demo.png',
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/member_demo.png')
        );
    }

    public function testManagedWriteModeDoesNotFallBackToLegacyDirectoryWhenPrimaryDestinationIsUnavailable(): void
    {
        $invalidPath = sys_get_temp_dir() . '/cedern-member-photo-invalid-' . bin2hex(random_bytes(4));
        file_put_contents($invalidPath, 'not-a-directory');
        $this->temporaryFiles[] = $invalidPath;

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';
        $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'] = $invalidPath;
        $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'] = 'media/membros/fotos';

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertNull($storage->exposedResolveWritableMemberProfilePhotoStorage());
    }

    public function testResolvesMemberPhotoFromManagedStorageRootWhenImportedOutsideCanonicalBucket(): void
    {
        unset(
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'],
            $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX']
        );

        $managedRoot = sys_get_temp_dir() . '/cedern-member-photo-root-' . bin2hex(random_bytes(4));
        $importDirectory = $managedRoot . '/imported-files';
        mkdir($importDirectory, 0775, true);
        $this->temporaryDirectories[] = $importDirectory;
        $this->temporaryDirectories[] = $managedRoot;

        $fileName = 'member_test_recursive_' . bin2hex(random_bytes(4)) . '.jpg';
        $filePath = $importDirectory . '/' . $fileName;
        file_put_contents($filePath, 'member-photo');
        $this->temporaryFiles[] = $filePath;

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = $managedRoot;

        $storage = new TestableMemberProfilePhotoStorageHarness();

        $this->assertSame(
            $filePath,
            $storage->exposedResolveManagedMemberProfilePhotoAbsolutePath('media/membros/fotos/' . $fileName)
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

    public function testDeletesStoredManagedMemberProfilePhotoFile(): void
    {
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';

        $projectRoot = dirname(__DIR__, 4);
        $fileName = 'member_test_delete_' . bin2hex(random_bytes(4)) . '.jpg';
        $projectStoragePath = $projectRoot . '/var/storage/member-photos/' . $fileName;
        file_put_contents($projectStoragePath, 'project-storage-photo');
        $this->temporaryFiles[] = $projectStoragePath;

        $storage = new TestableMemberProfilePhotoStorageHarness();
        $storage->exposedDeleteStoredMemberProfilePhotoIfManaged('media/membros/fotos/' . $fileName);

        $this->assertFileDoesNotExist($projectStoragePath);
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
            'APP_ENABLE_LEGACY_MEDIA_FALLBACK',
        ];
    }

    private function ensureDirectoryExists(string $directoryPath): void
    {
        if (is_dir($directoryPath)) {
            return;
        }

        mkdir($directoryPath, 0775, true);
        $this->temporaryDirectories[] = $directoryPath;
    }
}
