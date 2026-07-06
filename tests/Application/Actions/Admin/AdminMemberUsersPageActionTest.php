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

final class TestableAdminMemberUsersPageAction extends AdminMemberUsersPageAction
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
        /** @var Twig $twig */
        [$action, $twig] = $this->createAction($memberAuthRepository);

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
        $this->assertSame(2, $action->capturedData['member_users_summary']['total_count']);
        $this->assertSame(1, $action->capturedData['member_users_summary']['applicant_count']);
        $this->assertSame(1, $action->capturedData['member_users_summary']['former_count']);
        $this->assertSame(1, $action->capturedData['member_users_summary']['blocked_count']);
        $this->assertStringContainsString('Perfil SISCEDE', $html);
        $this->assertStringContainsString('Acesso ao SISCEDE', $html);
        $this->assertStringContainsString('Rafaela Nunes', $html);
        $this->assertStringContainsString('Solicitante', $html);
        $this->assertStringContainsString('Sem perfil liberado', $html);
        $this->assertStringContainsString('Carlos Mendes', $html);
        $this->assertStringContainsString('Desligado', $html);
        $this->assertStringContainsString('Sem perfil ativo', $html);
        $this->assertStringContainsString('Painel de pessoas', $html);
        $this->assertStringContainsString('Cadastros no recorte', $html);
        $this->assertStringContainsString('0 associados, 1 solicitantes e 1 desligados.', $html);
        $this->assertStringContainsString('Exportar CSV', $html);
        $this->assertStringContainsString('/painel/usuarios/1/pdf', $html);
    }

    public function testBuildsExportUrlWithCurrentFilters(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Souza',
            'email' => 'marina@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, 'Coordenador', 'efetivo', 'member', true, 'active');

        [$action] = $this->createAction($memberAuthRepository);
        $request = $this->createRequest('GET', '/painel/usuarios')->withQueryParams([
            'q' => 'marina',
            'role_filter' => 'member',
            'member_type_filter' => 'efetivo',
            'status_filter' => 'active',
            'association_status_filter' => 'member',
            'contributor_filter' => 'yes',
            'institutional_role_filter' => 'Coordenador',
            'sort' => 'full_name',
            'dir' => 'asc',
        ]);

        $action($request, new Response());

        $this->assertStringContainsString('/painel/usuarios?', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('q=marina', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('role_filter=member', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('member_type_filter=efetivo', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('status_filter=active', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('association_status_filter=member', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('contributor_filter=yes', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('institutional_role_filter=Coordenador', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('sort=full_name', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('dir=asc', $action->capturedData['member_users_export_csv_url']);
        $this->assertStringContainsString('export=csv', $action->capturedData['member_users_export_csv_url']);
    }

    public function testExportsFilteredUsersAsCsv(): void
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
        $memberAuthRepository->approveAndAssignRole($matchingUserId, 1, 'Coordenador', 'efetivo', 'member', true, 'active');

        $otherUserId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Bruno Lima',
            'email' => 'bruno@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole($otherUserId, 4, 'Secretário', 'efetivo', 'member', false, 'blocked');

        [$action] = $this->createAction($memberAuthRepository);
        $request = $this->createRequest('GET', '/painel/usuarios')->withQueryParams([
            'q' => 'marina',
            'export' => 'csv',
        ]);

        $response = $action($request, new Response());
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="pessoas-cede-', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('id;nome;email;telefone_fixo;telefone_celular;vinculo;tipo_socio;contribuinte;funcao_cede;perfil_siscede;acesso_siscede', mb_strtolower($body));
        $this->assertStringContainsString('Marina Souza', $body);
        $this->assertStringContainsString('marina@example.com', $body);
        $this->assertStringContainsString('(11) 3265-4321', $body);
        $this->assertStringContainsString('(11) 98765-4321', $body);
        $this->assertStringContainsString('Coordenador', $body);
        $this->assertStringContainsString('Usuário SISCEDE', $body);
        $this->assertStringContainsString('Ativo', $body);
        $this->assertStringNotContainsString('Bruno Lima', $body);
    }

    /**
     * @return array{0: TestableAdminMemberUsersPageAction, 1: Twig}
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
            new TestableAdminMemberUsersPageAction(
                $logger,
                $twig,
                $memberAuthRepository
            ),
            $twig,
        ];
    }
}
