<?php

declare(strict_types=1);

use App\Application\Actions\Page\AboutPageAction;
use App\Application\Actions\Page\AboutMissionPageAction;
use App\Application\Actions\Page\AboutValuesPageAction;
use App\Application\Actions\Page\AboutHistoryPageAction;
use App\Application\Actions\Page\AboutBrandPageAction;
use App\Application\Actions\Page\AboutManagementPageAction;
use App\Application\Actions\Admin\AdminDashboardPageAction;
use App\Application\Actions\Admin\AdminAgendaDeleteAction;
use App\Application\Actions\Admin\AdminAgendaFormPageAction;
use App\Application\Actions\Admin\AdminLoginPageAction;
use App\Application\Actions\Admin\AdminAgendaListPageAction;
use App\Application\Actions\Admin\AdminCategoryFormPageAction;
use App\Application\Actions\Admin\AdminCategoryListPageAction;
use App\Application\Actions\Admin\AdminCategoryToggleStatusAction;
use App\Application\Actions\Admin\AdminMemberAssignRoleAction;
use App\Application\Actions\Admin\AdminMemberUsersPageAction;
use App\Application\Actions\Admin\AdminMemberUserSummaryPageAction;
use App\Application\Actions\Admin\AdminCedeManagementPageAction;
use App\Application\Actions\Admin\AdminPracticalGuidePageAction;
use App\Application\Actions\Admin\AdminUserGuidePageAction;
use App\Application\Actions\Admin\AdminLogoutAction;
use App\Application\Actions\Page\AgendaDetailPageAction;
use App\Application\Actions\Page\AgendaEventIcsDownloadAction;
use App\Application\Actions\Page\AgendaPageAction;
use App\Application\Actions\Page\ContactPageAction;
use App\Application\Actions\Page\EsdePageAction;
use App\Application\Actions\Page\FaqPageAction;
use App\Application\Actions\Page\FaqDoctrinePageAction;
use App\Application\Actions\Page\FaqParticipationPageAction;
use App\Application\Actions\Page\FaqPracticesPageAction;
use App\Application\Actions\Page\FraternalServicePageAction;
use App\Application\Actions\Page\HomePageAction;
use App\Application\Actions\Page\MemberCompleteProfilePageAction;
use App\Application\Actions\Page\MemberEventInterestToggleAction;
use App\Application\Actions\Page\MemberAdminAreaPageAction;
use App\Application\Actions\Page\MemberHomePageAction;
use App\Application\Actions\Page\MemberLoginPageAction;
use App\Application\Actions\Page\MemberManagerAreaPageAction;
use App\Application\Actions\Page\MemberOperatorAreaPageAction;
use App\Application\Actions\Page\MemberLogoutAction;
use App\Application\Actions\Page\MemberRegisterPageAction;
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
    $roleWeights = [
        'member' => 10,
        'operator' => 20,
        'manager' => 30,
        'admin' => 40,
    ];

    $memberHasMinimumRole = static function (string $requiredRoleKey) use ($roleWeights): bool {
        $memberRoleKey = trim((string) ($_SESSION['member_role_key'] ?? 'member'));
        $memberWeight = (int) ($roleWeights[$memberRoleKey] ?? 0);
        $requiredWeight = (int) ($roleWeights[$requiredRoleKey] ?? PHP_INT_MAX);

        return !empty($_SESSION['member_authenticated']) && $memberWeight >= $requiredWeight;
    };

    $adminSessionAuthMiddleware = function (Request $request, RequestHandler $handler) use ($app, $memberHasMinimumRole): Response {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if ($memberHasMinimumRole('operator')) {
            return $handler->handle($request);
        }

        $response = $app->getResponseFactory()->createResponse(302);

        return $response->withHeader('Location', '/entrar');
    };

    $panelRoleMiddlewareFactory = static function (string $requiredRoleKey) use ($app, $memberHasMinimumRole): callable {
        return function (Request $request, RequestHandler $handler) use ($app, $memberHasMinimumRole, $requiredRoleKey): Response {
            if ($memberHasMinimumRole($requiredRoleKey)) {
                return $handler->handle($request);
            }

            $response = $app->getResponseFactory()->createResponse(302);

            if (!empty($_SESSION['member_authenticated'])) {
                return $response->withHeader('Location', '/membro?status=forbidden');
            }

            return $response->withHeader('Location', '/entrar');
        };
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
    $app->get('/quem-somos/gestao-cede', AboutManagementPageAction::class);
    $app->get('/estudos', StudiesPageAction::class);
    $app->get('/estudos/esde', EsdePageAction::class);
    $app->get('/estudos/palestras', PublicLecturesPageAction::class);
    $app->get('/estudos/atendimento-fraterno', FraternalServicePageAction::class);
    $app->get('/agenda', AgendaPageAction::class);
    $app->get('/agenda/{slug}', AgendaDetailPageAction::class);
    $app->get('/agenda/{slug}/ics', AgendaEventIcsDownloadAction::class);
    $app->map(['GET', 'POST'], '/cadastro', MemberRegisterPageAction::class);
    $app->map(['GET', 'POST'], '/entrar', MemberLoginPageAction::class);
    $app->map(['GET', 'POST'], '/membro/sair', MemberLogoutAction::class);
    $app->get('/membro', MemberHomePageAction::class);
    $app->map(['GET', 'POST'], '/membro/perfil/completar', MemberCompleteProfilePageAction::class);
    $app->post('/membro/eventos/{id}/participacao', MemberEventInterestToggleAction::class);
    $app->get('/membro/operacao', MemberOperatorAreaPageAction::class);
    $app->get('/membro/gestao', MemberManagerAreaPageAction::class);
    $app->get('/membro/administracao', MemberAdminAreaPageAction::class);
    $app->map(['GET', 'POST'], '/painel/login', AdminLoginPageAction::class);
    $app->get('/painel/logout', AdminLogoutAction::class);
    $app->group('/painel', function (Group $group) use ($panelRoleMiddlewareFactory) {
        $group->get('', AdminDashboardPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->get('/eventos', AdminAgendaListPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->map(['GET', 'POST'], '/eventos/novo', AdminAgendaFormPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->map(['GET', 'POST'], '/eventos/{id}/editar', AdminAgendaFormPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->post('/eventos/{id}/excluir', AdminAgendaDeleteAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->get('/categorias', AdminCategoryListPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->map(['GET', 'POST'], '/categorias/nova', AdminCategoryFormPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->map(['GET', 'POST'], '/categorias/{id}/editar', AdminCategoryFormPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->post('/categorias/{id}/alternar-status', AdminCategoryToggleStatusAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->get('/usuarios', AdminMemberUsersPageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->get('/gestao-cede', AdminCedeManagementPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->get('/usuarios/{id}/resumo', AdminMemberUserSummaryPageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->post('/usuarios/{id}/atribuir-papel', AdminMemberAssignRoleAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->get('/guia-do-usuario', AdminUserGuidePageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->get('/guia-pratico', AdminPracticalGuidePageAction::class)->add($panelRoleMiddlewareFactory('admin'));
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
    $app->get('/admin/usuarios', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/usuarios')->withStatus(302);
    });
    $app->get('/admin/gestao-cede', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/gestao-cede')->withStatus(302);
    });
    $app->get('/admin/usuarios/{id}/resumo', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        return $response->withHeader('Location', '/painel/usuarios/' . $id . '/resumo')->withStatus(302);
    });
    $app->post('/admin/usuarios/{id}/atribuir-papel', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        return $response->withHeader('Location', '/painel/usuarios/' . $id . '/atribuir-papel')->withStatus(307);
    });
    $app->get('/admin/guia', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/guia-do-usuario')->withStatus(302);
    });
    $app->get('/admin/guia-pratico', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/guia-pratico')->withStatus(302);
    });
    $app->get('/faq', FaqPageAction::class);
    $app->get('/faq/doutrina', FaqDoctrinePageAction::class);
    $app->get('/faq/participacao', FaqParticipationPageAction::class);
    $app->get('/faq/praticas', FaqPracticesPageAction::class);
    $app->map(['GET', 'POST'], '/contato', ContactPageAction::class);

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
