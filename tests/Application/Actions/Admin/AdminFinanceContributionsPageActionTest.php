<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminFinanceContributionsPageAction;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AdminFinanceContributionsPageActionTest extends TestCase
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

    public function testRendersContributionDashboardWithGeneratedAndPendingProfiles(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        $paidCandidateId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Silva',
            'email' => 'marina@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($paidCandidateId, [
            'full_name' => 'Marina Silva',
            'cpf' => '52998224725',
            'phone_mobile' => '84999998888',
            'preferred_due_day' => 10,
            'contribution_amount' => '65.50',
            'contribution_plan_label' => 'Contribuição associativa',
            'preferred_payment_method' => 'pix',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($paidCandidateId, 1, 'Atendimento fraterno', 'efetivo');

        $pendingProfileId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Carlos Pereira',
            'email' => 'carlos@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($pendingProfileId, [
            'full_name' => 'Carlos Pereira',
            'cpf' => '12345678909',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($pendingProfileId, 1, 'Mediunidade', 'fundador');

        $memberAuthRepository->generateContributionCharges('2026-07', 7);

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
        ) extends AdminFinanceContributionsPageAction {
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

        $request = $this->createRequest('GET', '/painel/financas/contribuicoes?competence=2026-07')
            ->withQueryParams([
                'competence' => '2026-07',
            ]);

        $response = $action($request, new Response());

        $html = $twig->fetch($action->capturedTemplate, array_merge($action->capturedData, [
            'base_url' => '',
            'current_path' => '/painel/financas/contribuicoes',
            'csrf_token' => 'test-token',
            'csrf_field_name' => '_csrf',
            'dashboard_user' => 'Financeiro de Teste',
            'dashboard_user_photo_path' => '',
            'dashboard_is_authenticated' => true,
            'dashboard_is_admin_session' => false,
            'dashboard_env_label' => 'Homologação',
            'dashboard_env_tone' => 'test',
            'dashboard_admin_notifications' => [],
            'dashboard_admin_pending_users' => [],
            'dashboard_admin_notification_count' => 0,
            'member_is_authenticated' => true,
            'member_name' => 'Financeiro de Teste',
            'member_role_key' => 'finance_operator',
            'member_role_name' => 'Operador Financeiro',
            'member_profile_photo_path' => '',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/admin-finance-contributions.twig', $action->capturedTemplate);
        $this->assertStringContainsString('Julho de 2026', $html);
        $this->assertStringContainsString('Marina Silva', $html);
        $this->assertStringContainsString('529.982.247-25', $html);
        $this->assertStringContainsString('R$ 65,50', $html);
        $this->assertStringContainsString('Em aberto', $html);
        $this->assertStringContainsString('Cobrança por e-mail autorizada', $html);
        $this->assertStringContainsString('Enviar e-mail', $html);
        $this->assertStringContainsString('WhatsApp', $html);
        $this->assertStringContainsString('Carlos Pereira', $html);
        $this->assertStringContainsString('Cadastro pendente', $html);
        $this->assertStringContainsString('Gerar cobranças da competência', $html);
        $this->assertStringContainsString('/painel/financas/contribuicoes/' , $html);
    }
}
