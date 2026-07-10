<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AbstractAdminLibraryAction;
use App\Domain\Library\LibraryRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Tests\TestCase;

final class TestableAbstractAdminLibraryAction extends AbstractAdminLibraryAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response;
    }

    public function exposedResolveLibraryUploadDirectory(): string
    {
        return $this->resolveLibraryUploadDirectory();
    }

    public function exposedResolveLibraryUploadPublicPrefix(): string
    {
        return $this->resolveLibraryUploadPublicPrefix();
    }

    public function exposedBuildManagedLibraryPdfRelativePath(string $fileName): string
    {
        return $this->buildManagedLibraryPdfRelativePath($fileName);
    }

    public function exposedResolveManagedLibraryPdfAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedLibraryPdfAbsolutePath($relativePath);
    }

    public function exposedResolveLibraryCoverUploadDirectory(): string
    {
        return $this->resolveLibraryCoverUploadDirectory();
    }

    public function exposedResolveLibraryCoverUploadPublicPrefix(): string
    {
        return $this->resolveLibraryCoverUploadPublicPrefix();
    }

    public function exposedBuildManagedLibraryCoverRelativePath(string $fileName): string
    {
        return $this->buildManagedLibraryCoverRelativePath($fileName);
    }

    public function exposedResolveManagedLibraryCoverAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedLibraryCoverAbsolutePath($relativePath);
    }
}

class AbstractAdminLibraryActionTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->getManagedEnvKeys() as $key) {
            $this->originalEnv[$key] = array_key_exists($key, $_ENV) ? (string) $_ENV[$key] : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->getManagedEnvKeys() as $key) {
            $originalValue = $this->originalEnv[$key] ?? null;

            if ($originalValue === null) {
                unset($_ENV[$key]);
                continue;
            }

            $_ENV[$key] = $originalValue;
        }

        foreach ($this->temporaryDirectories as $directoryPath) {
            if (is_dir($directoryPath)) {
                @rmdir($directoryPath);
            }
        }

        parent::tearDown();
    }

    public function testLibraryUploadStorageDefaultsMatchCurrentConvention(): void
    {
        unset(
            $_ENV['LIBRARY_UPLOAD_DIR'],
            $_ENV['LIBRARY_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['LIBRARY_COVER_UPLOAD_DIR'],
            $_ENV['LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['APP_MANAGED_STORAGE_ROOT']
        );

        $action = $this->createAction();
        $projectRoot = dirname(__DIR__, 4);

        $this->assertSame(
            $projectRoot . '/var/storage/library/docs',
            $action->exposedResolveLibraryUploadDirectory()
        );
        $this->assertSame(
            'media/biblioteca/docs',
            $action->exposedResolveLibraryUploadPublicPrefix()
        );
        $this->assertSame(
            $projectRoot . '/var/storage/library/docs/book_demo.pdf',
            $action->exposedResolveManagedLibraryPdfAbsolutePath('media/biblioteca/docs/book_demo.pdf')
        );
        $this->assertSame(
            $projectRoot . '/var/storage/library/covers',
            $action->exposedResolveLibraryCoverUploadDirectory()
        );
        $this->assertSame(
            'media/biblioteca/capas',
            $action->exposedResolveLibraryCoverUploadPublicPrefix()
        );
        $this->assertSame(
            $projectRoot . '/var/storage/library/covers/cover_demo.jpg',
            $action->exposedResolveManagedLibraryCoverAbsolutePath('media/biblioteca/capas/cover_demo.jpg')
        );
    }

    public function testLibraryUploadStorageUsesConfiguredDirectoryAndPrefix(): void
    {
        $_ENV['LIBRARY_UPLOAD_DIR'] = '/srv/cede-storage/library-pdfs';
        $_ENV['LIBRARY_UPLOAD_PUBLIC_PREFIX'] = 'media/biblioteca';
        $_ENV['LIBRARY_COVER_UPLOAD_DIR'] = '/srv/cede-storage/library-covers';
        $_ENV['LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX'] = 'media/biblioteca/capas';

        $action = $this->createAction();

        $this->assertSame(
            '/srv/cede-storage/library-pdfs',
            $action->exposedResolveLibraryUploadDirectory()
        );
        $this->assertSame(
            'media/biblioteca',
            $action->exposedResolveLibraryUploadPublicPrefix()
        );
        $this->assertSame(
            'media/biblioteca/book_demo.pdf',
            $action->exposedBuildManagedLibraryPdfRelativePath('book_demo.pdf')
        );
        $this->assertSame(
            '/srv/cede-storage/library-pdfs/book_demo.pdf',
            $action->exposedResolveManagedLibraryPdfAbsolutePath('media/biblioteca/book_demo.pdf')
        );
        $this->assertSame(
            '/srv/cede-storage/library-covers',
            $action->exposedResolveLibraryCoverUploadDirectory()
        );
        $this->assertSame(
            'media/biblioteca/capas',
            $action->exposedResolveLibraryCoverUploadPublicPrefix()
        );
        $this->assertSame(
            'media/biblioteca/capas/cover_demo.webp',
            $action->exposedBuildManagedLibraryCoverRelativePath('cover_demo.webp')
        );
        $this->assertSame(
            '/srv/cede-storage/library-covers/cover_demo.webp',
            $action->exposedResolveManagedLibraryCoverAbsolutePath('media/biblioteca/capas/cover_demo.webp')
        );
        $this->assertNull($action->exposedResolveManagedLibraryPdfAbsolutePath('assets/docs/library/book_demo.pdf'));
        $this->assertNull($action->exposedResolveManagedLibraryCoverAbsolutePath('assets/img/library-covers/cover_demo.webp'));
    }

    public function testLibraryUploadStorageCanReadLegacyPathsWhenExplicitlyEnabled(): void
    {
        $_ENV['APP_ENABLE_LEGACY_MEDIA_FALLBACK'] = 'true';
        $_ENV['LIBRARY_UPLOAD_DIR'] = '/srv/cede-storage/library-pdfs';
        $_ENV['LIBRARY_UPLOAD_PUBLIC_PREFIX'] = 'media/biblioteca';
        $_ENV['LIBRARY_COVER_UPLOAD_DIR'] = '/srv/cede-storage/library-covers';
        $_ENV['LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX'] = 'media/biblioteca/capas';

        $action = $this->createAction();
        $projectRoot = dirname(__DIR__, 4);

        $this->assertSame(
            $projectRoot . '/public/assets/docs/library/book_demo.pdf',
            $action->exposedResolveManagedLibraryPdfAbsolutePath('assets/docs/library/book_demo.pdf')
        );
        $this->assertSame(
            $projectRoot . '/public/assets/img/library-covers/cover_demo.webp',
            $action->exposedResolveManagedLibraryCoverAbsolutePath('assets/img/library-covers/cover_demo.webp')
        );
    }

    public function testLibraryUploadStorageUsesSharedManagedStorageRootWhenConfigured(): void
    {
        unset(
            $_ENV['LIBRARY_UPLOAD_DIR'],
            $_ENV['LIBRARY_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['LIBRARY_COVER_UPLOAD_DIR'],
            $_ENV['LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX']
        );
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';

        $action = $this->createAction();

        $this->assertSame(
            '/srv/cede-managed-storage/library/docs',
            $action->exposedResolveLibraryUploadDirectory()
        );
        $this->assertSame(
            '/srv/cede-managed-storage/library/covers',
            $action->exposedResolveLibraryCoverUploadDirectory()
        );
        $this->assertSame(
            '/srv/cede-managed-storage/library/docs/book_demo.pdf',
            $action->exposedResolveManagedLibraryPdfAbsolutePath('media/biblioteca/docs/book_demo.pdf')
        );
        $this->assertSame(
            '/srv/cede-managed-storage/library/covers/cover_demo.jpg',
            $action->exposedResolveManagedLibraryCoverAbsolutePath('media/biblioteca/capas/cover_demo.jpg')
        );
    }

    public function testLibraryUploadStorageRebasesRelativeConfiguredDirectoriesIntoManagedRoot(): void
    {
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';
        $_ENV['LIBRARY_UPLOAD_DIR'] = './var/storage/library/docs';
        $_ENV['LIBRARY_COVER_UPLOAD_DIR'] = 'var/storage/library/covers';

        $action = $this->createAction();

        $this->assertSame(
            '/srv/cede-managed-storage/library/docs',
            $action->exposedResolveLibraryUploadDirectory()
        );
        $this->assertSame(
            '/srv/cede-managed-storage/library/covers',
            $action->exposedResolveLibraryCoverUploadDirectory()
        );
    }

    public function testLibraryUploadStorageUsesExistingAncestorManagedStorageRootWhenConfiguredWithBareRelativeName(): void
    {
        unset(
            $_ENV['LIBRARY_UPLOAD_DIR'],
            $_ENV['LIBRARY_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['LIBRARY_COVER_UPLOAD_DIR'],
            $_ENV['LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX']
        );

        $projectRoot = dirname(__DIR__, 4);
        $directoryName = '_cedern_storage_test_' . bin2hex(random_bytes(4));
        $sharedRoot = dirname($projectRoot) . '/' . $directoryName;
        mkdir($sharedRoot, 0775, true);
        $this->temporaryDirectories[] = $sharedRoot;

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = $directoryName;

        $action = $this->createAction();

        $this->assertSame(
            $sharedRoot . '/library/docs',
            $action->exposedResolveLibraryUploadDirectory()
        );
        $this->assertSame(
            $sharedRoot . '/library/covers',
            $action->exposedResolveLibraryCoverUploadDirectory()
        );
    }

    /**
     * @return list<string>
     */
    private function getManagedEnvKeys(): array
    {
        return [
            'LIBRARY_UPLOAD_DIR',
            'LIBRARY_UPLOAD_PUBLIC_PREFIX',
            'LIBRARY_COVER_UPLOAD_DIR',
            'LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX',
            'APP_MANAGED_STORAGE_ROOT',
            'APP_ENABLE_LEGACY_MEDIA_FALLBACK',
        ];
    }

    private function createAction(): TestableAbstractAdminLibraryAction
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $libraryRepository = $this->prophesize(LibraryRepository::class)->reveal();

        return new TestableAbstractAdminLibraryAction($logger, $twig, $libraryRepository);
    }
}
