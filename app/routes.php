<?php

declare(strict_types=1);

use App\Application\Actions\Page\AboutPageAction;
use App\Application\Actions\Page\AboutMissionPageAction;
use App\Application\Actions\Page\AboutValuesPageAction;
use App\Application\Actions\Page\AboutHistoryPageAction;
use App\Application\Actions\Page\AboutFounderPageAction;
use App\Application\Actions\Page\AboutStatutePageAction;
use App\Application\Actions\Page\AboutBrandPageAction;
use App\Application\Actions\Page\AboutManagementPageAction;
use App\Application\Actions\Admin\AdminDashboardPageAction;
use App\Application\Actions\Admin\AdminAgendaDeleteAction;
use App\Application\Actions\Admin\AdminAgendaFormPageAction;
use App\Application\Actions\Admin\AdminLoginPageAction;
use App\Application\Actions\Admin\AdminAgendaListPageAction;
use App\Application\Actions\Admin\AdminBookshopBookDeleteAction;
use App\Application\Actions\Admin\AdminBookshopBookFormPageAction;
use App\Application\Actions\Admin\AdminBookshopBookListPageAction;
use App\Application\Actions\Admin\AdminBookshopCollectionFormPageAction;
use App\Application\Actions\Admin\AdminBookshopCollectionListPageAction;
use App\Application\Actions\Admin\AdminBookshopCollectionToggleStatusAction;
use App\Application\Actions\Admin\AdminBookshopCategoryFormPageAction;
use App\Application\Actions\Admin\AdminBookshopCategoryListPageAction;
use App\Application\Actions\Admin\AdminBookshopCategoryToggleStatusAction;
use App\Application\Actions\Admin\AdminBookshopDashboardPageAction;
use App\Application\Actions\Admin\AdminBookshopGenreFormPageAction;
use App\Application\Actions\Admin\AdminBookshopGenreListPageAction;
use App\Application\Actions\Admin\AdminBookshopGenreToggleStatusAction;
use App\Application\Actions\Admin\AdminBookshopImportPageAction;
use App\Application\Actions\Admin\AdminBookshopManualPageAction;
use App\Application\Actions\Admin\AdminBookshopReportsPageAction;
use App\Application\Actions\Admin\AdminBookshopSaleCancelAction;
use App\Application\Actions\Admin\AdminBookshopSaleFormPageAction;
use App\Application\Actions\Admin\AdminBookshopSaleListPageAction;
use App\Application\Actions\Admin\AdminBookshopSaleViewPageAction;
use App\Application\Actions\Admin\AdminBookshopStockMovementFormPageAction;
use App\Application\Actions\Admin\AdminBookshopStockMovementListPageAction;
use App\Application\Actions\Admin\AdminCategoryFormPageAction;
use App\Application\Actions\Admin\AdminCategoryListPageAction;
use App\Application\Actions\Admin\AdminCategoryToggleStatusAction;
use App\Application\Actions\Admin\AdminLibraryBookDeleteAction;
use App\Application\Actions\Admin\AdminLibraryBookFormPageAction;
use App\Application\Actions\Admin\AdminLibraryBookListPageAction;
use App\Application\Actions\Admin\AdminLibraryCategoryFormPageAction;
use App\Application\Actions\Admin\AdminLibraryCategoryListPageAction;
use App\Application\Actions\Admin\AdminLibraryCategoryToggleStatusAction;
use App\Application\Actions\Admin\AdminMemberAssignRoleAction;
use App\Application\Actions\Admin\AdminMemberUsersPageAction;
use App\Application\Actions\Admin\AdminMemberUserSummaryPageAction;
use App\Application\Actions\Admin\AdminCedeManagementPageAction;
use App\Application\Actions\Admin\AdminPrivacyPolicyPageAction;
use App\Application\Actions\Admin\AdminPracticalGuidePageAction;
use App\Application\Actions\Admin\AdminStatutePageAction;
use App\Application\Actions\Admin\AdminTermsOfUsePageAction;
use App\Application\Actions\Admin\AdminUserGuidePageAction;
use App\Application\Actions\Admin\AdminVisitCounterResetAction;
use App\Application\Actions\Admin\AdminLogoutAction;
use App\Application\Actions\Page\AgendaDetailPageAction;
use App\Application\Actions\Page\AgendaEventIcsDownloadAction;
use App\Application\Actions\Page\AgendaPageAction;
use App\Application\Actions\Page\BookshopCoverImagePageAction;
use App\Application\Actions\Page\ContactPageAction;
use App\Application\Actions\Page\EsdePageAction;
use App\Application\Actions\Page\FaqPageAction;
use App\Application\Actions\Page\FaqDoctrinePageAction;
use App\Application\Actions\Page\FaqParticipationPageAction;
use App\Application\Actions\Page\FaqPracticesPageAction;
use App\Application\Actions\Page\FraternalServicePageAction;
use App\Application\Actions\Page\HomePageAction;
use App\Application\Actions\Page\LibraryPageAction;
use App\Application\Actions\Page\StoreBazaarPageAction;
use App\Application\Actions\Page\StoreBookshopIiPageAction;
use App\Application\Actions\Page\StoreBookshopPageAction;
use App\Application\Actions\Page\StorePageAction;
use App\Application\Actions\Page\MemberCompleteProfilePageAction;
use App\Application\Actions\Page\MemberEventInterestToggleAction;
use App\Application\Actions\Page\MemberAdminAreaPageAction;
use App\Application\Actions\Page\MemberForgotPasswordPageAction;
use App\Application\Actions\Page\MemberHomePageAction;
use App\Application\Actions\Page\MemberLoginPageAction;
use App\Application\Actions\Page\MemberManagerAreaPageAction;
use App\Application\Actions\Page\MemberOperatorAreaPageAction;
use App\Application\Actions\Page\MemberLogoutAction;
use App\Application\Actions\Page\MemberRegisterPageAction;
use App\Application\Actions\Page\MemberResetPasswordPageAction;
use App\Application\Actions\Page\PrivacyPolicyPageAction;
use App\Application\Actions\Page\PublicLecturesPageAction;
use App\Application\Actions\Page\StudiesPageAction;
use App\Application\Actions\Page\TermsOfUsePageAction;
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

    $memberHasAnyRole = static function (array $allowedRoleKeys): bool {
        if (empty($_SESSION['member_authenticated'])) {
            return false;
        }

        $memberRoleKey = trim((string) ($_SESSION['member_role_key'] ?? 'member'));

        return in_array($memberRoleKey, $allowedRoleKeys, true);
    };

    $memberHasPanelAccess = static function () use ($memberHasAnyRole, $memberHasMinimumRole): bool {
        return $memberHasMinimumRole('operator') || $memberHasAnyRole(['bookshop_operator']);
    };

    $adminSessionAuthMiddleware = function (Request $request, RequestHandler $handler) use ($app, $memberHasPanelAccess): Response {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if ($memberHasPanelAccess()) {
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

    $panelDashboardAccessMiddleware = function (Request $request, RequestHandler $handler) use ($app, $memberHasPanelAccess): Response {
        if ($memberHasPanelAccess()) {
            return $handler->handle($request);
        }

        $response = $app->getResponseFactory()->createResponse(302);

        if (!empty($_SESSION['member_authenticated'])) {
            return $response->withHeader('Location', '/membro?status=forbidden');
        }

        return $response->withHeader('Location', '/entrar');
    };

    $panelBookshopAccessMiddleware = function (Request $request, RequestHandler $handler) use ($app, $memberHasAnyRole): Response {
        if ($memberHasAnyRole(['bookshop_operator', 'admin'])) {
            return $handler->handle($request);
        }

        $response = $app->getResponseFactory()->createResponse(302);

        if (!empty($_SESSION['member_authenticated'])) {
            return $response->withHeader('Location', '/membro?status=forbidden');
        }

        return $response->withHeader('Location', '/entrar');
    };

    $memberSessionAuthMiddleware = function (Request $request, RequestHandler $handler) use ($app): Response {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (!empty($_SESSION['member_authenticated'])) {
            return $handler->handle($request);
        }

        $requestedPath = trim($request->getUri()->getPath());
        $requestedQuery = trim($request->getUri()->getQuery());
        $redirectTarget = $requestedPath !== '' ? $requestedPath : '/';

        if ($requestedQuery !== '') {
            $redirectTarget .= '?' . $requestedQuery;
        }

        $response = $app->getResponseFactory()->createResponse(302);

        return $response->withHeader(
            'Location',
            '/entrar?redirect_to=' . rawurlencode($redirectTarget)
        );
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
    $app->get('/quem-somos/fundador', AboutFounderPageAction::class);
    $app->get('/quem-somos/estatuto', AboutStatutePageAction::class);
    $app->get('/quem-somos/nossa-marca', AboutBrandPageAction::class);
    $app->get('/quem-somos/gestao-cede', AboutManagementPageAction::class);
    $app->get('/estudos', StudiesPageAction::class);
    $app->get('/estudos/esde', EsdePageAction::class);
    $app->get('/estudos/palestras', PublicLecturesPageAction::class);
    $app->get('/estudos/atendimento-fraterno', FraternalServicePageAction::class);
    $app->get('/agenda', AgendaPageAction::class);
    $app->get('/agenda/{slug}', AgendaDetailPageAction::class);
    $app->get('/agenda/{slug}/ics', AgendaEventIcsDownloadAction::class);
    $app->get('/media/livraria/capas/{file}', BookshopCoverImagePageAction::class);
    $app->get('/loja', StorePageAction::class);
    $app->get('/loja/bazar', StoreBazaarPageAction::class);
    $app->get('/loja/livraria', StoreBookshopPageAction::class);
    $app->get('/loja/livraria-ii', StoreBookshopIiPageAction::class);
    $app->get('/livraria', function (Request $request, Response $response) {
        $queryString = trim($request->getUri()->getQuery());
        $target = '/loja/livraria' . ($queryString !== '' ? '?' . $queryString : '');

        return $response->withHeader('Location', $target)->withStatus(302);
    });
    $app->get('/livraria-ii', function (Request $request, Response $response) {
        $queryString = trim($request->getUri()->getQuery());
        $target = '/loja/livraria-ii' . ($queryString !== '' ? '?' . $queryString : '');

        return $response->withHeader('Location', $target)->withStatus(302);
    });
    $app->get('/quem-somos/base-de-conhecimento', LibraryPageAction::class);
    $app->get('/loja/biblioteca', function (Request $request, Response $response) {
        $queryString = trim($request->getUri()->getQuery());
        $target = '/quem-somos/base-de-conhecimento' . ($queryString !== '' ? '?' . $queryString : '');

        return $response->withHeader('Location', $target)->withStatus(302);
    });
    $app->get('/biblioteca', function (Request $request, Response $response) {
        $queryString = trim($request->getUri()->getQuery());
        $target = '/quem-somos/base-de-conhecimento' . ($queryString !== '' ? '?' . $queryString : '');

        return $response->withHeader('Location', $target)->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/cadastro', MemberRegisterPageAction::class);
    $app->map(['GET', 'POST'], '/entrar', MemberLoginPageAction::class);
    $app->map(['GET', 'POST'], '/esqueci-senha', MemberForgotPasswordPageAction::class);
    $app->map(['GET', 'POST'], '/redefinir-senha', MemberResetPasswordPageAction::class);
    $app->map(['GET', 'POST'], '/membro/sair', MemberLogoutAction::class);
    $app->get('/membro', MemberHomePageAction::class);
    $app->map(['GET', 'POST'], '/membro/perfil/completar', MemberCompleteProfilePageAction::class);
    $app->post('/membro/eventos/{id}/participacao', MemberEventInterestToggleAction::class);
    $app->get('/membro/operacao', MemberOperatorAreaPageAction::class);
    $app->get('/membro/gestao', MemberManagerAreaPageAction::class);
    $app->get('/membro/administracao', MemberAdminAreaPageAction::class);
    $app->map(['GET', 'POST'], '/painel/login', AdminLoginPageAction::class);
    $app->get('/painel/logout', AdminLogoutAction::class);
    $app->group('/painel', function (Group $group) use ($panelBookshopAccessMiddleware, $panelDashboardAccessMiddleware, $panelRoleMiddlewareFactory) {
        $group->get('', AdminDashboardPageAction::class)->add($panelDashboardAccessMiddleware);
        $group->get('/eventos', AdminAgendaListPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->map(['GET', 'POST'], '/eventos/novo', AdminAgendaFormPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->map(['GET', 'POST'], '/eventos/{id}/editar', AdminAgendaFormPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->post('/eventos/{id}/excluir', AdminAgendaDeleteAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->get('/categorias', AdminCategoryListPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->map(['GET', 'POST'], '/categorias/nova', AdminCategoryFormPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->map(['GET', 'POST'], '/categorias/{id}/editar', AdminCategoryFormPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->post('/categorias/{id}/alternar-status', AdminCategoryToggleStatusAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->get('/biblioteca/livros', AdminLibraryBookListPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->map(['GET', 'POST'], '/biblioteca/livros/novo', AdminLibraryBookFormPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->map(['GET', 'POST'], '/biblioteca/livros/{id}/editar', AdminLibraryBookFormPageAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->post('/biblioteca/livros/{id}/excluir', AdminLibraryBookDeleteAction::class)->add($panelRoleMiddlewareFactory('operator'));
        $group->get('/biblioteca/categorias', AdminLibraryCategoryListPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->map(['GET', 'POST'], '/biblioteca/categorias/nova', AdminLibraryCategoryFormPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->map(['GET', 'POST'], '/biblioteca/categorias/{id}/editar', AdminLibraryCategoryFormPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->post('/biblioteca/categorias/{id}/alternar-status', AdminLibraryCategoryToggleStatusAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->get('/livraria', AdminBookshopDashboardPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/manual', AdminBookshopManualPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/relatorios', AdminBookshopReportsPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/movimentacoes', AdminBookshopStockMovementListPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/movimentacoes/nova', AdminBookshopStockMovementFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/colecoes', AdminBookshopCollectionListPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/colecoes/nova', AdminBookshopCollectionFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/colecoes/{id}/editar', AdminBookshopCollectionFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->post('/livraria/colecoes/{id}/alternar-status', AdminBookshopCollectionToggleStatusAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/categorias', AdminBookshopCategoryListPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/categorias/nova', AdminBookshopCategoryFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/categorias/{id}/editar', AdminBookshopCategoryFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->post('/livraria/categorias/{id}/alternar-status', AdminBookshopCategoryToggleStatusAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/generos', AdminBookshopGenreListPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/generos/novo', AdminBookshopGenreFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/generos/{id}/editar', AdminBookshopGenreFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->post('/livraria/generos/{id}/alternar-status', AdminBookshopGenreToggleStatusAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/acervo', AdminBookshopBookListPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/acervo/novo', AdminBookshopBookFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/acervo/{id}/editar', AdminBookshopBookFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->post('/livraria/acervo/{id}/excluir', AdminBookshopBookDeleteAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/importar', AdminBookshopImportPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/vendas', AdminBookshopSaleListPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->map(['GET', 'POST'], '/livraria/vendas/nova', AdminBookshopSaleFormPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/livraria/vendas/{id}', AdminBookshopSaleViewPageAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->post('/livraria/vendas/{id}/cancelar', AdminBookshopSaleCancelAction::class)
            ->add($panelBookshopAccessMiddleware);
        $group->get('/usuarios', AdminMemberUsersPageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->get('/gestao-cede', AdminCedeManagementPageAction::class)->add($panelRoleMiddlewareFactory('manager'));
        $group->get('/usuarios/{id}/resumo', AdminMemberUserSummaryPageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->post('/usuarios/{id}/atribuir-papel', AdminMemberAssignRoleAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->post('/visitas/nova-contagem', AdminVisitCounterResetAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->get('/guia-do-usuario', AdminUserGuidePageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->get('/guia-pratico', AdminPracticalGuidePageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->map(['GET', 'POST'], '/institucional/estatuto', AdminStatutePageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->map(['GET', 'POST'], '/institucional/politica-de-privacidade', AdminPrivacyPolicyPageAction::class)->add($panelRoleMiddlewareFactory('admin'));
        $group->map(['GET', 'POST'], '/institucional/termos-de-uso', AdminTermsOfUsePageAction::class)->add($panelRoleMiddlewareFactory('admin'));
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
    $app->get('/admin/biblioteca', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(302);
    });
    $app->get('/admin/biblioteca/livros', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/biblioteca/livros/novo', function (Request $request, Response $response) {
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/biblioteca/livros/novo')->withStatus($statusCode);
    });
    $app->map(['GET', 'POST'], '/admin/biblioteca/livros/{id}/editar', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/biblioteca/livros/' . $id . '/editar')->withStatus($statusCode);
    });
    $app->post('/admin/biblioteca/livros/{id}/excluir', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');

        return $response->withHeader('Location', '/painel/biblioteca/livros/' . $id . '/excluir')->withStatus(307);
    });
    $app->get('/admin/biblioteca/categorias', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/biblioteca/categorias')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/biblioteca/categorias/nova', function (Request $request, Response $response) {
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/biblioteca/categorias/nova')->withStatus($statusCode);
    });
    $app->map(['GET', 'POST'], '/admin/biblioteca/categorias/{id}/editar', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/biblioteca/categorias/' . $id . '/editar')->withStatus($statusCode);
    });
    $app->post('/admin/biblioteca/categorias/{id}/alternar-status', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');

        return $response->withHeader('Location', '/painel/biblioteca/categorias/' . $id . '/alternar-status')->withStatus(307);
    });
    $app->get('/admin/livraria', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/livraria')->withStatus(302);
    });
    $app->get('/admin/livraria/colecoes', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/livraria/colecoes')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/livraria/colecoes/nova', function (Request $request, Response $response) {
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/livraria/colecoes/nova')->withStatus($statusCode);
    });
    $app->map(['GET', 'POST'], '/admin/livraria/colecoes/{id}/editar', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/livraria/colecoes/' . $id . '/editar')->withStatus($statusCode);
    });
    $app->post('/admin/livraria/colecoes/{id}/alternar-status', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');

        return $response->withHeader('Location', '/painel/livraria/colecoes/' . $id . '/alternar-status')->withStatus(307);
    });
    $app->get('/admin/livraria/categorias', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/livraria/categorias')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/livraria/categorias/nova', function (Request $request, Response $response) {
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/livraria/categorias/nova')->withStatus($statusCode);
    });
    $app->map(['GET', 'POST'], '/admin/livraria/categorias/{id}/editar', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/livraria/categorias/' . $id . '/editar')->withStatus($statusCode);
    });
    $app->post('/admin/livraria/categorias/{id}/alternar-status', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');

        return $response->withHeader('Location', '/painel/livraria/categorias/' . $id . '/alternar-status')->withStatus(307);
    });
    $app->get('/admin/livraria/generos', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/painel/livraria/generos')->withStatus(302);
    });
    $app->map(['GET', 'POST'], '/admin/livraria/generos/nova', function (Request $request, Response $response) {
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/livraria/generos/nova')->withStatus($statusCode);
    });
    $app->map(['GET', 'POST'], '/admin/livraria/generos/{id}/editar', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/livraria/generos/' . $id . '/editar')->withStatus($statusCode);
    });
    $app->post('/admin/livraria/generos/{id}/alternar-status', function (Request $request, Response $response) {
        $id = (string) ($request->getAttribute('id') ?? '');

        return $response->withHeader('Location', '/painel/livraria/generos/' . $id . '/alternar-status')->withStatus(307);
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
    $app->map(['GET', 'POST'], '/admin/institucional/estatuto', function (Request $request, Response $response) {
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/institucional/estatuto')->withStatus($statusCode);
    });
    $app->map(['GET', 'POST'], '/admin/institucional/politica-de-privacidade', function (Request $request, Response $response) {
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/institucional/politica-de-privacidade')->withStatus($statusCode);
    });
    $app->map(['GET', 'POST'], '/admin/institucional/termos-de-uso', function (Request $request, Response $response) {
        $statusCode = strtoupper($request->getMethod()) === 'POST' ? 307 : 302;

        return $response->withHeader('Location', '/painel/institucional/termos-de-uso')->withStatus($statusCode);
    });
    $app->get('/faq', FaqPageAction::class);
    $app->get('/faq/doutrina', FaqDoctrinePageAction::class);
    $app->get('/faq/participacao', FaqParticipationPageAction::class);
    $app->get('/faq/praticas', FaqPracticesPageAction::class);
    $app->map(['GET', 'POST'], '/contato', ContactPageAction::class);
    $app->get('/politica-de-privacidade', PrivacyPolicyPageAction::class);
    $app->get('/termos-de-uso', TermsOfUsePageAction::class);

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
            ['template' => 'home/testimonials.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/roadmap.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/faq.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/final-cta.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'components/theme-palette.twig', 'context' => []],
            ['template' => 'components/footer.twig', 'context' => []],
            ['template' => 'home.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/about.twig', 'context' => ['homeContent' => $homeContent]],
            [
                'template' => 'pages/about-detail.twig',
                'context' => ['homeContent' => $homeContent, 'about' => $homeContent['aboutPages']['missao'] ?? []],
            ],
            [
                'template' => 'pages/about-detail.twig',
                'context' => ['homeContent' => $homeContent, 'about' => $homeContent['aboutPages']['estatuto'] ?? []],
            ],
            [
                'template' => 'pages/about-founder.twig',
                'context' => ['homeContent' => $homeContent, 'founder' => $homeContent['aboutPages']['fundador'] ?? []],
            ],
            [
                'template' => 'pages/about-statute.twig',
                'context' => ['homeContent' => $homeContent, 'statute' => (require __DIR__ . '/content/statute.php')],
            ],
            [
                'template' => 'pages/legal-document.twig',
                'context' => ['homeContent' => $homeContent, 'legal_document' => (require __DIR__ . '/content/privacy-policy.php')],
            ],
            [
                'template' => 'pages/legal-document.twig',
                'context' => ['homeContent' => $homeContent, 'legal_document' => (require __DIR__ . '/content/terms-of-use.php')],
            ],
            ['template' => 'pages/about-brand.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/studies.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/study-detail.twig', 'context' => ['homeContent' => $homeContent, 'study' => $homeContent['studiesPages']['esde'] ?? []]],
            ['template' => 'pages/library.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/admin-library-books.twig', 'context' => ['library_books' => []]],
            ['template' => 'pages/admin-library-book-form.twig', 'context' => ['library_book_form' => [], 'library_book_categories' => []]],
            ['template' => 'pages/admin-library-categories.twig', 'context' => ['library_categories' => []]],
            ['template' => 'pages/admin-library-category-form.twig', 'context' => ['library_category_form' => []]],
            ['template' => 'pages/admin-bookshop-dashboard.twig', 'context' => ['bookshop_metrics' => []]],
            ['template' => 'pages/admin-bookshop-books.twig', 'context' => ['bookshop_books' => []]],
            ['template' => 'pages/admin-bookshop-collections.twig', 'context' => ['bookshop_collections' => []]],
            ['template' => 'pages/admin-bookshop-collection-form.twig', 'context' => ['bookshop_collection_form' => []]],
            ['template' => 'pages/admin-bookshop-categories.twig', 'context' => ['bookshop_categories' => []]],
            ['template' => 'pages/admin-bookshop-category-form.twig', 'context' => ['bookshop_category_form' => []]],
            ['template' => 'pages/admin-bookshop-genres.twig', 'context' => ['bookshop_genres' => []]],
            ['template' => 'pages/admin-bookshop-genre-form.twig', 'context' => ['bookshop_genre_form' => []]],
            [
                'template' => 'pages/admin-bookshop-book-form.twig',
                'context' => [
                    'bookshop_book_form' => [],
                    'bookshop_book_collections' => [],
                    'bookshop_book_categories' => [],
                    'bookshop_book_genres' => [],
                    'bookshop_book_language_options' => [],
                ],
            ],
            ['template' => 'pages/admin-bookshop-manual.twig', 'context' => []],
            ['template' => 'pages/admin-bookshop-reports.twig', 'context' => []],
            ['template' => 'pages/admin-bookshop-stock-movements.twig', 'context' => ['bookshop_stock_movements' => []]],
            ['template' => 'pages/admin-bookshop-stock-movement-form.twig', 'context' => ['bookshop_stock_movement_book_options' => [], 'bookshop_stock_movement_type_options' => []]],
            ['template' => 'pages/admin-bookshop-import.twig', 'context' => []],
            ['template' => 'pages/admin-bookshop-sales.twig', 'context' => ['bookshop_sales' => []]],
            [
                'template' => 'pages/admin-bookshop-sale-form.twig',
                'context' => ['bookshop_sale_form' => ['items' => []], 'bookshop_sale_book_options' => []],
            ],
            ['template' => 'pages/admin-bookshop-sale-view.twig', 'context' => ['bookshop_sale' => ['items' => []]]],
            ['template' => 'pages/agenda.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/agenda-detail.twig', 'context' => ['homeContent' => $homeContent, 'agenda' => $homeContent['agendaPages']['estudo-do-evangelho'] ?? []]],
            ['template' => 'pages/store.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/store-bazaar.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/store-bookshop.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/store-bookshop-ii.twig', 'context' => ['homeContent' => $homeContent]],
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
