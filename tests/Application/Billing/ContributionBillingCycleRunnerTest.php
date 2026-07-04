<?php

declare(strict_types=1);

namespace Tests\Application\Billing;

use App\Application\Billing\ContributionBillingCycleRunner;
use App\Domain\Billing\ContributionBillingGateway;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ContributionBillingCycleRunnerTest extends TestCase
{
    public function testGeneratesLocalChargesAndCreatesExternalChargeUsingPreferredMethod(): void
    {
        $repository = new FallbackMemberAuthRepository();
        $memberUserId = $this->createContributor($repository, 'Marina Silva', 'marina@example.com', [
            'preferred_payment_method' => 'pix_automatico',
        ]);
        $gateway = new RecordingContributionBillingGateway();
        $runner = new ContributionBillingCycleRunner(new NullLogger(), $repository, $gateway);

        $summary = $runner->run('2026-07', 'preferred');
        $charges = $repository->findContributionChargesByMember($memberUserId, 5);

        $this->assertSame('2026-07', $summary['competence']);
        $this->assertSame('preferred', $summary['billing_mode']);
        $this->assertSame(1, $summary['local']['created']);
        $this->assertSame(1, $summary['external']['created']);
        $this->assertSame([], $summary['external']['failures']);
        $this->assertCount(1, $gateway->calls);
        $this->assertSame('pix', $gateway->calls[0]['billing_type']);
        $this->assertCount(1, $charges);
        $this->assertSame('pay_1', $charges[0]['gateway_payment_id'] ?? null);
        $this->assertSame('PIX', $charges[0]['gateway_billing_type'] ?? null);
    }

    public function testSkipsExistingAndPaidChargesAndCollectsFailures(): void
    {
        $repository = new FallbackMemberAuthRepository();

        $failureUserId = $this->createContributor($repository, 'Ana Falha', 'ana@example.com');
        $existingUserId = $this->createContributor($repository, 'Bruno Existente', 'bruno@example.com');
        $paidUserId = $this->createContributor($repository, 'Carla Paga', 'carla@example.com');

        $repository->generateContributionCharges('2026-07', null);

        $failureCharge = $repository->findContributionChargesByMember($failureUserId, 1)[0] ?? null;
        $existingCharge = $repository->findContributionChargesByMember($existingUserId, 1)[0] ?? null;
        $paidCharge = $repository->findContributionChargesByMember($paidUserId, 1)[0] ?? null;

        $this->assertNotNull($failureCharge);
        $this->assertNotNull($existingCharge);
        $this->assertNotNull($paidCharge);

        $repository->updateContributionGatewayData((int) $existingCharge['id'], [
            'gateway_provider' => 'asaas',
            'gateway_customer_id' => 'cus_existing',
            'gateway_payment_id' => 'pay_existing',
            'gateway_billing_type' => 'BOLETO',
            'gateway_status' => 'PENDING',
            'gateway_invoice_url' => 'https://asaas.test/invoice/pay_existing',
            'gateway_bank_slip_url' => 'https://asaas.test/boleto/pay_existing',
            'gateway_transaction_receipt_url' => null,
            'gateway_pix_payload' => null,
            'gateway_pix_encoded_image' => null,
            'gateway_pix_expiration_date' => null,
            'gateway_last_synced_at' => '2026-07-01 10:00:00',
        ]);
        $repository->markContributionChargeAsPaid((int) $paidCharge['id'], 'pix', 7);

        $gateway = new RecordingContributionBillingGateway([
            (int) $failureCharge['id'] => 'Erro simulado no Asaas.',
        ]);
        $runner = new ContributionBillingCycleRunner(new NullLogger(), $repository, $gateway);

        $summary = $runner->run('2026-07', 'boleto');

        $this->assertSame(0, $summary['local']['created']);
        $this->assertSame(3, $summary['local']['skipped_existing']);
        $this->assertSame(0, $summary['external']['created']);
        $this->assertSame(1, $summary['external']['skipped_existing']);
        $this->assertSame(1, $summary['external']['skipped_non_pending']);
        $this->assertSame(0, $summary['external']['skipped_missing_context']);
        $this->assertCount(1, $summary['external']['failures']);
        $this->assertSame((int) $failureCharge['id'], $summary['external']['failures'][0]['charge_id']);
        $this->assertCount(1, $gateway->calls);
        $this->assertSame('boleto', $gateway->calls[0]['billing_type']);
    }

    public function testThrowsWhenGatewayIsNotConfigured(): void
    {
        $repository = new FallbackMemberAuthRepository();
        $this->createContributor($repository, 'Dora Sem Gateway', 'dora@example.com');
        $runner = new ContributionBillingCycleRunner(
            new NullLogger(),
            $repository,
            new RecordingContributionBillingGateway([], false)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gateway externo não configurado');

        $runner->run('2026-07', 'preferred');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createContributor(
        FallbackMemberAuthRepository $repository,
        string $fullName,
        string $email,
        array $overrides = []
    ): int {
        $userId = $repository->createPendingUser([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => 'hash',
        ]);

        $repository->updateProfile($userId, array_merge([
            'full_name' => $fullName,
            'cpf' => str_pad((string) (52998224725 + $userId), 11, '0', STR_PAD_LEFT),
            'phone_mobile' => '84999990000',
            'preferred_due_day' => 10,
            'contribution_amount' => '65.50',
            'preferred_payment_method' => 'pix',
            'billing_email_opt_in' => 1,
            'billing_whatsapp_opt_in' => 1,
            'profile_completed' => 1,
        ], $overrides));
        $repository->approveAndAssignRole($userId, 1, 'Atendimento fraterno', 'efetivo');

        return $userId;
    }
}

final class RecordingContributionBillingGateway implements ContributionBillingGateway
{
    /**
     * @var array<int, string>
     */
    private array $failuresByChargeId;

    private bool $configured;

    /**
     * @var list<array{member_id: int, charge_id: int, billing_type: string}>
     */
    public array $calls = [];

    /**
     * @param array<int, string> $failuresByChargeId
     */
    public function __construct(array $failuresByChargeId = [], bool $configured = true)
    {
        $this->failuresByChargeId = $failuresByChargeId;
        $this->configured = $configured;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function providerKey(): string
    {
        return 'asaas';
    }

    public function createCharge(array $member, array $charge, string $billingType): array
    {
        $chargeId = (int) ($charge['id'] ?? 0);

        $this->calls[] = [
            'member_id' => (int) ($member['id'] ?? 0),
            'charge_id' => $chargeId,
            'billing_type' => $billingType,
        ];

        if (isset($this->failuresByChargeId[$chargeId])) {
            throw new \RuntimeException($this->failuresByChargeId[$chargeId]);
        }

        return [
            'gateway_provider' => 'asaas',
            'gateway_customer_id' => 'cus_' . $chargeId,
            'gateway_payment_id' => 'pay_' . $chargeId,
            'gateway_billing_type' => strtoupper($billingType),
            'gateway_status' => 'PENDING',
            'gateway_invoice_url' => 'https://asaas.test/invoice/pay_' . $chargeId,
            'gateway_bank_slip_url' => $billingType === 'boleto'
                ? 'https://asaas.test/boleto/pay_' . $chargeId
                : null,
            'gateway_transaction_receipt_url' => null,
            'gateway_pix_payload' => $billingType === 'pix' ? '000201010212' : null,
            'gateway_pix_encoded_image' => $billingType === 'pix' ? 'ZmFrZS1xci1jb2Rl' : null,
            'gateway_pix_expiration_date' => $billingType === 'pix' ? '2026-07-10 23:59:59' : null,
            'gateway_last_synced_at' => '2026-07-01 12:00:00',
        ];
    }

    public function refreshCharge(array $charge): array
    {
        return [];
    }
}
