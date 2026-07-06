<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Domain\Billing\ContributionBillingGateway;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractAdminFinanceContributionGatewayAction extends AbstractAdminFinanceContributionsAction
{
    protected ContributionBillingGateway $billingGateway;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        \App\Domain\Member\MemberAuthRepository $memberAuthRepository,
        ContributionBillingGateway $billingGateway
    ) {
        parent::__construct($logger, $twig, $memberAuthRepository);
        $this->billingGateway = $billingGateway;
    }

    /**
     * @return array{charge: array<string, mixed>, member: array<string, mixed>, competence: string}|null
     */
    protected function loadChargeContext(int $chargeId): ?array
    {
        if ($chargeId <= 0) {
            return null;
        }

        $charge = $this->memberAuthRepository->findContributionChargeById($chargeId);
        if ($charge === null) {
            return null;
        }

        $memberId = (int) ($charge['member_user_id'] ?? 0);
        $member = $memberId > 0 ? $this->memberAuthRepository->findById($memberId) : null;
        if ($member === null) {
            return null;
        }

        return [
            'charge' => $charge,
            'member' => $member,
            'competence' => $this->normalizeCompetence($charge['competence'] ?? null),
        ];
    }

    protected function gatewayConfigured(): bool
    {
        return $this->billingGateway->isConfigured();
    }

    protected function redirectToChargeDetail(Response $response, int $chargeId): Response
    {
        return $response->withHeader(
            'Location',
            '/painel/financas/contribuicoes/' . $chargeId . '/cobranca'
        )->withStatus(302);
    }

    /**
     * @param array<string, mixed> $charge
     * @return array<string, mixed>
     */
    protected function syncGatewayCharge(array $charge): array
    {
        $gatewayData = $this->billingGateway->refreshCharge($charge);
        $chargeId = (int) ($charge['id'] ?? 0);

        if ($chargeId > 0) {
            $this->memberAuthRepository->updateContributionGatewayData($chargeId, $gatewayData);
        }

        $mergedCharge = array_merge($charge, $gatewayData);
        $this->maybeMarkChargeAsPaidFromGateway($mergedCharge);

        $refreshed = $chargeId > 0 ? $this->memberAuthRepository->findContributionChargeById($chargeId) : null;

        return $refreshed ?? $mergedCharge;
    }

    /**
     * @param array<string, mixed> $charge
     */
    protected function maybeMarkChargeAsPaidFromGateway(array $charge): void
    {
        $chargeId = (int) ($charge['id'] ?? 0);
        $chargeStatus = strtolower(trim((string) ($charge['status'] ?? '')));
        $gatewayStatus = strtoupper(trim((string) ($charge['gateway_status'] ?? '')));

        if ($chargeId <= 0 || $chargeStatus !== 'pending') {
            return;
        }

        if (!in_array($gatewayStatus, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
            return;
        }

        $paymentMethod = strtoupper(trim((string) ($charge['gateway_billing_type'] ?? ''))) === 'PIX'
            ? 'pix'
            : 'boleto';

        $this->memberAuthRepository->markContributionChargeAsPaid(
            $chargeId,
            $paymentMethod,
            $this->resolveActorUserId()
        );
    }

    protected function normalizeGatewayBillingType(string $value): string
    {
        return match (strtolower(trim($value))) {
            'pix' => 'Pix',
            'boleto' => 'Boleto',
            'undefined' => 'Fatura',
            default => 'Não definido',
        };
    }

    protected function normalizeGatewayStatusLabel(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'PENDING' => 'Pendente',
            'RECEIVED' => 'Recebida',
            'CONFIRMED' => 'Confirmada',
            'OVERDUE' => 'Vencida',
            'RECEIVED_IN_CASH' => 'Recebida em dinheiro',
            'REFUNDED' => 'Estornada',
            'REFUND_REQUESTED' => 'Estorno solicitado',
            'CHARGEBACK_REQUESTED' => 'Chargeback solicitado',
            'CHARGEBACK_DISPUTE' => 'Em disputa',
            'AWAITING_CHARGEBACK_REVERSAL' => 'Aguardando reversão',
            'DUNNING_REQUESTED' => 'Em negativação',
            'DUNNING_RECEIVED' => 'Negativação recebida',
            'DUNNING_CANCELED' => 'Negativação cancelada',
            default => trim($value) !== '' ? trim($value) : 'Sem status',
        };
    }

    protected function normalizeGatewayStatusTone(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'is-on',
            'OVERDUE', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE' => 'is-critical',
            'PENDING', 'DUNNING_REQUESTED' => 'is-warning',
            default => 'is-info',
        };
    }

    protected function normalizeLocalChargeStatusLabel(string $value): string
    {
        return match (strtolower(trim($value))) {
            'pending' => 'Pendente',
            'paid' => 'Recebida',
            'exempt' => 'Isenta',
            default => trim($value) !== '' ? trim($value) : 'Sem status',
        };
    }

    protected function formatCurrency(mixed $value): string
    {
        $numericValue = is_numeric((string) $value) ? (float) $value : 0.0;

        return 'R$ ' . number_format($numericValue, 2, ',', '.');
    }

    protected function formatDate(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    protected function formatDateTime(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y H:i');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    protected function formatCpf(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) !== 11) {
            return trim($value) !== '' ? $value : '-';
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }
}
