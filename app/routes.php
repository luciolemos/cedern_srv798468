<?php

declare(strict_types=1);

use App\Application\Actions\Page\AboutPageAction;
use App\Application\Actions\Page\AboutMissionPageAction;
use App\Application\Actions\Page\AboutValuesPageAction;
use App\Application\Actions\Page\AboutHistoryPageAction;
use App\Application\Actions\Page\AboutBrandPageAction;
use App\Application\Actions\Admin\AdminDashboardPageAction;
use App\Application\Actions\Admin\AdminAgendaDeleteAction;
use App\Application\Actions\Admin\AdminAgendaFormPageAction;
use App\Application\Actions\Admin\AdminLoginPageAction;
use App\Application\Actions\Admin\AdminAgendaListPageAction;
use App\Application\Actions\Admin\AdminCategoryFormPageAction;
use App\Application\Actions\Admin\AdminCategoryListPageAction;
use App\Application\Actions\Admin\AdminCategoryToggleStatusAction;
use App\Application\Actions\Admin\AdminLogoutAction;
use App\Application\Actions\Page\AgendaDetailPageAction;
use App\Application\Actions\Page\AgendaPageAction;
use App\Application\Actions\Page\ContactPageAction;
use App\Application\Actions\Page\EsdePageAction;
use App\Application\Actions\Page\FaqPageAction;
use App\Application\Actions\Page\FaqDoctrinePageAction;
use App\Application\Actions\Page\FaqParticipationPageAction;
use App\Application\Actions\Page\FaqPracticesPageAction;
use App\Application\Actions\Page\FraternalServicePageAction;
use App\Application\Actions\Page\HomePageAction;
use App\Application\Actions\Page\PublicLecturesPageAction;
use App\Application\Actions\Page\StudiesPageAction;
use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Domain\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Slim\Views\Twig;

