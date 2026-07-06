<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Application\Security\RecaptchaVerifier;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractPageAction
{
    protected LoggerInterface $logger;

    protected Twig $twig;

    public function __construct(LoggerInterface $logger, Twig $twig)
    {
        $this->logger = $logger;
        $this->twig = $twig;
    }

    abstract public function __invoke(Request $request, Response $response): Response;

    protected function renderPage(Response $response, string $template, array $data = []): Response
    {
        $baseUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'), '/');
        $defaultPageImage =
            'https://cedern.org/assets/img/cedern/cede1_1600_1000.png';
        $defaultPageDescription =
            'Centro de Estudos da Doutrina Espírita (CEDE): instituição filantrópica '
            . 'dedicada ao estudo, à prática e à divulgação da Doutrina Espírita.';

        $context = array_merge([
            'homeContent' => require __DIR__ . '/../../../../app/content/home.php',
            'site_name' => trim((string) ($_ENV['APP_DEFAULT_SITE_NAME'] ?? 'CEDE')),
            'page_image' => trim((string) ($_ENV['APP_DEFAULT_PAGE_IMAGE'] ?? $defaultPageImage)),
            'page_description' => trim((string) (
                $_ENV['APP_DEFAULT_PAGE_DESCRIPTION'] ?? $defaultPageDescription
            )),
            'page_url_base' => $baseUrl,
        ], $data);

        return $this->twig->render($response, $template, $context);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function storeSessionFlash(string $key, array $payload): void
    {
        $this->ensureSessionStarted();

        if (!isset($_SESSION['_codex_flash']) || !is_array($_SESSION['_codex_flash'])) {
            $_SESSION['_codex_flash'] = [];
        }

        $_SESSION['_codex_flash'][$key] = $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function consumeSessionFlash(string $key): array
    {
        $this->ensureSessionStarted();

        $flashBag = $_SESSION['_codex_flash'] ?? [];
        if (!is_array($flashBag)) {
            return [];
        }

        $payload = $flashBag[$key] ?? [];
        unset($_SESSION['_codex_flash'][$key]);

        return is_array($payload) ? $payload : [];
    }

    protected function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * @return array{ok: bool, message: string, score: float|null, error_codes: list<string>}
     */
    protected function verifyRecaptchaToken(
        Request $request,
        RecaptchaVerifier $recaptchaVerifier,
        string $token,
        string $expectedAction
    ): array {
        return $recaptchaVerifier->verifySubmission(
            $token,
            $expectedAction,
            strtolower(trim($request->getUri()->getHost())),
            $this->resolveClientIp($request)
        );
    }

    protected function resolveClientIp(Request $request): ?string
    {
        $forwardedFor = trim($request->getHeaderLine('X-Forwarded-For'));
        if ($forwardedFor !== '') {
            $candidates = array_map('trim', explode(',', $forwardedFor));
            foreach ($candidates as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        $remoteAddress = trim((string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''));

        return filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false
            ? $remoteAddress
            : null;
    }

    protected function normalizeBasePath(string $rawBasePath): string
    {
        $trimmed = trim($rawBasePath);

        if ($trimmed === '' || $trimmed === '/') {
            return '';
        }

        return '/' . trim($trimmed, '/');
    }

    protected function resolveAppBasePath(Request $request): string
    {
        $appBaseEnv = getenv('APP_BASE');
        $appBaseRaw = trim((string) ($appBaseEnv !== false ? $appBaseEnv : ($_ENV['APP_BASE'] ?? '')));
        $configuredAppBasePath = $this->normalizeBasePath($appBaseRaw);
        $requestUriPath = trim($request->getUri()->getPath());

        if ($requestUriPath === '') {
            $requestUriPath = '/';
        }

        if (
            $configuredAppBasePath === ''
            || $requestUriPath === $configuredAppBasePath
            || str_starts_with($requestUriPath, $configuredAppBasePath . '/')
        ) {
            return $configuredAppBasePath;
        }

        return '';
    }

    protected function buildAppPath(Request $request, string $path): string
    {
        $normalizedPath = '/' . ltrim(trim($path), '/');

        return $this->resolveAppBasePath($request) . $normalizedPath;
    }

    protected function buildAbsoluteAppUrl(Request $request, string $path): string
    {
        $origin = $this->resolveRequestOrigin($request);

        return rtrim($origin, '/') . $this->buildAppPath($request, $path);
    }

    private function resolveRequestOrigin(Request $request): string
    {
        $configuredDefaultUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'), '/');
        $configuredDefaultOrigin = $this->resolveConfiguredDefaultOrigin($configuredDefaultUrl);
        $forwardedProto = $this->firstHeaderValue($request->getHeaderLine('X-Forwarded-Proto'));
        $forwardedHost = $this->firstHeaderValue($request->getHeaderLine('X-Forwarded-Host'));
        $scheme = strtolower($forwardedProto);

        if ($scheme === '') {
            $scheme = strtolower($request->getUri()->getScheme());
        }

        if ($scheme === '') {
            $httpsServerParam = strtolower(trim((string) ($request->getServerParams()['HTTPS'] ?? '')));
            $scheme = $httpsServerParam !== '' && $httpsServerParam !== 'off' ? 'https' : '';
        }

        if ($scheme === '') {
            $scheme = strtolower(parse_url($configuredDefaultUrl, PHP_URL_SCHEME) ?: 'https');
        }

        $host = $forwardedHost !== '' ? $forwardedHost : trim($request->getUri()->getHost());
        if ($host === '') {
            return $configuredDefaultOrigin;
        }

        $origin = $scheme . '://' . $host;
        $port = $request->getUri()->getPort();

        if (
            $forwardedHost === ''
            && $port !== null
            && !str_contains($host, ':')
            && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
        ) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    private function resolveConfiguredDefaultOrigin(string $configuredDefaultUrl): string
    {
        $scheme = strtolower((string) parse_url($configuredDefaultUrl, PHP_URL_SCHEME));
        $host = trim((string) parse_url($configuredDefaultUrl, PHP_URL_HOST));
        $port = parse_url($configuredDefaultUrl, PHP_URL_PORT);

        if ($scheme === '' || $host === '') {
            return 'https://cedern.org';
        }

        $origin = $scheme . '://' . $host;

        if (
            is_int($port)
            && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
        ) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    private function firstHeaderValue(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $parts = array_map('trim', explode(',', $normalized));

        return trim((string) ($parts[0] ?? ''));
    }
}
