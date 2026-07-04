<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminMemberUserPdfAction;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AdminMemberUserPdfActionTest extends TestCase
{
    public function testGeneratesAdministrativePdfForSelectedUser(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Helena Martins',
            'email' => 'helena@example.com',
            'password_hash' => 'hash',
        ]);

        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Helena Martins',
            'phone_mobile' => '84999994444',
            'phone_landline' => '8433554400',
            'birth_date' => '1988-03-25',
            'birth_place' => 'Natal/RN',
            'cpf' => '52998224725',
            'postal_code' => '59000000',
            'street_address' => 'Rua das Acacias',
            'address_number' => '25',
            'address_complement' => 'Casa',
            'neighborhood' => 'Centro',
            'address_city' => 'Natal',
            'address_state' => 'RN',
            'preferred_due_day' => 8,
            'contribution_amount' => '72.40',
            'contribution_plan_label' => 'Plano diretoria',
            'preferred_payment_method' => 'boleto',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'privacy_notice_accepted_at' => '2026-07-01 10:30:00',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $userId,
            1,
            'Coordenador',
            'efetivo',
            'member',
            true,
            'active'
        );

        $presidentUserId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Presidente CEDE Teste',
            'email' => 'presidente@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $presidentUserId,
            1,
            'Presidente CEDE',
            'efetivo',
            'member',
            true,
            'active'
        );

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new class ($logger, $twig, $memberAuthRepository) extends AdminMemberUserPdfAction {
            public string $capturedHtml = '';

            protected function renderPdfFromHtml(string $html): string
            {
                $this->capturedHtml = $html;

                return '%PDF-ADMIN-TEST%';
            }
        };

        $request = $this->createRequest('GET', '/painel/usuarios/' . $userId . '/pdf', ['HTTP_ACCEPT' => 'application/pdf'])
            ->withAttribute('id', $userId);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString(
            'formulario-cadastro-associado.pdf',
            $response->getHeaderLine('Content-Disposition')
        );
        $this->assertSame('%PDF-ADMIN-TEST%', (string) $response->getBody());
        $this->assertStringContainsString('FORMULÁRIO DE CADASTRO DE ASSOCIADO', $action->capturedHtml);
        $this->assertStringContainsString('Helena Martins', $action->capturedHtml);
        $this->assertStringContainsString('25/03/1988', $action->capturedHtml);
        $this->assertStringContainsString('529.982.247-25', $action->capturedHtml);
        $this->assertStringContainsString('Dia 08', $action->capturedHtml);
        $this->assertStringContainsString('R$ 72,40', $action->capturedHtml);
        $this->assertStringContainsString('Boleto', $action->capturedHtml);
        $this->assertStringContainsString('Plano diretoria', $action->capturedHtml);
        $this->assertStringContainsString('Coordenador', $action->capturedHtml);
        $this->assertStringContainsString('Associado(a)', $action->capturedHtml);
        $this->assertStringContainsString('Presidente CEDE Teste', $action->capturedHtml);
        $this->assertStringContainsString('Presidente do CEDE', $action->capturedHtml);
        $this->assertStringContainsString('/painel/usuarios/' . $userId, $action->capturedHtml);
    }
}
