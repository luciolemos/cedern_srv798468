<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Page;

use App\Application\Actions\Page\MemberCompleteProfilePageAction;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class MemberCompleteProfilePageActionTest extends TestCase
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

    public function testRejectsDuplicateCpfWhenCompletingProfile(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        $existingUserId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Associado Existente',
            'email' => 'existente@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($existingUserId, [
            'full_name' => 'Associado Existente',
            'phone_mobile' => '84999990001',
            'birth_date' => '1980-05-10',
            'birth_place' => 'Natal/RN',
            'cpf' => $this->generateValidCpf('123456789'),
            'postal_code' => '59000000',
            'street_address' => 'Rua Um',
            'address_number' => '10',
            'neighborhood' => 'Centro',
            'address_city' => 'Natal',
            'address_state' => 'RN',
            'profile_photo_path' => 'media/membros/existente.jpg',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $existingUserId,
            1,
            'Atendimento fraterno',
            'efetivo',
            'member',
            true,
            'active'
        );

        $currentUserId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Associado Atual',
            'email' => 'atual@example.com',
            'password_hash' => 'hash',
        ]);
        $currentCpf = $this->generateValidCpf('987654321');
        $memberAuthRepository->updateProfile($currentUserId, [
            'full_name' => 'Associado Atual',
            'phone_mobile' => '84999990002',
            'birth_date' => '1990-08-12',
            'birth_place' => 'Mossoro/RN',
            'cpf' => $currentCpf,
            'postal_code' => '59000001',
            'street_address' => 'Rua Dois',
            'address_number' => '20',
            'neighborhood' => 'Alecrim',
            'address_city' => 'Natal',
            'address_state' => 'RN',
            'profile_photo_path' => 'media/membros/atual.jpg',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $currentUserId,
            1,
            'Atendimento fraterno',
            'efetivo',
            'member',
            true,
            'active'
        );

        $_SESSION['member_authenticated'] = true;
        $_SESSION['member_user_id'] = $currentUserId;

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new MemberCompleteProfilePageAction($logger, $twig, $memberAuthRepository);

        $duplicateCpf = (string) ($memberAuthRepository->findById($existingUserId)['cpf'] ?? '');

        $request = $this->createRequest('POST', '/membro/perfil/completar', ['HTTP_ACCEPT' => 'text/html'])
            ->withParsedBody([
                'full_name' => 'Associado Atual',
                'phone_mobile' => '(84) 99999-0002',
                'phone_landline' => '',
                'birth_date' => '1990-08-12',
                'birth_state' => 'RN',
                'birth_city' => 'Mossoro',
                'birth_place' => '',
                'cpf' => $duplicateCpf,
                'postal_code' => '59000-001',
                'street_address' => 'Rua Dois',
                'address_number' => '20',
                'address_complement' => '',
                'neighborhood' => 'Alecrim',
                'address_city' => 'Natal',
                'address_state' => 'RN',
                'preferred_due_day' => '10',
                'contribution_amount' => '65,50',
                'contribution_plan_label' => '',
                'preferred_payment_method' => 'pix',
                'billing_email_opt_in' => '1',
                'billing_whatsapp_opt_in' => '1',
                'privacy_notice_acknowledged' => '1',
            ]);

        $response = $action($request, new Response());
        $updatedUser = $memberAuthRepository->findById($currentUserId);
        $flash = $_SESSION['_codex_flash']['member_complete_profile'] ?? [];
        $errors = is_array($flash) ? (array) ($flash['errors'] ?? []) : [];

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/membro/perfil/completar', $response->getHeaderLine('Location'));
        $this->assertContains('Este CPF já está vinculado a outro usuário SISCEDE.', $errors);
        $this->assertSame($currentCpf, (string) ($updatedUser['cpf'] ?? ''));
    }

    public function testNonContributorCanSaveProfileWithoutBillingPreferencesAndKeepsExistingBillingFlags(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Associado Sem Cobranca',
            'email' => 'sem-cobranca@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Associado Sem Cobranca',
            'phone_mobile' => '84999990003',
            'birth_date' => '1991-07-10',
            'birth_place' => 'Natal/RN',
            'cpf' => $this->generateValidCpf('456789123'),
            'postal_code' => '59000002',
            'street_address' => 'Rua Tres',
            'address_number' => '30',
            'neighborhood' => 'Lagoa Nova',
            'address_city' => 'Natal',
            'address_state' => 'RN',
            'preferred_due_day' => 8,
            'contribution_amount' => '70.00',
            'contribution_plan_label' => 'Plano legado',
            'preferred_payment_method' => 'pix',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'profile_photo_path' => 'media/membros/sem-cobranca.jpg',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $userId,
            1,
            'Atendimento fraterno',
            'efetivo',
            'member',
            false,
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

        $action = new MemberCompleteProfilePageAction($logger, $twig, $memberAuthRepository);

        $request = $this->createRequest('POST', '/membro/perfil/completar', ['HTTP_ACCEPT' => 'text/html'])
            ->withParsedBody($this->buildValidProfilePayload([
                'full_name' => 'Associado Sem Cobranca Atualizado',
                'phone_mobile' => '(84) 99999-0003',
                'birth_date' => '1991-07-10',
                'birth_state' => 'RN',
                'birth_city' => 'Natal',
                'cpf' => $this->generateValidCpf('456789123'),
                'postal_code' => '59000-002',
                'street_address' => 'Rua Tres',
                'address_number' => '30',
                'neighborhood' => 'Lagoa Nova',
                'address_city' => 'Natal',
                'address_state' => 'RN',
                'preferred_due_day' => '',
                'preferred_payment_method' => '',
            ]));

        $response = $action($request, new Response());
        $updatedUser = $memberAuthRepository->findById($userId);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/membro', $response->getHeaderLine('Location'));
        $this->assertSame(8, (int) ($updatedUser['preferred_due_day'] ?? 0));
        $this->assertSame('pix', (string) ($updatedUser['preferred_payment_method'] ?? ''));
        $this->assertSame(1, (int) ($updatedUser['billing_email_opt_in'] ?? 0));
        $this->assertSame(1, (int) ($updatedUser['billing_whatsapp_opt_in'] ?? 0));
    }

    public function testContributorCannotOverrideAdministrativeAmountOrPlanFromProfileForm(): void
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();

        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Associado Contribuinte',
            'email' => 'contribuinte@example.com',
            'password_hash' => 'hash',
        ]);
        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Associado Contribuinte',
            'phone_mobile' => '84999990004',
            'birth_date' => '1988-04-22',
            'birth_place' => 'Natal/RN',
            'cpf' => $this->generateValidCpf('741852963'),
            'postal_code' => '59000003',
            'street_address' => 'Rua Quatro',
            'address_number' => '40',
            'neighborhood' => 'Tirol',
            'address_city' => 'Natal',
            'address_state' => 'RN',
            'preferred_due_day' => 10,
            'contribution_amount' => '55.00',
            'contribution_plan_label' => 'Plano diretoria',
            'preferred_payment_method' => 'pix',
            'billing_email_opt_in' => 0,
            'billing_whatsapp_opt_in' => 0,
            'profile_photo_path' => 'media/membros/contribuinte.jpg',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole(
            $userId,
            1,
            'Atendimento fraterno',
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

        $action = new MemberCompleteProfilePageAction($logger, $twig, $memberAuthRepository);

        $request = $this->createRequest('POST', '/membro/perfil/completar', ['HTTP_ACCEPT' => 'text/html'])
            ->withParsedBody($this->buildValidProfilePayload([
                'full_name' => 'Associado Contribuinte Atualizado',
                'phone_mobile' => '(84) 99999-0004',
                'birth_date' => '1988-04-22',
                'birth_state' => 'RN',
                'birth_city' => 'Natal',
                'cpf' => $this->generateValidCpf('741852963'),
                'postal_code' => '59000-003',
                'street_address' => 'Rua Quatro',
                'address_number' => '40',
                'neighborhood' => 'Tirol',
                'address_city' => 'Natal',
                'address_state' => 'RN',
                'preferred_due_day' => '14',
                'preferred_payment_method' => 'manual',
                'contribution_amount' => '99,90',
                'contribution_plan_label' => 'Plano adulterado',
                'billing_email_opt_in' => '1',
            ]));

        $response = $action($request, new Response());
        $updatedUser = $memberAuthRepository->findById($userId);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/membro', $response->getHeaderLine('Location'));
        $this->assertSame('55.00', (string) ($updatedUser['contribution_amount'] ?? ''));
        $this->assertSame('Plano diretoria', (string) ($updatedUser['contribution_plan_label'] ?? ''));
        $this->assertSame(14, (int) ($updatedUser['preferred_due_day'] ?? 0));
        $this->assertSame('manual', (string) ($updatedUser['preferred_payment_method'] ?? ''));
        $this->assertSame(1, (int) ($updatedUser['billing_email_opt_in'] ?? 0));
        $this->assertSame(0, (int) ($updatedUser['billing_whatsapp_opt_in'] ?? 0));
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function buildValidProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Associado Teste',
            'phone_mobile' => '(84) 99999-9999',
            'phone_landline' => '',
            'birth_date' => '1990-08-12',
            'birth_state' => 'RN',
            'birth_city' => 'Natal',
            'birth_place' => '',
            'cpf' => $this->generateValidCpf('123123123'),
            'postal_code' => '59000-000',
            'street_address' => 'Rua Teste',
            'address_number' => '100',
            'address_complement' => '',
            'neighborhood' => 'Centro',
            'address_city' => 'Natal',
            'address_state' => 'RN',
            'preferred_due_day' => '',
            'contribution_amount' => '',
            'contribution_plan_label' => '',
            'preferred_payment_method' => '',
            'billing_email_opt_in' => '',
            'billing_whatsapp_opt_in' => '',
            'privacy_notice_acknowledged' => '1',
        ], $overrides);
    }

    private function generateValidCpf(string $baseDigits): string
    {
        $digits = preg_replace('/\D+/', '', $baseDigits) ?? '';
        $digits = str_pad(substr($digits, 0, 9), 9, '0', STR_PAD_RIGHT);

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += ((int) $digits[$index]) * (($position + 1) - $index);
            }

            $remainder = ($sum * 10) % 11;
            $digits .= (string) ($remainder === 10 ? 0 : $remainder);
        }

        return $digits;
    }
}
