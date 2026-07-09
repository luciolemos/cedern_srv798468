<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use App\Application\Middleware\SessionMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Tests\TestCase;

final class SessionMiddlewareTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalCookie = [];

    private string $originalSessionSavePath = '';

    private string $temporarySessionDirectory = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->originalCookie = $_COOKIE;
        $this->originalSessionSavePath = (string) session_save_path();
        $this->temporarySessionDirectory = sys_get_temp_dir() . '/cedern-session-test-' . bin2hex(random_bytes(6));

        mkdir($this->temporarySessionDirectory, 0775, true);
        session_save_path($this->temporarySessionDirectory);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $_COOKIE = $this->originalCookie;
        session_id('');
        session_save_path($this->originalSessionSavePath);

        if (is_dir($this->temporarySessionDirectory)) {
            foreach (glob($this->temporarySessionDirectory . '/*') ?: [] as $filePath) {
                @chmod($filePath, 0664);
                @unlink($filePath);
            }

            @rmdir($this->temporarySessionDirectory);
        }

        parent::tearDown();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFallsBackToFreshSessionWhenExistingSessionFileCannotBeRead(): void
    {
        $brokenSessionId = 'broken_session_test';
        $brokenSessionPath = $this->temporarySessionDirectory . '/sess_' . $brokenSessionId;

        file_put_contents($brokenSessionPath, 'corrupted');
        chmod($brokenSessionPath, 0000);
        $_COOKIE[session_name()] = $brokenSessionId;

        $middleware = new SessionMiddleware();
        $request = $this->createRequest('GET', '/entrar');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertNotSame($brokenSessionId, session_id());
    }

    private function okHandler(): RequestHandler
    {
        return new class implements RequestHandler {
            public function handle(Request $request): Response
            {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write('ok');

                return $response;
            }
        };
    }
}
