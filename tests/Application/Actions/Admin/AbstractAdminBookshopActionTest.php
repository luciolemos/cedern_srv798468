<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AbstractAdminBookshopAction;
use App\Domain\Bookshop\BookshopRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\NullLogger;
use Slim\Views\Twig;

final class TestableAdminBookshopAction extends AbstractAdminBookshopAction
{
    public function __construct(BookshopRepository $bookshopRepository)
    {
        parent::__construct(new NullLogger(), Twig::create(sys_get_temp_dir()), $bookshopRepository);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        return $response;
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

    public function testFallsBackToProjectStorageDirectoryWhenManagedRootWasIntroducedLater(): void
    {
        unset(
            $_ENV['BOOKSHOP_COVER_UPLOAD_DIR'],
            $_ENV['BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX']
        );
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';

        $projectRoot = dirname(__DIR__, 4);
        $fileName = 'cover_test_project_storage_' . bin2hex(random_bytes(4)) . '.jpg';
        $projectStoragePath = $projectRoot . '/var/storage/bookshop/covers/' . $fileName;
        file_put_contents($projectStoragePath, 'project-storage-cover');
        $this->temporaryFiles[] = $projectStoragePath;

        $repository = $this->createMock(BookshopRepository::class);
        $action = new TestableAdminBookshopAction($repository);

        $this->assertSame(
            $projectStoragePath,
            $action->exposedResolveManagedBookshopCoverAbsolutePath('media/livraria/capas/' . $fileName)
        );
    }

    /**
     * @return list<string>
     */
    private function managedEnvKeys(): array
    {
        return [
            'APP_MANAGED_STORAGE_ROOT',
            'APP_ENABLE_LEGACY_MEDIA_FALLBACK',
            'BOOKSHOP_COVER_UPLOAD_DIR',
            'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
        ];
    }
}
