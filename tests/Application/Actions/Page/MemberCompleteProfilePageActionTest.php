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
