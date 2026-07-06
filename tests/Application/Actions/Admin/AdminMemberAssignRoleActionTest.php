<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminMemberAssignRoleAction;
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
    }
}
