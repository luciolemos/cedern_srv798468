<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminCedeManagementPageAction;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class TestableAdminCedeManagementPageAction extends AdminCedeManagementPageAction
{
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
}

final class AdminCedeManagementPageActionTest extends TestCase
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

    public function testRendersExportButtonAndBuildsExportUrlWithCurrentFilters(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Souza',
            'email' => 'marina@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $userId,
            1,
            'Coordenador(a) do Curso de Mediunidade',
            'efetivo',
            'member',
            true,
            'active'
        );

        /** @var Twig $twig */
        [$action, $twig] = $this->createAction($memberAuthRepository);
        $request = $this->createRequest('GET', '/painel/gestao-cede')->withQueryParams([
            'q' => 'marina',
            'institutional_role' => 'Coordenador(a) do Curso de Mediunidade',
            'status_filter' => 'active',
            'sort' => 'full_name',
            'dir' => 'desc',
        ]);

        $response = $action($request, new Response());

        $html = $twig->fetch($action->capturedTemplate, array_merge($action->capturedData, [
            'base_url' => '',
            'current_path' => '/painel/gestao-cede',
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
        $this->assertSame('pages/admin-cede-management.twig', $action->capturedTemplate);
        $this->assertSame('Usuário SISCEDE', $action->capturedData['cede_management_users'][0]['role_name_display'] ?? null);
        $this->assertStringContainsString('Exportar CSV', $html);
        $this->assertStringContainsString('Usuário SISCEDE', $html);
        $this->assertStringContainsString('/painel/gestao-cede?', $action->capturedData['cede_management_export_csv_url']);
        $this->assertStringContainsString('q=marina', $action->capturedData['cede_management_export_csv_url']);
        $this->assertStringContainsString(
            'institutional_role=Coordenador%28a%29+do+Curso+de+Mediunidade',
            $action->capturedData['cede_management_export_csv_url']
        );
        $this->assertStringContainsString('status_filter=active', $action->capturedData['cede_management_export_csv_url']);
        $this->assertStringContainsString('sort=full_name', $action->capturedData['cede_management_export_csv_url']);
        $this->assertStringContainsString('dir=desc', $action->capturedData['cede_management_export_csv_url']);
        $this->assertStringContainsString('export=csv', $action->capturedData['cede_management_export_csv_url']);
    }

    public function testExportsFilteredManagementUsersAsCsv(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $matchingUserId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Souza',
            'email' => 'marina@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($matchingUserId, [
            'full_name' => 'Marina Souza',
            'phone_mobile' => '11987654321',
            'phone_landline' => '1132654321',
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $matchingUserId,
            1,
            'Coordenador(a) do Curso de Mediunidade',
            'efetivo',
            'member',
            true,
            'active'
        );

        $otherUserId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Bruno Lima',
            'email' => 'bruno@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $otherUserId,
            4,
            'Secretário',
            'fundador',
            'member',
            true,
            'active'
        );

        [$action] = $this->createAction($memberAuthRepository);
        $request = $this->createRequest('GET', '/painel/gestao-cede')->withQueryParams([
            'q' => 'marina',
            'export' => 'csv',
        ]);

        $response = $action($request, new Response());
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename="diretoria-cede-',
            $response->getHeaderLine('Content-Disposition')
        );
        $this->assertStringContainsString(
            'id;nome;email;telefone_fixo;telefone_celular;funcao_cede;tipo_socio;perfil_siscede;acesso_siscede',
            mb_strtolower($body)
        );
        $this->assertStringContainsString('Marina Souza', $body);
        $this->assertStringContainsString('marina@example.com', $body);
        $this->assertStringContainsString('(11) 3265-4321', $body);
        $this->assertStringContainsString('(11) 98765-4321', $body);
        $this->assertStringContainsString('Coordenador(a) do Curso de Mediunidade', $body);
        $this->assertStringContainsString('Efetivo', $body);
        $this->assertStringContainsString('Usuário SISCEDE', $body);
        $this->assertStringContainsString('Ativo', $body);
        $this->assertStringNotContainsString('Bruno Lima', $body);
    }

    /**
     * @return array{0: TestableAdminCedeManagementPageAction, 1: Twig}
     */
    private function createAction(FallbackMemberAuthRepository $memberAuthRepository): array
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return [
            new TestableAdminCedeManagementPageAction(
                $logger,
                $twig,
                $memberAuthRepository
            ),
            $twig,
        ];
    }
}
