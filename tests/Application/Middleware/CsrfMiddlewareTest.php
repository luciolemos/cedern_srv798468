<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use App\Application\Middleware\CsrfMiddleware;
use App\Application\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Tests\TestCase;

class CsrfMiddlewareTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalSession = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSession = $_SESSION ?? [];
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;

        parent::tearDown();
    }

    public function testGetRequestPassesWithoutSubmittedToken(): void
    {
        $middleware = new CsrfMiddleware();
        $request = $this->createRequest('GET', '/contato');

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
    }

    public function testPostRequestWithoutSubmittedTokenIsRejected(): void
    {
        $middleware = new CsrfMiddleware();
        $request = $this->createRequest('POST', '/contato')->withParsedBody([]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Sessão expirada', (string) $response->getBody());
    }

    public function testPostRequestWithValidTokenPasses(): void
    {
        $middleware = new CsrfMiddleware();
        $token = CsrfToken::get();
        $request = $this->createRequest('POST', '/contato')->withParsedBody([
            CsrfToken::FIELD_NAME => $token,
        ]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
    }

    public function testPostRequestWithoutTokenPassesWithSameOriginHeader(): void
    {
        $middleware = new CsrfMiddleware();
        $request = $this->createRequest(
            'POST',
            '/entrar',
            ['Host' => 'cedern.org', 'Origin' => 'https://cedern.org'],
            [],
            ['HTTPS' => 'on']
        )->withParsedBody([]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
    }

    public function testPostRequestWithoutTokenRejectsExternalOriginHeader(): void
    {
        $middleware = new CsrfMiddleware();
        $request = $this->createRequest(
            'POST',
            '/entrar',
            ['Host' => 'cedern.org', 'Origin' => 'https://example.org'],
            [],
            ['HTTPS' => 'on']
        )->withParsedBody([]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(403, $response->getStatusCode());
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
