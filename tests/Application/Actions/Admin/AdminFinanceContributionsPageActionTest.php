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
        $this->assertStringContainsString('Carlos Pereira', $html);
        $this->assertStringContainsString('Configuração pendente', $html);
        $this->assertStringContainsString('Exportar CSV', $html);
        $this->assertStringContainsString('Pago em', $html);
        $this->assertStringContainsString('Forma de pagamento', $html);
        $this->assertStringNotContainsString('Gerar cobranças da competência', $html);
        $this->assertStringNotContainsString('Ações', $html);
        $this->assertStringNotContainsString('Enviar e-mail', $html);
        $this->assertStringNotContainsString('/painel/financas/contribuicoes/1/whatsapp?', $html);
        $this->assertStringNotContainsString('Registrar recebimento', $html);
        $this->assertStringContainsString('Mostrando 1-2 de 2 registros', $html);
        $this->assertStringContainsString('Registros por página', $html);
    }

    public function testKeepsManagementButtonsVisibleForAdmin(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Admin Financeiro',
            'email' => 'admin.financeiro@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Admin Financeiro',
            'cpf' => '52998224725',
            'phone_mobile' => '84999998888',
            'preferred_due_day' => 10,
            'contribution_amount' => '65.50',
            'preferred_payment_method' => 'pix',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 4, 'Diretor de Finanças', 'efetivo', 'member', true, 'active');
        $memberAuthRepository->generateContributionCharges('2026-07', 7);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['member_authenticated'] = true;
        $_SESSION['member_user_id'] = $userId;
        $_SESSION['member_role_key'] = 'admin';
        $_SESSION['member_role_name'] = 'Administrador';
        $_SESSION['admin_authenticated'] = true;

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
            'dashboard_user' => 'Administrador Financeiro',
            'dashboard_user_photo_path' => '',
            'dashboard_is_authenticated' => true,
            'dashboard_is_admin_session' => true,
            'dashboard_env_label' => 'Homologação',
            'dashboard_env_tone' => 'test',
            'dashboard_admin_notifications' => [],
            'dashboard_admin_pending_users' => [],
            'dashboard_admin_notification_count' => 0,
            'member_is_authenticated' => true,
            'member_name' => 'Administrador Financeiro',
            'member_role_key' => 'admin',
            'member_role_name' => 'Administrador',
            'member_profile_photo_path' => '',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Gerar cobranças da competência', $html);
        $this->assertStringContainsString('Ações', $html);
        $this->assertStringContainsString('Registrar recebimento', $html);
        $this->assertStringContainsString('Enviar e-mail', $html);
        $this->assertStringContainsString('WhatsApp', $html);
    }

    public function testExportsFilteredContributionsAsCsv(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Helena Exportacao',
            'email' => 'helena.exportacao@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Helena Exportacao',
            'cpf' => '52998224725',
            'phone_mobile' => '84999998888',
            'preferred_due_day' => 10,
            'contribution_amount' => '65.50',
            'preferred_payment_method' => 'pix',
            'billing_email_opt_in' => 1,
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, 'Atendimento fraterno', 'efetivo');
        $memberAuthRepository->generateContributionCharges('2026-07', 7);

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['member_authenticated'] = true;
        $_SESSION['member_user_id'] = 99;
        $_SESSION['member_role_key'] = 'finance_operator';
        $_SESSION['member_role_name'] = 'Operador Financeiro';

        $action = new AdminFinanceContributionsPageAction(
            $logger,
            $twig,
            $memberAuthRepository
        );

        $request = $this->createRequest('GET', '/painel/financas/contribuicoes')
            ->withQueryParams([
                'competence' => '2026-07',
                'export' => 'csv',
            ]);

        $response = $action($request, new Response());
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="contribuicoes-2026-07-', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('contribuinte;cpf;email;competencia;vencimento;valor;situacao;pago_em;forma_pagamento;cobranca;tipo_socio;funcao_cede;observacoes', mb_strtolower($body));
        $this->assertStringContainsString('Helena Exportacao', $body);
        $this->assertStringContainsString('Julho de 2026', $body);
        $this->assertStringContainsString('R$ 65,50', $body);
    }

    public function testKeepsCurrentCompetenceAsPaidWhenThereIsOlderOverdueCharge(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Lúcio de Teste',
            'email' => 'lucio@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Lúcio de Teste',
            'cpf' => '52998224725',
            'phone_mobile' => '84999998888',
            'preferred_due_day' => 5,
            'contribution_amount' => '50.00',
            'preferred_payment_method' => 'boleto',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, 'Atendimento fraterno', 'efetivo');
        $memberAuthRepository->generateContributionCharges('2026-06', 7);
        $memberAuthRepository->generateContributionCharges('2026-07', 7);

        $julyRows = $memberAuthRepository->findContributionMembersByCompetence('2026-07');
        $julyChargeId = (int) ($julyRows[0]['charge_id'] ?? 0);
        $memberAuthRepository->markContributionChargeAsPaid($julyChargeId, 'pix', 7);

        $data = $this->renderActionData($memberAuthRepository, '2026-07');
        $row = $data['finance_contributions'][0] ?? null;

        $this->assertIsArray($row);
        $this->assertSame('paid', $row['status_key'] ?? null);
        $this->assertSame('Recebida', $row['status_label'] ?? null);
        $this->assertContains('Há 1 mensalidade anterior em atraso.', $row['status_notes'] ?? []);
    }

    public function testTreatsGatewayReceivedChargeAsPaidInCurrentCompetenceDisplay(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Gateway',
            'email' => 'marina-gateway@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Marina Gateway',
            'cpf' => '52998224725',
            'phone_mobile' => '84999998888',
            'preferred_due_day' => 5,
            'contribution_amount' => '50.00',
            'preferred_payment_method' => 'boleto',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, 'Atendimento fraterno', 'efetivo');
        $memberAuthRepository->generateContributionCharges('2026-06', 7);
        $memberAuthRepository->generateContributionCharges('2026-07', 7);

        $julyRows = $memberAuthRepository->findContributionMembersByCompetence('2026-07');
        $julyChargeId = (int) ($julyRows[0]['charge_id'] ?? 0);
        $memberAuthRepository->updateContributionGatewayData($julyChargeId, [
            'gateway_provider' => 'asaas',
            'gateway_payment_id' => 'pay_test_123',
            'gateway_billing_type' => 'PIX',
            'gateway_status' => 'RECEIVED',
            'gateway_last_synced_at' => '2026-07-01 12:32:26',
        ]);

        $data = $this->renderActionData($memberAuthRepository, '2026-07');
        $row = $data['finance_contributions'][0] ?? null;

        $this->assertIsArray($row);
        $this->assertSame('paid', $row['status_key'] ?? null);
        $this->assertSame('Recebida', $row['status_label'] ?? null);
        $this->assertFalse((bool) ($row['can_mark_paid'] ?? true));
        $this->assertContains('Recebimento confirmado no gateway.', $row['status_notes'] ?? []);
        $this->assertContains('Há 1 mensalidade anterior em atraso.', $row['status_notes'] ?? []);
    }

    public function testPaginatesContributionsAndPreservesRequestedPageSize(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        for ($index = 1; $index <= 6; $index++) {
            $fullName = sprintf('Contribuinte %02d', $index);
            $email = sprintf('contribuinte%02d@example.com', $index);
            $userId = $memberAuthRepository->createPendingUser([
                'full_name' => $fullName,
                'email' => $email,
                'password_hash' => 'hash',
            ]);
            $memberAuthRepository->updateProfile($userId, [
                'full_name' => $fullName,
                'cpf' => sprintf('%011d', 10000000000 + $index),
                'preferred_due_day' => 5,
                'contribution_amount' => '45.00',
                'preferred_payment_method' => 'pix',
                'billing_email_opt_in' => 1,
                'profile_completed' => 1,
            ]);
            $memberAuthRepository->approveAndAssignRole($userId, 1, 'Atendimento fraterno', 'efetivo');
        }

        $memberAuthRepository->generateContributionCharges('2026-07', 7);

        $data = $this->renderActionData($memberAuthRepository, '2026-07', [
            'per_page' => '5',
            'page' => '2',
        ]);

        $this->assertCount(1, $data['finance_contributions']);
        $this->assertSame('Contribuinte 06', $data['finance_contributions'][0]['full_name'] ?? null);

        $pagination = $data['finance_contributions_pagination'];
        $this->assertSame(2, $pagination['current_page']);
        $this->assertSame(2, $pagination['total_pages']);
        $this->assertSame(6, $pagination['total_items']);
        $this->assertSame(6, $pagination['start_item']);
        $this->assertSame(6, $pagination['end_item']);
        $this->assertSame('5', $pagination['page_size']);
    }

    /**
     * @param array<string, string> $queryParams
     * @return array<string, mixed>
     */
    private function renderActionData(
        FallbackMemberAuthRepository $memberAuthRepository,
        string $competence,
        array $queryParams = []
    ): array
    {
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
            /** @var array<string, mixed> */
            public array $capturedData = [];

            protected function renderPage(
                ResponseInterface $response,
                string $template,
                array $data = []
            ): ResponseInterface {
                $this->capturedData = $data;

                return $response;
            }
        };

        $request = $this->createRequest('GET', '/painel/financas/contribuicoes')
            ->withQueryParams(array_merge([
                'competence' => $competence,
            ], $queryParams));

        $action($request, new Response());

        return $action->capturedData;
    }
}
