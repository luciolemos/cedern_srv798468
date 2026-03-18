<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

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
}
