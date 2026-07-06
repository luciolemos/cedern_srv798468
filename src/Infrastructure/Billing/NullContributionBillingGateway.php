<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use App\Domain\Billing\ContributionBillingGateway;

final class NullContributionBillingGateway implements ContributionBillingGateway
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function providerKey(): string
    {
        return 'none';
    }

    public function createCharge(array $member, array $charge, string $billingType): array
    {
        throw new \RuntimeException('Gateway de cobrança não configurado.');
    }

    public function refreshCharge(array $charge): array
    {
        throw new \RuntimeException('Gateway de cobrança não configurado.');
    }
}
