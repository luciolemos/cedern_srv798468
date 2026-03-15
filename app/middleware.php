<?php

declare(strict_types=1);

use App\Domain\Agenda\AgendaRepository;
use App\Domain\Member\MemberAuthRepository;
use App\Application\Middleware\SessionMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Views\Twig;

return function (App $app) {
    $app->add(function (Request $request, RequestHandler $handler) use ($app) {
        $twig = $app->getContainer()->get(Twig::class);
        $twigEnvironment = $twig->getEnvironment();
        $appEnv = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'production')));
        $dashboardEnvLabel = 'Produção';
        $dashboardEnvTone = 'prod';

        if (in_array($appEnv, ['dev', 'development', 'local'], true)) {
            $dashboardEnvLabel = 'Desenvolvimento';
            $dashboardEnvTone = 'dev';
        } elseif (in_array($appEnv, ['test', 'testing', 'qa', 'homolog'], true)) {
            $dashboardEnvLabel = 'Homologação';
            $dashboardEnvTone = 'test';
        }

        $dashboardRoleWeights = [
            'member' => 10,
            'operator' => 20,
            'manager' => 30,
            'admin' => 40,
        ];

        $memberRoleKey = trim((string) ($_SESSION['member_role_key'] ?? 'member'));
        $memberRoleWeight = (int) ($dashboardRoleWeights[$memberRoleKey] ?? 0);
        $memberHasDashboardAccess = !empty($_SESSION['member_authenticated']) && $memberRoleWeight >= 20;
        $memberCanManageUsers = !empty($_SESSION['member_authenticated'])
            && in_array($memberRoleKey, ['admin'], true);

        $dashboardIsAdminSession = !empty($_SESSION['admin_authenticated']);
        $dashboardIsAuthenticated = !empty($_SESSION['admin_authenticated']) || $memberHasDashboardAccess;
        $dashboardCanManageUsers = $dashboardIsAdminSession || $memberCanManageUsers;
        $dashboardUser = (string) ($_SESSION['admin_user'] ?? '');
        $dashboardUserPhotoPath = '';
        $dashboardAdminNotifications = [];
        $dashboardPendingUsers = [];
        $dashboardNotificationCount = 0;

        if ($dashboardUser === '' && $memberHasDashboardAccess) {
            $dashboardUser = (string) ($_SESSION['member_name'] ?? 'Usuário');
            $dashboardUserPhotoPath = (string) ($_SESSION['member_profile_photo_path'] ?? '');
        }

        if ($dashboardCanManageUsers) {
            try {
                /** @var MemberAuthRepository $memberAuthRepository */
                $memberAuthRepository = $app->getContainer()->get(MemberAuthRepository::class);
                $allUsers = $memberAuthRepository->findAllUsersForAdmin();

                $dashboardPendingUsers = array_values(array_filter(
                    $allUsers,
                    static fn (array $user): bool => (string) ($user['status'] ?? '') === 'pending'
                ));

                $dashboardNotificationCount = count($dashboardPendingUsers);

                if ($dashboardNotificationCount > 0) {
                    $dashboardAdminNotifications[] = [
                        'title' => 'Contas pendentes',
                        'description' => $dashboardNotificationCount . ' cadastro(s) para aprovar.',
                        'href' => '/painel/usuarios?sort=created_at&dir=desc&q=pending',
                        'cta' => 'Aprovar contas',
                    ];
                }

                $dashboardPendingUsers = array_slice($dashboardPendingUsers, 0, 5);
            } catch (\Throwable $exception) {
            }
        }

        $twigEnvironment->addGlobal('current_path', $request->getUri()->getPath());
        $twigEnvironment->addGlobal('dashboard_user', $dashboardUser);
        $twigEnvironment->addGlobal('dashboard_user_photo_path', $dashboardUserPhotoPath);
        $twigEnvironment->addGlobal('dashboard_is_authenticated', $dashboardIsAuthenticated);
        $twigEnvironment->addGlobal('dashboard_is_admin_session', $dashboardIsAdminSession);
        $twigEnvironment->addGlobal('member_is_authenticated', !empty($_SESSION['member_authenticated']));
        $twigEnvironment->addGlobal('member_name', (string) ($_SESSION['member_name'] ?? ''));
        $twigEnvironment->addGlobal('member_role_key', (string) ($_SESSION['member_role_key'] ?? ''));
        $twigEnvironment->addGlobal('member_role_name', (string) ($_SESSION['member_role_name'] ?? 'Membro'));
        $twigEnvironment->addGlobal(
            'member_profile_photo_path',
            (string) ($_SESSION['member_profile_photo_path'] ?? '')
        );
        $twigEnvironment->addGlobal('dashboard_env_label', $dashboardEnvLabel);
        $twigEnvironment->addGlobal('dashboard_env_tone', $dashboardEnvTone);
        $twigEnvironment->addGlobal('dashboard_admin_notifications', $dashboardAdminNotifications);
        $twigEnvironment->addGlobal('dashboard_admin_pending_users', $dashboardPendingUsers);
        $twigEnvironment->addGlobal('dashboard_admin_notification_count', $dashboardNotificationCount);

        $navigationMenu = (array) ($twigEnvironment->getGlobals()['navigation_menu'] ?? []);

        if ($navigationMenu !== []) {
            try {
                /** @var AgendaRepository $agendaRepository */
                $agendaRepository = $app->getContainer()->get(AgendaRepository::class);
                $upcomingEvents = $agendaRepository->findUpcomingPublished(4);
                $maxLabelLength = 36;

                $toCompactLabel = static function (string $value) use ($maxLabelLength): string {
                    $label = trim($value);

                    if ($label === '') {
                        return 'Atividade';
                    }

                    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                        if (mb_strlen($label) <= $maxLabelLength) {
                            return $label;
                        }

                        return rtrim(mb_substr($label, 0, $maxLabelLength - 1)) . '…';
                    }

                    if (strlen($label) <= $maxLabelLength) {
                        return $label;
                    }

                    return rtrim(substr($label, 0, $maxLabelLength - 1)) . '…';
                };

                $dynamicAgendaItems = [[
                    'path' => '/agenda',
                    'label' => 'Visão geral',
                ]];

                foreach ($upcomingEvents as $event) {
                    $slug = trim((string) ($event['slug'] ?? ''));
                    $title = trim((string) ($event['title'] ?? 'Atividade'));

                    if ($slug === '') {
                        continue;
                    }

                    $dynamicAgendaItems[] = [
                        'path' => '/agenda/' . $slug,
                        'label' => $toCompactLabel($title),
                    ];
                }

                foreach ($navigationMenu as $index => $group) {
                    if (!is_array($group) || (string) ($group['key'] ?? '') !== 'agenda') {
                        continue;
                    }

                    $navigationMenu[$index]['items'] = $dynamicAgendaItems;
                    break;
                }

                $twigEnvironment->addGlobal('navigation_menu', $navigationMenu);
            } catch (\Throwable $exception) {
            }
        }

        return $handler->handle($request);
    });

    $app->add(SessionMiddleware::class);
};
