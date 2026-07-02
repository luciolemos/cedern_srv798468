<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminMemberUsersPageAction;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AdminMemberUsersPageActionTest extends TestCase
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

    public function testRendersApplicantWithoutReleasedSystemProfile(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $memberAuthRepository->createPendingUser([
            'full_name' => 'Rafaela Nunes',
            'email' => 'rafaela@example.com',
            'password_hash' => 'hash',
        ]);
        $formerUserId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Carlos Mendes',
            'email' => 'carlos.mendes@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole($formerUserId, 4, 'Coordenador', 'efetivo', 'former', false, 'blocked');

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
        ) extends AdminMemberUsersPageAction {
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

        $request = $this->createRequest('GET', '/painel/usuarios');
        $response = $action($request, new Response());

        $html = $twig->fetch($action->capturedTemplate, array_merge($action->capturedData, [
            'base_url' => '',
            'current_path' => '/painel/usuarios',
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
        $this->assertSame('pages/admin-member-users.twig', $action->capturedTemplate);
        $this->assertStringContainsString('Rafaela Nunes', $html);
        $this->assertStringContainsString('Solicitante', $html);
        $this->assertStringContainsString('Sem perfil liberado', $html);
        $this->assertStringContainsString('Carlos Mendes', $html);
        $this->assertStringContainsString('Desligado', $html);
        $this->assertStringContainsString('Sem perfil ativo', $html);
    }
}
