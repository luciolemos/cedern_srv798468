<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AbstractAdminPatrimonyAction;
use App\Domain\Patrimony\PatrimonyRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\NullLogger;
use Slim\Views\Twig;

final class TestableAdminPatrimonyAction extends AbstractAdminPatrimonyAction
{
    public function __construct(PatrimonyRepository $patrimonyRepository)
    {
        parent::__construct(new NullLogger(), Twig::create(sys_get_temp_dir()), $patrimonyRepository);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        return $response;
    }

    public function exposedResolveManagedPatrimonyAbsolutePath(?string $relativePath): ?string
    {
        return $this->resolveManagedPatrimonyAbsolutePath($relativePath);
    }
}

final class AbstractAdminPatrimonyActionTest extends TestCase
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

    public function testPrefersManagedStoragePathWhenManagedRootIsActive(): void
    {
        unset(
            $_ENV['PATRIMONY_IMAGE_UPLOAD_DIR'],
            $_ENV['PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX']
        );
        $_ENV['APP_MANAGED_STORAGE_ROOT'] = '/srv/cede-managed-storage';

        $projectRoot = dirname(__DIR__, 4);
        $fileName = 'asset-photo-test-project-storage-' . bin2hex(random_bytes(4)) . '.jpg';
        $projectStoragePath = $projectRoot . '/var/storage/patrimony/img/' . $fileName;
        file_put_contents($projectStoragePath, 'project-storage-patrimony-image');
        $this->temporaryFiles[] = $projectStoragePath;

        $repository = $this->createMock(PatrimonyRepository::class);
        $action = new TestableAdminPatrimonyAction($repository);

        $this->assertSame(
            '/srv/cede-managed-storage/patrimony/img/' . $fileName,
            $action->exposedResolveManagedPatrimonyAbsolutePath('media/patrimonio/img/' . $fileName)
        );
    }

    public function testDoesNotResolveLegacyPatrimonyImagePathByDefault(): void
    {
        $repository = $this->createMock(PatrimonyRepository::class);
        $action = new TestableAdminPatrimonyAction($repository);

        $this->assertNull(
            $action->exposedResolveManagedPatrimonyAbsolutePath('assets/img/patrimony/demo.webp')
        );
    }

    public function testResolvesLegacyPatrimonyImagePathWhenLegacyFallbackIsEnabled(): void
    {
        $_ENV['APP_ENABLE_LEGACY_MEDIA_FALLBACK'] = 'true';

        $repository = $this->createMock(PatrimonyRepository::class);
        $action = new TestableAdminPatrimonyAction($repository);
        $projectRoot = dirname(__DIR__, 4);

        $this->assertSame(
            $projectRoot . '/public/assets/img/patrimony/demo.webp',
            $action->exposedResolveManagedPatrimonyAbsolutePath('assets/img/patrimony/demo.webp')
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
            'PATRIMONY_IMAGE_UPLOAD_DIR',
            'PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX',
        ];
    }
}
