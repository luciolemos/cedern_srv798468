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

class AbstractAdminLibraryActionTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

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

        parent::tearDown();
    }

    public function testLibraryUploadStorageDefaultsMatchCurrentConvention(): void
    {
        unset(
            $_ENV['LIBRARY_UPLOAD_DIR'],
            $_ENV['LIBRARY_UPLOAD_PUBLIC_PREFIX'],
            $_ENV['LIBRARY_COVER_UPLOAD_DIR'],
            $_ENV['LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX']
        );

        $action = $this->createAction();
        $projectRoot = dirname(__DIR__, 4);

        $this->assertSame(
            $projectRoot . '/public/assets/docs/library',
            $action->exposedResolveLibraryUploadDirectory()
        );
        $this->assertSame(
            'assets/docs/library',
            $action->exposedResolveLibraryUploadPublicPrefix()
        );
        $this->assertSame(
            $projectRoot . '/public/assets/docs/library/book_demo.pdf',
            $action->exposedResolveManagedLibraryPdfAbsolutePath('assets/docs/library/book_demo.pdf')
        );
        $this->assertSame(
            $projectRoot . '/public/assets/img/library-covers',
            $action->exposedResolveLibraryCoverUploadDirectory()
        );
        $this->assertSame(
            'assets/img/library-covers',
            $action->exposedResolveLibraryCoverUploadPublicPrefix()
        );
        $this->assertSame(
            $projectRoot . '/public/assets/img/library-covers/cover_demo.jpg',
            $action->exposedResolveManagedLibraryCoverAbsolutePath('assets/img/library-covers/cover_demo.jpg')
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
        $this->assertNull(
            $action->exposedResolveManagedLibraryPdfAbsolutePath('assets/docs/library/book_demo.pdf')
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
        $this->assertNull(
            $action->exposedResolveManagedLibraryCoverAbsolutePath('assets/img/library-covers/cover_demo.webp')
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
        ];
    }

    private function createAction(): AbstractAdminLibraryAction
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $libraryRepository = $this->prophesize(LibraryRepository::class)->reveal();

        return new class ($logger, $twig, $libraryRepository) extends AbstractAdminLibraryAction {
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
        };
    }
}
