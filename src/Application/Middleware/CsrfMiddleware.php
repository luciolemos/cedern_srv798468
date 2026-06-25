<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class CsrfMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        CsrfToken::get();

        if (!$this->requiresValidation($request)) {
            return $handler->handle($request);
        }

        if (CsrfToken::isValid($this->extractSubmittedToken($request)) || $this->hasSameOriginHeader($request)) {
            return $handler->handle($request);
        }

        return $this->buildForbiddenResponse($request);
    }

    private function requiresValidation(Request $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function extractSubmittedToken(Request $request): string
    {
        $headerToken = trim($request->getHeaderLine('X-CSRF-Token'));
        if ($headerToken !== '') {
            return $headerToken;
        }

        $body = $request->getParsedBody();
        if (is_array($body)) {
            return trim((string) ($body[CsrfToken::FIELD_NAME] ?? ''));
        }

        return '';
    }

    private function hasSameOriginHeader(Request $request): bool
    {
        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin !== '') {
            return $this->matchesAllowedOrigin($origin, $request);
        }

        $referer = trim($request->getHeaderLine('Referer'));
        if ($referer !== '') {
            return $this->matchesAllowedOrigin($referer, $request);
        }

        return false;
    }

    private function matchesAllowedOrigin(string $url, Request $request): bool
    {
        $submittedOrigin = $this->normalizeOrigin($url);
        if ($submittedOrigin === '') {
            return false;
        }

        return in_array($submittedOrigin, $this->allowedOrigins($request), true);
    }

    /**
     * @return list<string>
     */
    private function allowedOrigins(Request $request): array
    {
        $origins = [];
        $requestHost = trim($request->getHeaderLine('Host'));
        if ($requestHost === '') {
            $requestHost = $request->getUri()->getHost();
        }

        if ($requestHost !== '') {
            $origin = $this->normalizeOrigin($this->requestScheme($request) . '://' . $requestHost);
            if ($origin !== '') {
                $origins[] = $origin;
            }
        }

        $defaultPageUrl = trim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? ''));
        if ($defaultPageUrl !== '') {
            $origin = $this->normalizeOrigin($defaultPageUrl);
            if ($origin !== '') {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    private function requestScheme(Request $request): string
    {
        $forwardedProto = strtolower(trim($request->getHeaderLine('X-Forwarded-Proto')));
        if ($forwardedProto !== '') {
            return explode(',', $forwardedProto)[0] === 'https' ? 'https' : 'http';
        }

        $serverParams = $request->getServerParams();
        if (!empty($serverParams['HTTPS']) && strtolower((string) $serverParams['HTTPS']) !== 'off') {
            return 'https';
        }

        $scheme = strtolower($request->getUri()->getScheme());

        return $scheme !== '' ? $scheme : 'http';
    }

    private function normalizeOrigin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $portSuffix = ($port !== null && $port !== $defaultPort) ? ':' . $port : '';

        return $scheme . '://' . $host . $portSuffix;
    }

    private function buildForbiddenResponse(Request $request): Response
    {
        $accept = strtolower($request->getHeaderLine('Accept'));
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        $wantsJson = str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');

        $response = new SlimResponse(403);
        if ($wantsJson) {
            $response->getBody()->write((string) json_encode([
                'status' => 'error',
                'message' => 'Sessão expirada. Recarregue a página e tente novamente.',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write('Sessão expirada. Recarregue a página e tente novamente.');

        return $response->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }
}