return function (App $app) {
    $normalizeEnvValue = static function (string $value): string {
        $normalized = trim($value);

        if (strlen($normalized) >= 2) {
            $first = $normalized[0];
            $last = $normalized[strlen($normalized) - 1];

            if (($first === '"' && $last === '"') || ($first === '\'' && $last === '\'')) {
                return substr($normalized, 1, -1);
            }
        }

        return $normalized;
    };

    $buildDashboardAuthToken = static function (string $username, string $password): string {
        $seed = trim((string) ($_ENV['APP_DEFAULT_SITE_NAME'] ?? 'CEDE'));

        return hash('sha256', $seed . '|' . $username . '|' . $password);
    };

    $adminSessionAuthMiddleware = function (Request $request, RequestHandler $handler) use ($app): Response {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (!empty($_SESSION['admin_authenticated'])) {
            return $handler->handle($request);
        }

        $expectedUserRaw = (string) (
            $_ENV['ADMIN_DASHBOARD_USER']
            ?? $_ENV['ADMIN_AGENDA_USER']
            ?? ''
        );
        $expectedPassRaw = (string) (
            $_ENV['ADMIN_DASHBOARD_PASS']
            ?? $_ENV['ADMIN_AGENDA_PASS']
            ?? ''
        );

        $expectedUser = $normalizeEnvValue($expectedUserRaw);
        $expectedPass = $normalizeEnvValue($expectedPassRaw);

        if ($expectedUser !== '' && $expectedPass !== '') {
            $cookieToken = (string) ($_COOKIE['dashboard_auth'] ?? '');
            $validToken = $buildDashboardAuthToken($expectedUser, $expectedPass);

            if ($cookieToken !== '' && hash_equals($validToken, $cookieToken)) {
                $_SESSION['admin_authenticated'] = true;
                $_SESSION['admin_user'] = $expectedUser;

                return $handler->handle($request);
            }
        }

        $response = $app->getResponseFactory()->createResponse(302);

        return $response->withHeader('Location', '/painel/login');
    };

    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', HomePageAction::class);
    $app->get('/quem-somos', AboutPageAction::class);
    $app->get('/quem-somos/missao', AboutMissionPageAction::class);
    $app->get('/quem-somos/valores', AboutValuesPageAction::class);
    $app->get('/quem-somos/historia', AboutHistoryPageAction::class);
    $app->get('/quem-somos/nossa-marca', AboutBrandPageAction::class);
    $app->get('/estudos', StudiesPageAction::class);
    $app->get('/estudos/esde', EsdePageAction::class);
    $app->get('/estudos/palestras', PublicLecturesPageAction::class);
    $app->get('/estudos/atendimento-fraterno', FraternalServicePageAction::class);
    $app->get('/agenda', AgendaPageAction::class);
    $app->get('/agenda/{slug}', AgendaDetailPageAction::class);
    $app->map(['GET', 'POST'], '/painel/login', AdminLoginPageAction::class);
    $app->get('/painel/logout', AdminLogoutAction::class);
    $app->group('/painel', function (Group $group) {
        $group->get('', AdminDashboardPageAction::class);
        $group->get('/eventos', AdminAgendaListPageAction::class);
        $group->map(['GET', 'POST'], '/eventos/novo', AdminAgendaFormPageAction::class);
        $group->map(['GET', 'POST'], '/eventos/{id}/editar', AdminAgendaFormPageAction::class);
        $group->post('/eventos/{id}/excluir', AdminAgendaDeleteAction::class);
        $group->get('/categorias', AdminCategoryListPageAction::class);
        $group->map(['GET', 'POST'], '/categorias/nova', AdminCategoryFormPageAction::class);
        $group->map(['GET', 'POST'], '/categorias/{id}/editar', AdminCategoryFormPageAction::class);
        $group->post('/categorias/{id}/alternar-status', AdminCategoryToggleStatusAction::class);
    })->add($adminSessionAuthMiddleware);

    $app->get('/admin', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/login', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/login')->withStatus(302);
    });
    $app->get('/admin/logout', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/logout')->withStatus(302);
    });
    $app->get('/admin/dashboard', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel')->withStatus(302);
    });
    $app->get('/admin/agenda', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/eventos')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/agenda/novo', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/eventos/novo')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/agenda/{id}/editar', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        return $response->withHeader('Location', '/painel/eventos/' . $id . '/editar')->withStatus(302);
    });
    $app->post('/admin/agenda/{id}/excluir', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        return $response->withHeader('Location', '/painel/eventos/' . $id . '/excluir')->withStatus(307);
    });
    $app->get('/admin/categorias', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/categorias')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/categorias/nova', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/categorias/nova')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/categorias/{id}/editar', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        return $response->withHeader('Location', '/painel/categorias/' . $id . '/editar')->withStatus(302);
    });
    $app->post('/admin/categorias/{id}/alternar-status', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        return $response->withHeader('Location', '/painel/categorias/' . $id . '/alternar-status')->withStatus(307);
    });
    $app->get('/faq', FaqPageAction::class);
    $app->get('/faq/doutrina', FaqDoctrinePageAction::class);
    $app->get('/faq/participacao', FaqParticipationPageAction::class);
    $app->get('/faq/praticas', FaqPracticesPageAction::class);
    $app->get('/contato', ContactPageAction::class);

    $app->get('/users', function (Request $request, Response $response) use ($app) {
        $twig = $app->getContainer()->get(Twig::class);
        $repository = $app->getContainer()->get(UserRepository::class);
        $users = array_map(
            static fn ($user): array => $user->jsonSerialize(),
            $repository->findAll()
        );

        return $twig->render($response, 'users.twig', ['users' => $users]);
    });

    $app->get('/health/render', function (Request $request, Response $response) use ($app) {
        $twigView = $app->getContainer()->get(Twig::class);
        $twig = $twigView->getEnvironment();
        $homeContent = require __DIR__ . '/content/home.php';

        $checks = [
            ['template' => 'components/header.twig', 'context' => []],
            ['template' => 'home/hero.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/features.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/social-proof.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/roadmap.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/faq.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/final-cta.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'components/theme-palette.twig', 'context' => []],
            ['template' => 'components/footer.twig', 'context' => []],
            ['template' => 'home.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/about.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/about-detail.twig', 'context' => ['homeContent' => $homeContent, 'about' => $homeContent['aboutPages']['missao'] ?? []]],
            ['template' => 'pages/about-brand.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/studies.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/study-detail.twig', 'context' => ['homeContent' => $homeContent, 'study' => $homeContent['studiesPages']['esde'] ?? []]],
            ['template' => 'pages/agenda.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/agenda-detail.twig', 'context' => ['homeContent' => $homeContent, 'agenda' => $homeContent['agendaPages']['estudo-do-evangelho'] ?? []]],
            ['template' => 'pages/faq.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/faq-category.twig', 'context' => ['homeContent' => $homeContent, 'faq_category_slug' => 'doutrina']],
            ['template' => 'pages/contact.twig', 'context' => ['homeContent' => $homeContent]],
        ];

        $results = [];

        foreach ($checks as $check) {
            $template = $check['template'];
            $context = $check['context'];

            try {
                $html = $twig->render($template, $context);
                $results[] = [
                    'template' => $template,
                    'ok' => true,
                    'length' => strlen($html),
                ];
            } catch (\Throwable $exception) {
                $results[] = [
                    'template' => $template,
                    'ok' => false,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $payload = [
            'status' => 'ok',
            'php' => PHP_VERSION,
            'results' => $results,
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/health/db', function (Request $request, Response $response) use ($app) {
        try {
            /** @var \PDO $pdo */
            $pdo = $app->getContainer()->get(\PDO::class);
            $row = $pdo->query('SELECT 1 AS ok')->fetch();
            $status = ((int) ($row['ok'] ?? 0) === 1) ? 'ok' : 'degraded';
            $code = ($status === 'ok') ? 200 : 503;

            $payload = [
                'status' => $status,
                'db' => 'connected',
            ];

            $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $response
                ->withStatus($code)
                ->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $exception) {
            $payload = [
                'status' => 'error',
                'db' => 'unavailable',
                'message' => $exception->getMessage(),
            ];

            $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $response
                ->withStatus(503)
                ->withHeader('Content-Type', 'application/json');
        }
    });

    $app->group('/api/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });
};
