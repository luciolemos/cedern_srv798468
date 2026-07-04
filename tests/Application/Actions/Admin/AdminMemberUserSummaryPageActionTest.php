<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminMemberUserSummaryPageAction;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AdminMemberUserSummaryPageActionTest extends TestCase
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

    public function testRendersFinanceOperatorInSystemProfileSelect(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Alice Financeira',
            'email' => 'alice.financeira@example.com',
            'password_hash' => 'hash',
        ]);

        $memberAuthRepository->approveAndAssignRole($userId, 1, 'Coordenador', 'efetivo');
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Alice Financeira',
            'birth_date' => '1990-08-12',
            'preferred_due_day' => 10,
            'contribution_amount' => '65.50',
            'preferred_payment_method' => 'pix',
        ]);

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
        ) extends AdminMemberUserSummaryPageAction {
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

        $request = $this->createRequest('GET', '/painel/usuarios/' . $userId . '/resumo')
            ->withAttribute('id', $userId);

        $response = $action($request, new Response());

        $html = $twig->fetch($action->capturedTemplate, array_merge($action->capturedData, [
            'base_url' => '',
            'current_path' => '/painel/usuarios/' . $userId . '/resumo',
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
        $this->assertSame('pages/admin-member-user-summary.twig', $action->capturedTemplate);
        $this->assertStringContainsString('Configuração CEDE', $html);
        $this->assertStringContainsString('Configuração SISCEDE', $html);
        $this->assertStringContainsString('Perfil no SISCEDE', $html);
        $this->assertStringContainsString('Acesso ao SISCEDE', $html);
        $this->assertStringContainsString('Operador Financeiro', $html);
        $this->assertMatchesRegularExpression('/<option value="6"[^>]*>Operador Financeiro<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<option\s+value="pending"[^>]*data-summary-pending-option[^>]*hidden[^>]*disabled[^>]*>Pendente<\/option>/', $html);
        $this->assertStringContainsString('Com vínculo Associado na CEDE, este cadastro pode receber perfil e acesso no SISCEDE.', $html);
        $this->assertStringContainsString('Entender regras do vínculo associativo', $html);
        $this->assertStringContainsString('Apenas Associado permite configurar manualmente o SISCEDE.', $html);
        $this->assertStringContainsString('Entender regras da função no CEDE', $html);
        $this->assertStringContainsString('A função no CEDE só permanece se o campo Vínculo associativo estiver como Associado e o Acesso ao SISCEDE estiver Ativo.', $html);
        $this->assertStringContainsString('Funções exclusivas exigem que não exista outro usuário ativo ocupando o mesmo cargo.', $html);
        $this->assertStringContainsString('Entender regras do tipo de sócio', $html);
        $this->assertStringContainsString('Definições do Estatuto do CEDE', $html);
        $this->assertStringContainsString('Fundador: associado que participou da Assembleia de fundação do CEDE.', $html);
        $this->assertStringContainsString('Efetivo: associado cuja proposta de admissão foi aprovada pela Diretoria, conforme o Estatuto.', $html);
        $this->assertStringContainsString('Configuração financeira', $html);
        $this->assertStringContainsString('12/08/1990', $html);
        $this->assertStringContainsString('Dia do vencimento da cobrança', $html);
        $this->assertStringContainsString('Dia 10', $html);
        $this->assertStringContainsString('Forma definida de pagamento', $html);
        $this->assertStringContainsString('Pix', $html);
        $this->assertStringContainsString('Valor da contribuição', $html);
        $this->assertStringContainsString('R$ 65,50', $html);
    }

    public function testExplainsDerivedSiscedeStateForApplicant(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Bruna Solicitante',
            'email' => 'bruna.solicitante@example.com',
            'password_hash' => 'hash',
        ]);

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
        ) extends AdminMemberUserSummaryPageAction {
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

        $request = $this->createRequest('GET', '/painel/usuarios/' . $userId . '/resumo')
            ->withAttribute('id', $userId);

        $response = $action($request, new Response());

        $html = $twig->fetch($action->capturedTemplate, array_merge($action->capturedData, [
            'base_url' => '',
            'current_path' => '/painel/usuarios/' . $userId . '/resumo',
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
        $this->assertStringContainsString('Configuração CEDE', $html);
        $this->assertStringContainsString('Configuração SISCEDE', $html);
        $this->assertStringContainsString('Perfil no SISCEDE', $html);
        $this->assertStringContainsString('Acesso ao SISCEDE', $html);
        $this->assertStringContainsString('Enquanto o vínculo na CEDE for Solicitante, o SISCEDE mantém', $html);
        $this->assertStringContainsString('Sem perfil liberado', $html);
        $this->assertStringContainsString('Acesso pendente', $html);
        $this->assertStringContainsString('Enquanto o vínculo na CEDE for Solicitante, o SISCEDE mantém esta conta sem perfil liberado.', $html);
        $this->assertStringContainsString('Enquanto o vínculo na CEDE for Solicitante, o SISCEDE mantém esta conta com acesso pendente.', $html);
        $this->assertMatchesRegularExpression('/<option\s+value="pending"[^>]*selected[^>]*data-summary-pending-option[^>]*>Pendente<\/option>/', $html);
    }
}
