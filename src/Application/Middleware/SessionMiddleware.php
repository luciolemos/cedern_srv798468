<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class SessionMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            $this->configureSessionCookie();
            $this->startSessionSafely();
        }

        $request = $request->withAttribute('session', $_SESSION ?? []);

        return $handler->handle($request);
    }

    private function configureSessionCookie(): void
    {
        ini_set('session.use_strict_mode', '1');

        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $this->isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function startSessionSafely(): void
    {
        if ($this->attemptSessionStart()) {
            return;
        }

        $this->resetBrokenSessionCookie();

        if (!$this->attemptSessionStart()) {
            $_SESSION = [];
        }
    }

    private function attemptSessionStart(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        return @session_start();
    }

    private function resetBrokenSessionCookie(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $sessionName = session_name();
        if ($sessionName !== false && isset($_COOKIE[$sessionName])) {
            unset($_COOKIE[$sessionName]);
        }

        session_id('');
        $_SESSION = [];

        if ($sessionName === false || headers_sent()) {
            return;
        }

        $params = session_get_cookie_params();
        setcookie($sessionName, '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $this->isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function isHttpsRequest(): bool
    {
        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

        return $forwardedProto === 'https'
            || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    }
}
