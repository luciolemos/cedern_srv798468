<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\ContributionBillingGateway;
use App\Domain\Member\MemberAuthRepository;
use Psr\Log\LoggerInterface;

final class ContributionBillingCycleRunner
{
    private LoggerInterface $logger;
    private MemberAuthRepository $memberAuthRepository;
    private ContributionBillingGateway $billingGateway;

    public function __construct(
        LoggerInterface $logger,
        MemberAuthRepository $memberAuthRepository,
        ContributionBillingGateway $billingGateway
    ) {
        $this->logger = $logger;
        $this->memberAuthRepository = $memberAuthRepository;
        $this->billingGateway = $billingGateway;
    }

    /**
     * @return array{
     *     competence: string,
     *     billing_mode: string,
     *     local: array{created: int, skipped_existing: int, skipped_incomplete_profile: int},
     *     external: array{
     *         created: int,
     *         skipped_existing: int,
     *         skipped_non_pending: int,
     *         skipped_missing_context: int,
     *         failures: list<array{charge_id: int, member_user_id: int, member_name: string, error: string}>
     *     }
     * }
     */
    public function run(string $competence, string $billingMode = 'preferred'): array
    {
        $normalizedCompetence = $this->normalizeCompetence($competence);
        $normalizedBillingMode = $this->normalizeBillingMode($billingMode);

        $localSummary = $this->memberAuthRepository->generateContributionCharges($normalizedCompetence, null);
        $externalSummary = [
            'created' => 0,
            'skipped_existing' => 0,
            'skipped_non_pending' => 0,
            'skipped_missing_context' => 0,
            'failures' => [],
        ];

        if (!$this->billingGateway->isConfigured()) {
            throw new \RuntimeException('Gateway externo não configurado. Defina as credenciais do Asaas antes da automação.');
        }

        foreach ($this->memberAuthRepository->findContributionMembersByCompetence($normalizedCompetence) as $row) {
            $chargeId = (int) ($row['charge_id'] ?? 0);
            $memberUserId = (int) ($row['id'] ?? 0);

            if ($chargeId <= 0 || $memberUserId <= 0) {
                $externalSummary['skipped_missing_context']++;
                continue;
            }

            $charge = $this->memberAuthRepository->findContributionChargeById($chargeId);
            $member = $this->memberAuthRepository->findById($memberUserId);

            if ($charge === null || $member === null) {
                $externalSummary['skipped_missing_context']++;
                continue;
            }

            if (trim((string) ($charge['gateway_payment_id'] ?? '')) !== '') {
                $externalSummary['skipped_existing']++;
                continue;
            }

            if (strtolower(trim((string) ($charge['status'] ?? ''))) !== 'pending') {
                $externalSummary['skipped_non_pending']++;
                continue;
            }

            $resolvedBillingType = $this->resolveBillingType($normalizedBillingMode, $member, $charge);

            try {
                $gatewayData = $this->billingGateway->createCharge($member, $charge, $resolvedBillingType);
                $this->memberAuthRepository->updateContributionGatewayData($chargeId, $gatewayData);
                $externalSummary['created']++;
            } catch (\Throwable $exception) {
                $failure = [
                    'charge_id' => $chargeId,
                    'member_user_id' => $memberUserId,
                    'member_name' => trim((string) ($member['full_name'] ?? 'Associado CEDE')),
                    'error' => $exception->getMessage(),
                ];

                $externalSummary['failures'][] = $failure;

                $this->logger->warning('Falha na automação de cobrança mensal.', [
                    'competence' => $normalizedCompetence,
                    'charge_id' => $chargeId,
                    'member_user_id' => $memberUserId,
                    'billing_mode' => $normalizedBillingMode,
                    'resolved_billing_type' => $resolvedBillingType,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'competence' => $normalizedCompetence,
            'billing_mode' => $normalizedBillingMode,
            'local' => $localSummary,
            'external' => $externalSummary,
        ];
    }

    private function normalizeCompetence(string $value): string
    {
        $normalized = trim($value);

        if (preg_match('/^\d{4}-\d{2}$/', $normalized) === 1) {
            return $normalized;
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Fortaleza')))->format('Y-m');
    }

    private function normalizeBillingMode(string $value): string
    {
        return match (strtolower(trim($value))) {
            'pix', 'boleto', 'preferred' => strtolower(trim($value)),
            default => 'preferred',
        };
    }

    /**
     * @param array<string, mixed> $member
     * @param array<string, mixed> $charge
     */
    private function resolveBillingType(string $billingMode, array $member, array $charge): string
    {
        if ($billingMode === 'pix' || $billingMode === 'boleto') {
            return $billingMode;
        }

        $preferredPaymentMethod = strtolower(trim((string) (
            $charge['preferred_payment_method']
            ?? $member['preferred_payment_method']
            ?? 'manual'
        )));

        return in_array($preferredPaymentMethod, ['pix', 'pix_automatico'], true)
            ? 'pix'
            : 'boleto';
    }
}
