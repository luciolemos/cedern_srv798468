<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Page;

use App\Application\Actions\Page\MemberCompleteProfilePdfAction;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class MemberCompleteProfilePdfActionTest extends TestCase
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

    public function testGeneratesPdfResponseUsingCurrentFormValues(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Associado Original',
            'email' => 'original@example.com',
            'password_hash' => 'hash',
        ]);

        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Associado Original',
            'phone_mobile' => '84999990001',
            'phone_landline' => '8432101111',
            'birth_date' => '1992-08-18',
            'birth_place' => 'Natal/RN',
            'cpf' => '12345678909',
            'postal_code' => '59000000',
            'street_address' => 'Rua Um',
            'address_number' => '100',
            'address_complement' => 'Casa',
            'neighborhood' => 'Centro',
            'address_city' => 'Natal',
            'address_state' => 'RN',
            'preferred_due_day' => 10,
            'contribution_amount' => '45.50',
            'contribution_plan_label' => 'Plano base',
            'preferred_payment_method' => 'boleto',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 0,
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $userId,
            1,
            'Sem função definida',
            'efetivo',
            'member',
            true,
            'active'
        );

        $presidentUserId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Presidente Teste CEDE',
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

        $_SESSION['member_authenticated'] = true;
        $_SESSION['member_user_id'] = $userId;

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new class ($logger, $twig, $memberAuthRepository) extends MemberCompleteProfilePdfAction {
            public string $capturedHtml = '';

            protected function renderPdfFromHtml(string $html): string
            {
                $this->capturedHtml = $html;

                return '%PDF-TEST%';
            }
        };

        $request = $this->createRequest('POST', '/membro/perfil/completar/pdf', ['HTTP_ACCEPT' => 'application/pdf'])
            ->withParsedBody([
                'full_name' => 'Nome para PDF',
                'phone_mobile' => '(84) 99999-4321',
                'phone_landline' => '',
                'birth_date' => '2001-01-05',
                'birth_state' => 'PB',
                'birth_city' => 'Joao Pessoa',
                'birth_place' => '',
                'cpf' => '98765432100',
                'postal_code' => '58000-123',
                'street_address' => 'Avenida Principal',
                'address_number' => '321',
                'address_complement' => 'Apto 12',
                'neighborhood' => 'Centro',
                'address_city' => 'Joao Pessoa',
                'address_state' => 'PB',
                'preferred_due_day' => '12',
                'contribution_amount' => '88,90',
                'contribution_plan_label' => 'Plano especial',
                'preferred_payment_method' => 'pix',
                'billing_whatsapp_opt_in' => '1',
                'privacy_notice_acknowledged' => '1',
            ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString(
            'formulario-cadastro-associado.pdf',
            $response->getHeaderLine('Content-Disposition')
        );
        $this->assertSame('%PDF-TEST%', (string) $response->getBody());
        $this->assertStringContainsString('FORMULÁRIO DE CADASTRO DE ASSOCIADO', $action->capturedHtml);
        $this->assertStringContainsString('class="pdf-brand-logo"', $action->capturedHtml);
        $this->assertStringContainsString('src="data:image/png;base64,', $action->capturedHtml);
        $this->assertStringContainsString('Nome para PDF', $action->capturedHtml);
        $this->assertStringContainsString('05/01/2001', $action->capturedHtml);
        $this->assertStringContainsString('Dia 12', $action->capturedHtml);
        $this->assertStringContainsString('R$ 88,90', $action->capturedHtml);
        $this->assertStringContainsString('Pix', $action->capturedHtml);
        $this->assertStringContainsString('Usuário SISCEDE', $action->capturedHtml);
        $this->assertStringContainsString('Sem função definida', $action->capturedHtml);
        $this->assertStringContainsString('Nome para PDF', $action->capturedHtml);
        $this->assertStringContainsString('Associado(a)', $action->capturedHtml);
        $this->assertStringContainsString('Presidente Teste CEDE', $action->capturedHtml);
        $this->assertStringContainsString('Presidente do CEDE', $action->capturedHtml);
        $this->assertSame(1, substr_count($action->capturedHtml, '<div class="pdf-field-grid">'));
        $this->assertSame(1, substr_count($action->capturedHtml, '<div class="pdf-field-grid is-address">'));
        $this->assertSame(1, substr_count($action->capturedHtml, '<div class="pdf-field-grid is-finance">'));

        $addressSectionStart = strpos($action->capturedHtml, '<h2>Endereço</h2>');
        $financeSectionStart = strpos($action->capturedHtml, '<h2>Contribuição e cobrança</h2>');
        $this->assertNotFalse($addressSectionStart);
        $this->assertNotFalse($financeSectionStart);

        $addressHtml = substr($action->capturedHtml, $addressSectionStart);
        $financeHtml = substr($action->capturedHtml, $financeSectionStart);

        $cepPosition = strpos($addressHtml, 'CEP');
        $ufPosition = strpos($addressHtml, 'UF');
        $cidadePosition = strpos($addressHtml, 'Cidade');
        $logradouroPosition = strpos($addressHtml, 'Logradouro');
        $numeroPosition = strpos($addressHtml, 'Número');
        $bairroPosition = strpos($addressHtml, 'Bairro');
        $complementoPosition = strpos($addressHtml, 'Complemento');
        $privacidadePosition = strpos($financeHtml, 'Ciência da privacidade');
        $planoPosition = strpos($financeHtml, 'Plano definido pela diretoria');

        $this->assertNotFalse($cepPosition);
        $this->assertNotFalse($ufPosition);
        $this->assertNotFalse($cidadePosition);
        $this->assertNotFalse($logradouroPosition);
        $this->assertNotFalse($numeroPosition);
        $this->assertNotFalse($bairroPosition);
        $this->assertNotFalse($complementoPosition);
        $this->assertNotFalse($privacidadePosition);
        $this->assertNotFalse($planoPosition);
        $this->assertLessThan($cepPosition, $logradouroPosition);
        $this->assertLessThan($numeroPosition, $logradouroPosition);
        $this->assertLessThan($bairroPosition, $numeroPosition);
        $this->assertLessThan($complementoPosition, $bairroPosition);
        $this->assertLessThan($cidadePosition, $complementoPosition);
        $this->assertLessThan($ufPosition, $cidadePosition);
        $this->assertLessThan($cepPosition, $ufPosition);
        $this->assertLessThan($planoPosition, $privacidadePosition);
    }

    public function testFallsBackToPrintableHtmlWhenPdfGeneratorFails(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Associado Fallback',
            'email' => 'fallback@example.com',
            'password_hash' => 'hash',
        ]);

        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Associado Fallback',
            'phone_mobile' => '84999990001',
            'birth_date' => '1992-08-18',
            'birth_place' => 'Natal/RN',
            'cpf' => '12345678909',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $userId,
            1,
            'Sem função definida',
            'efetivo',
            'member',
            true,
            'active'
        );

        $_SESSION['member_authenticated'] = true;
        $_SESSION['member_user_id'] = $userId;

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new class ($logger, $twig, $memberAuthRepository) extends MemberCompleteProfilePdfAction {
            protected function renderPdfFromHtml(string $html): string
            {
                throw new \RuntimeException('pdf-unavailable');
            }
        };

        $request = $this->createRequest('GET', '/membro/perfil/completar/pdf', ['HTTP_ACCEPT' => 'application/pdf']);
        $response = $action($request, new Response());
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('html', $response->getHeaderLine('X-Cede-Document-Fallback'));
        $this->assertStringContainsString('FORMULÁRIO DE CADASTRO DE ASSOCIADO', $body);
        $this->assertStringContainsString('Use a impressão do navegador para salvar em PDF.', $body);
        $this->assertStringContainsString('Associado Fallback', $body);
    }
}
