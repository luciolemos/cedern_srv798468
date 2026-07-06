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
