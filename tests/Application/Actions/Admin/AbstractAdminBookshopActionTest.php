<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AbstractAdminBookshopAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Tests\TestCase;

final class TestableAbstractAdminBookshopAction extends AbstractAdminBookshopAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response;
    }

    public function exposedResolveBookshopCoverUploadDirectory(): string
    {
        return $this->resolveBookshopCoverUploadDirectory();
    }

    public function exposedResolveManagedBookshopCoverAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedBookshopCoverAbsolutePath($relativePath);
    }
}

final class AbstractAdminBookshopActionTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

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
        foreach ($this->managedEnvKeys() as $key) {
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

    public function testBookshopCoverUploadDirectoryUsesSharedManagedStorageRootWhenConfigured(): void
    {
        unset(
            $_ENV['BOOKSHOP_COVER_UPLOAD_DIR'],
            $_ENV['BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX']
        );
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';

        $action = $this->createAction();

        $this->assertSame(
            '/srv/cede-managed-storage/bookshop/covers',
            $action->exposedResolveBookshopCoverUploadDirectory()
        );
        $this->assertSame(
            '/srv/cede-managed-storage/bookshop/covers/cover_demo.jpg',
            $action->exposedResolveManagedBookshopCoverAbsolutePath('media/livraria/capas/cover_demo.jpg')
        );
    }

    public function testBookshopCoverUploadDirectoryRebasesRelativeConfiguredDirectoryIntoManagedRoot(): void
    {
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';
        $_ENV['BOOKSHOP_COVER_UPLOAD_DIR'] = './var/storage/bookshop/covers';

        $action = $this->createAction();

        $this->assertSame(
            '/srv/cede-managed-storage/bookshop/covers',
            $action->exposedResolveBookshopCoverUploadDirectory()
        );
        $this->assertSame(
            '/srv/cede-managed-storage/bookshop/covers/cover_demo.jpg',
            $action->exposedResolveManagedBookshopCoverAbsolutePath('media/livraria/capas/cover_demo.jpg')
        );
    }

    public function testBookshopCoverUploadDirectoryUsesExistingAncestorManagedStorageRootWhenConfiguredWithBareRelativeName(): void
    {
        unset(
            $_ENV['BOOKSHOP_COVER_UPLOAD_DIR'],
            $_ENV['BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX']
        );

        $projectRoot = dirname(__DIR__, 4);
        $directoryName = '_cedern_storage_test_' . bin2hex(random_bytes(4));
        $sharedRoot = dirname($projectRoot) . '/' . $directoryName;
        mkdir($sharedRoot, 0775, true);
        $this->temporaryDirectories[] = $sharedRoot;

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = $directoryName;

        $action = $this->createAction();

        $this->assertSame(
            $sharedRoot . '/bookshop/covers',
            $action->exposedResolveBookshopCoverUploadDirectory()
        );
        $this->assertSame(
            $sharedRoot . '/bookshop/covers/cover_demo.jpg',
            $action->exposedResolveManagedBookshopCoverAbsolutePath('media/livraria/capas/cover_demo.jpg')
        );
    }

    /**
     * @return list<string>
     */
    private function managedEnvKeys(): array
    {
        return [
            'BOOKSHOP_COVER_UPLOAD_DIR',
            'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
            'APP_MANAGED_STORAGE_ROOT',
        ];
    }

    private function createAction(): TestableAbstractAdminBookshopAction
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $bookshopRepository = $this->prophesize(BookshopRepository::class)->reveal();

        return new TestableAbstractAdminBookshopAction($logger, $twig, $bookshopRepository);
    }
}
