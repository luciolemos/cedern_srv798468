<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminMemberUserViewPageAction;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AdminMemberUserViewPageActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testRendersReadOnlyMemberRegistrationView(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Silva',
            'email' => 'marina@example.com',
            'password_hash' => 'hash',
        ]);

        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Marina Silva',
            'phone_mobile' => '84999998888',
            'phone_landline' => '8433221100',
            'birth_date' => '1990-08-12',
            'birth_place' => 'Natal/RN',
            'privacy_notice_version' => 'v2026.1',
            'privacy_notice_accepted_at' => '2026-06-18 14:30:00',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 4, 'Coordenador', 'efetivo');

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new class (
            $logger,
            $twig,
            $memberAuthRepository
        ) extends AdminMemberUserViewPageAction {
            public string $capturedTemplate = '';

            /** @var array<string, mixed> */
            public array $capturedData = [];

            protected function renderPage(
                ResponseInterface $response,
                string $template,
                array $data = []
            ): ResponseInterface {
                $this->capturedTemplate = $template;
                $this->capturedData = $data;

                return $response;
            }
        };

        $request = $this->createRequest('GET', '/painel/usuarios/' . $userId)
            ->withAttribute('id', $userId);

        $response = $action($request, new Response());

        $html = $twig->fetch($action->capturedTemplate, array_merge($action->capturedData, [
            'base_url' => '',
            'current_path' => '/painel/usuarios/' . $userId,
            'csrf_token' => 'test-token',
            'csrf_field_name' => '_csrf',
            'dashboard_user' => 'Administrador de Teste',
            'dashboard_user_photo_path' => '',
            'dashboard_is_authenticated' => true,
            'dashboard_is_admin_session' => true,
            'dashboard_env_label' => 'Homologação',
            'dashboard_env_tone' => 'test',
            'dashboard_admin_notifications' => [],
            'dashboard_admin_pending_users' => [],
            'dashboard_admin_notification_count' => 0,
            'member_is_authenticated' => true,
            'member_name' => 'Administrador de Teste',
            'member_role_key' => 'admin',
            'member_role_name' => 'Administrador',
            'member_profile_photo_path' => '',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/admin-member-user-view.twig', $action->capturedTemplate);
        $this->assertStringContainsString('Marina Silva', $html);
        $this->assertStringContainsString('Administrador', $html);
        $this->assertStringContainsString('(84) 99999-8888', $html);
        $this->assertStringContainsString('(84) 3322-1100', $html);
        $this->assertStringContainsString('12/08/1990', $html);
        $this->assertStringContainsString('18/06/2026 14:30', $html);
        $this->assertStringContainsString('/painel/usuarios/' . $userId . '/resumo', $html);
    }
}
