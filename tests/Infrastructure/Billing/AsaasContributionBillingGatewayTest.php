<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Billing;

use App\Infrastructure\Billing\AsaasContributionBillingGateway;
use PHPUnit\Framework\TestCase;

final class AsaasContributionBillingGatewayTest extends TestCase
{
    public function testResolveGatewayDueDateKeepsFutureDate(): void
    {
        $gateway = new AsaasContributionBillingGateway();
        $method = new \ReflectionMethod($gateway, 'resolveGatewayDueDate');
        $method->setAccessible(true);
        $futureDate = date('Y-m-d', strtotime('+5 days'));

        $resolvedDueDate = $method->invoke($gateway, $futureDate);

        $this->assertSame($futureDate, $resolvedDueDate);
    }

    public function testResolveGatewayDueDateClampsPastDateToToday(): void
    {
        $gateway = new AsaasContributionBillingGateway();
        $method = new \ReflectionMethod($gateway, 'resolveGatewayDueDate');
        $method->setAccessible(true);
        $pastDate = date('Y-m-d', strtotime('-10 days'));

        $resolvedDueDate = $method->invoke($gateway, $pastDate);

        $this->assertSame(date('Y-m-d'), $resolvedDueDate);
    }
}
