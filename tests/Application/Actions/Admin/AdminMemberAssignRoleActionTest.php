<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminMemberAssignRoleAction;
use App\Domain\Member\MemberAuthRepository;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AdminMemberAssignRoleActionTest extends TestCase
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

    public function testAcceptsMediunityCourseCoordinatorInstitutionalRole(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Claudia Rocha',
            'email' => 'claudia@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, 'Coordenador', 'efetivo', 'member', true, 'active');

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new AdminMemberAssignRoleAction(
            $logger,
            $twig,
            $memberAuthRepository
        );

        $request = $this->createRequest('POST', '/painel/usuarios/' . $userId . '/atribuir-perfil')
            ->withAttribute('id', $userId)
            ->withParsedBody([
                'role_id' => '1',
                'institutional_role' => 'Coordenador(a) do Curso de Mediunidade',
                'member_type' => 'efetivo',
                'association_status' => 'member',
                'account_status' => 'active',
                'is_contributor' => '1',
                'contribution_amount' => '65,50',
                'contribution_plan_label' => 'Plano coordenacao',
                'redirect_to' => '/painel/usuarios/' . $userId . '/resumo',
            ]);

        $response = $action($request, new Response());
        $updatedUser = $memberAuthRepository->findById($userId);
        $flash = $_SESSION['_codex_flash']['admin_member_user_summary_' . $userId] ?? null;

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/usuarios/' . $userId . '/resumo', $response->getHeaderLine('Location'));
        $this->assertIsArray($flash);
        $this->assertSame('approved', $flash['status'] ?? null);
        $this->assertIsArray($updatedUser);
        $this->assertSame(
            'Coordenador(a) do Curso de Mediunidade',
            $updatedUser['institutional_role'] ?? null
        );
        $this->assertSame('65.50', (string) ($updatedUser['contribution_amount'] ?? ''));
        $this->assertSame('Plano coordenacao', $updatedUser['contribution_plan_label'] ?? null);
    }

    public function testAcceptsSecondSecretaryInstitutionalRole(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Carlos Secretaria',
            'email' => 'carlos.secretaria@example.com',
            'password_hash' => 'hash',
        ]);

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new AdminMemberAssignRoleAction(
            $logger,
            $twig,
            $memberAuthRepository
        );

        $request = $this->createRequest('POST', '/painel/usuarios/' . $userId . '/atribuir-perfil')
            ->withAttribute('id', $userId)
            ->withParsedBody([
                'role_id' => '1',
                'institutional_role' => '2º Secretário',
                'member_type' => 'efetivo',
                'association_status' => 'member',
                'account_status' => 'active',
                'is_contributor' => '0',
                'redirect_to' => '/painel/usuarios/' . $userId . '/resumo',
            ]);

        $response = $action($request, new Response());
        $updatedUser = $memberAuthRepository->findById($userId);
        $flash = $_SESSION['_codex_flash']['admin_member_user_summary_' . $userId] ?? null;

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/usuarios/' . $userId . '/resumo', $response->getHeaderLine('Location'));
        $this->assertIsArray($flash);
        $this->assertSame('approved', $flash['status'] ?? null);
        $this->assertIsArray($updatedUser);
        $this->assertSame('2º Secretário', $updatedUser['institutional_role'] ?? null);
    }

    public function testPersistsAdministrativeFinancialConfigurationForContributor(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Gestora',
            'email' => 'marina.gestora@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, null, 'efetivo', 'member', true, 'active');
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Marina Gestora',
            'preferred_due_day' => 12,
            'preferred_payment_method' => 'pix',
            'contribution_amount' => '45.00',
            'contribution_plan_label' => 'Plano anterior',
        ]);

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new AdminMemberAssignRoleAction(
            $logger,
            $twig,
            $memberAuthRepository
        );

        $request = $this->createRequest('POST', '/painel/usuarios/' . $userId . '/atribuir-perfil')
            ->withAttribute('id', $userId)
            ->withParsedBody([
                'role_id' => '1',
                'institutional_role' => 'Coordenador',
                'member_type' => 'efetivo',
                'association_status' => 'member',
                'account_status' => 'active',
                'is_contributor' => '1',
                'contribution_amount' => '88,90',
                'contribution_plan_label' => 'Plano diretoria 2026',
                'redirect_to' => '/painel/usuarios/' . $userId . '/resumo',
            ]);

        $response = $action($request, new Response());
        $updatedUser = $memberAuthRepository->findById($userId);
        $flash = $_SESSION['_codex_flash']['admin_member_user_summary_' . $userId] ?? null;

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/usuarios/' . $userId . '/resumo', $response->getHeaderLine('Location'));
        $this->assertIsArray($flash);
        $this->assertSame('approved', $flash['status'] ?? null);
        $this->assertIsArray($updatedUser);
        $this->assertSame('88.90', (string) ($updatedUser['contribution_amount'] ?? ''));
        $this->assertSame('Plano diretoria 2026', $updatedUser['contribution_plan_label'] ?? null);
        $this->assertSame(12, (int) ($updatedUser['preferred_due_day'] ?? 0));
        $this->assertSame('pix', $updatedUser['preferred_payment_method'] ?? null);
    }

    public function testClearsAdministrativeFinancialConfigurationWhenContributionIsDisabled(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Paulo Nao Contribuinte',
            'email' => 'paulo.nao.contribuinte@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, null, 'efetivo', 'member', true, 'active');
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Paulo Nao Contribuinte',
            'contribution_amount' => '72.40',
            'contribution_plan_label' => 'Plano legado',
        ]);

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new AdminMemberAssignRoleAction(
            $logger,
            $twig,
            $memberAuthRepository
        );

        $request = $this->createRequest('POST', '/painel/usuarios/' . $userId . '/atribuir-perfil')
            ->withAttribute('id', $userId)
            ->withParsedBody([
                'role_id' => '1',
                'member_type' => 'efetivo',
                'association_status' => 'member',
                'account_status' => 'active',
                'is_contributor' => '0',
                'redirect_to' => '/painel/usuarios/' . $userId . '/resumo',
            ]);

        $response = $action($request, new Response());
        $updatedUser = $memberAuthRepository->findById($userId);
        $flash = $_SESSION['_codex_flash']['admin_member_user_summary_' . $userId] ?? null;

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/usuarios/' . $userId . '/resumo', $response->getHeaderLine('Location'));
        $this->assertIsArray($flash);
        $this->assertSame('approved', $flash['status'] ?? null);
        $this->assertIsArray($updatedUser);
        $this->assertNull($updatedUser['contribution_amount'] ?? null);
        $this->assertNull($updatedUser['contribution_plan_label'] ?? null);
    }

    public function testRedirectsWithAssignErrorWhenRepositoryDoesNotPersistChanges(): void
    {
        $memberAuthRepositoryProphecy = $this->prophesize(MemberAuthRepository::class);
        $memberAuthRepositoryProphecy->findById(13)->willReturn([
            'id' => 13,
            'full_name' => 'Usuario Producao',
            'email' => 'usuario@example.com',
            'status' => 'blocked',
            'association_status' => 'member',
            'is_contributor' => 0,
            'member_type' => null,
            'role_id' => null,
            'institutional_role' => null,
        ]);
        $memberAuthRepositoryProphecy->approveAndAssignRole(
            13,
            4,
            'Coordenador',
            'efetivo',
            'member',
            false,
            'active',
            null
        )->willReturn(false);

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new AdminMemberAssignRoleAction(
            $logger,
            $twig,
            $memberAuthRepositoryProphecy->reveal()
        );

        $request = $this->createRequest('POST', '/painel/usuarios/13/atribuir-perfil')
            ->withAttribute('id', 13)
            ->withParsedBody([
                'role_id' => '4',
                'institutional_role' => 'Coordenador',
                'member_type' => 'efetivo',
                'association_status' => 'member',
                'account_status' => 'active',
                'is_contributor' => '0',
                'redirect_to' => '/painel/usuarios/13/resumo',
            ]);

        $response = $action($request, new Response());
        $flash = $_SESSION['_codex_flash']['admin_member_user_summary_13'] ?? null;

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/usuarios/13/resumo', $response->getHeaderLine('Location'));
        $this->assertIsArray($flash);
        $this->assertSame('assign-error', $flash['status'] ?? null);
    }
}
