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
        $adminId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Gestor CEDE',
            'email' => 'gestor@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole($adminId, 4, null, null, 'member', false, 'active');

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
            'cpf' => '52998224725',
            'postal_code' => '59000000',
            'street_address' => 'Rua das Flores',
            'address_number' => '123',
            'address_complement' => 'Apto 12',
            'neighborhood' => 'Centro',
            'address_city' => 'Parnamirim',
            'address_state' => 'RN',
            'preferred_due_day' => 10,
            'contribution_amount' => '65.50',
            'contribution_plan_label' => 'Plano associado efetivo',
            'preferred_payment_method' => 'pix',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'privacy_notice_version' => 'v2026.1',
            'privacy_notice_accepted_at' => '2026-06-18 14:30:00',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 4, 'Coordenador', 'efetivo', 'member', true, 'active', $adminId);
        $memberAuthRepository->approveAndAssignRole($userId, 4, null, null, 'former', false, 'blocked', $adminId);

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
        $this->assertStringContainsString('Dados do cadastro', $html);
        $this->assertStringContainsString('Essas informações ficam associadas à conta de membro.', $html);
        $this->assertStringContainsString('Acesso ao SISCEDE', $html);
        $this->assertStringContainsString('Perfil no SISCEDE', $html);
        $this->assertStringContainsString('Marina Silva', $html);
        $this->assertStringContainsString('Administrador', $html);
        $this->assertStringContainsString('(84) 99999-8888', $html);
        $this->assertStringContainsString('(84) 3322-1100', $html);
        $this->assertStringContainsString('12/08/1990', $html);
        $this->assertStringContainsString('529.982.247-25', $html);
        $this->assertStringContainsString('Endereço', $html);
        $this->assertStringContainsString('Informações de localização vinculadas a este cadastro.', $html);
        $this->assertStringContainsString('59000-000', $html);
        $this->assertStringContainsString('Rua das Flores, 123 - Apto 12', $html);
        $this->assertStringContainsString('Centro', $html);
        $this->assertStringContainsString('Parnamirim / RN', $html);
        $this->assertStringContainsString('Configuração financeira', $html);
        $this->assertStringContainsString('Dia 10', $html);
        $this->assertStringContainsString('R$ 65,50', $html);
        $this->assertStringContainsString('Plano associado efetivo', $html);
        $this->assertStringContainsString('Pix', $html);
        $this->assertStringContainsString('Autorizado', $html);
        $this->assertStringContainsString('18/06/2026 14:30', $html);
        $this->assertStringContainsString('/painel/usuarios/' . $userId . '/resumo', $html);
        $this->assertStringContainsString('Sem perfil ativo', $html);
        $this->assertStringContainsString('Histórico administrativo', $html);
        $this->assertStringContainsString('Cadastro criado como solicitante com acesso pendente.', $html);
        $this->assertStringContainsString(
            'Situação administrativa atualizada: acesso bloqueado, vínculo desligado, contribuinte não participa.',
            $html
        );
        $this->assertStringContainsString('Gestor CEDE', $html);
        $this->assertStringContainsString('Sistema', $html);
        $this->assertTrue(strpos($html, '<dt>Perfil no SISCEDE</dt>') < strpos($html, '<dt>Tipo de Sócio</dt>'));
        $this->assertTrue(strpos($html, '<dt>Tipo de Sócio</dt>') < strpos($html, '<dt>E-mail</dt>'));
        $this->assertTrue(strpos($html, '<dt>E-mail</dt>') < strpos($html, '<dt>Celular</dt>'));
        $this->assertTrue(strpos($html, '<dt>Telefone</dt>') < strpos($html, '<dt>Data de nascimento</dt>'));
        $this->assertTrue(strpos($html, '<dt>Data de nascimento</dt>') < strpos($html, '<dt>Naturalidade</dt>'));
        $this->assertTrue(strpos($html, '<dt>Naturalidade</dt>') < strpos($html, '<dt>CPF</dt>'));
    }
}
