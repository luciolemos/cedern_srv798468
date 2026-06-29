<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminFinanceContributionGatewayCreateAction;
use App\Application\Actions\Admin\AdminFinanceContributionGatewayViewPageAction;
use App\Domain\Billing\ContributionBillingGateway;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AdminFinanceContributionGatewayActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [
            'member_user_id' => 17,
        ];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testCreatesGatewayChargeAndPersistsMetadata(): void
    {
        $memberAuthRepository = $this->buildRepositoryWithPendingContribution();
        $gateway = new class () implements ContributionBillingGateway {
            public function isConfigured(): bool
            {
                return true;
            }

            public function providerKey(): string
            {
                return 'asaas';
            }

            public function createCharge(array $member, array $charge, string $billingType): array
            {
                return [
                    'gateway_provider' => 'asaas',
                    'gateway_customer_id' => 'cus_123',
                    'gateway_payment_id' => 'pay_123',
                    'gateway_billing_type' => strtoupper($billingType),
                    'gateway_status' => 'PENDING',
                    'gateway_invoice_url' => 'https://asaas.test/invoice/pay_123',
                    'gateway_bank_slip_url' => null,
                    'gateway_transaction_receipt_url' => null,
                    'gateway_pix_payload' => '000201010212',
                    'gateway_pix_encoded_image' => 'ZmFrZS1xci1jb2Rl',
                    'gateway_pix_expiration_date' => '2026-07-10 23:59:59',
                    'gateway_last_synced_at' => '2026-06-29 12:30:00',
                ];
            }

            public function refreshCharge(array $charge): array
            {
                return [];
            }
        };

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new AdminFinanceContributionGatewayCreateAction(
            $logger,
            $twig,
            $memberAuthRepository,
            $gateway
        );

        $request = $this->createRequest('POST', '/painel/financas/contribuicoes/1/cobranca/criar')
            ->withAttribute('id', 1)
            ->withParsedBody([
                'competence' => '2026-07',
                'billing_type' => 'pix',
            ]);

        $response = $action($request, new Response());
        $charge = $memberAuthRepository->findContributionChargeById(1);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/painel/financas/contribuicoes/1/cobranca', $response->getHeaderLine('Location'));
        $this->assertNotNull($charge);
        $this->assertSame('asaas', $charge['gateway_provider'] ?? null);
        $this->assertSame('pay_123', $charge['gateway_payment_id'] ?? null);
        $this->assertSame('PIX', $charge['gateway_billing_type'] ?? null);
        $this->assertSame('https://asaas.test/invoice/pay_123', $charge['gateway_invoice_url'] ?? null);
    }

    public function testRendersGatewayChargeDetailView(): void
    {
        $memberAuthRepository = $this->buildRepositoryWithPendingContribution();
        $memberAuthRepository->updateContributionGatewayData(1, [
            'gateway_provider' => 'asaas',
            'gateway_customer_id' => 'cus_123',
            'gateway_payment_id' => 'pay_123',
            'gateway_billing_type' => 'PIX',
            'gateway_status' => 'PENDING',
            'gateway_invoice_url' => 'https://asaas.test/invoice/pay_123',
            'gateway_bank_slip_url' => '',
            'gateway_transaction_receipt_url' => '',
            'gateway_pix_payload' => '000201010212',
            'gateway_pix_encoded_image' => 'ZmFrZS1xci1jb2Rl',
            'gateway_pix_expiration_date' => '2026-07-10 23:59:59',
            'gateway_last_synced_at' => '2026-06-29 12:30:00',
        ]);

        $gateway = new class () implements ContributionBillingGateway {
            public function isConfigured(): bool
            {
                return true;
            }

            public function providerKey(): string
            {
                return 'asaas';
            }

            public function createCharge(array $member, array $charge, string $billingType): array
            {
                return [];
            }

            public function refreshCharge(array $charge): array
            {
                return [];
            }
        };

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new class (
            $logger,
            $twig,
            $memberAuthRepository,
            $gateway
        ) extends AdminFinanceContributionGatewayViewPageAction {
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

        $request = $this->createRequest('GET', '/painel/financas/contribuicoes/1/cobranca')
            ->withAttribute('id', 1);

        $response = $action($request, new Response());

        $html = $twig->fetch($action->capturedTemplate, array_merge($action->capturedData, [
            'base_url' => '',
            'current_path' => '/painel/financas/contribuicoes/1/cobranca',
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
        $this->assertSame('pages/admin-finance-contribution-charge.twig', $action->capturedTemplate);
        $this->assertStringEndsWith(
            '/webhooks/asaas/contribuicoes',
            (string) ($action->capturedData['finance_contribution_charge']['gateway_webhook_url'] ?? '')
        );
        $this->assertStringContainsString('Marina Silva', $html);
        $this->assertStringContainsString('pay_123', $html);
        $this->assertStringContainsString('Abrir cobrança', $html);
        $this->assertStringContainsString('Copia e cola Pix', $html);
        $this->assertStringContainsString('000201010212', $html);
        $this->assertStringContainsString('10/07/2026 23:59', $html);
        $this->assertStringContainsString('Webhook desta instalação', $html);
    }

    private function buildRepositoryWithPendingContribution(): FallbackMemberAuthRepository
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Silva',
            'email' => 'marina@example.com',
            'password_hash' => 'hash',
        ]);

        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Marina Silva',
            'cpf' => '52998224725',
            'phone_mobile' => '84999998888',
            'preferred_due_day' => 10,
            'contribution_amount' => '65.50',
            'preferred_payment_method' => 'pix',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, 'Atendimento fraterno', 'efetivo');
        $memberAuthRepository->generateContributionCharges('2026-07', 7);

        return $memberAuthRepository;
    }
}
