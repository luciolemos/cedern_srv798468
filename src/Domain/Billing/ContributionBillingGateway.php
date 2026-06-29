<?php

declare(strict_types=1);

namespace App\Domain\Billing;

interface ContributionBillingGateway
{
    public function isConfigured(): bool;

    public function providerKey(): string;

    /**
     * @param array<string, mixed> $member
     * @param array<string, mixed> $charge
     * @return array<string, mixed>
     */
    public function createCharge(array $member, array $charge, string $billingType): array;

    /**
     * @param array<string, mixed> $charge
     * @return array<string, mixed>
     */
    public function refreshCharge(array $charge): array;
}
