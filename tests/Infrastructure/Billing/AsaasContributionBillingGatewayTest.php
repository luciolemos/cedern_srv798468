<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Billing;

use App\Infrastructure\Billing\AsaasContributionBillingGateway;
use PHPUnit\Framework\TestCase;

final class AsaasContributionBillingGatewayTest extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private array $environmentBackup = [];

    protected function tearDown(): void
    {
        foreach ($this->environmentBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
                putenv($key);
                continue;
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }

        $this->environmentBackup = [];
    }

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

    public function testIsNotConfiguredWhenDevelopmentPointsToProductionAsaas(): void
    {
        $this->setEnvVar('APP_ENV', 'development');
        $this->setEnvVar('ASAAS_ENVIRONMENT', 'production');
        $this->setEnvVar('ASAAS_API_KEY', 'test_key');
        $this->setEnvVar('ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION', 'false');

        $gateway = new AsaasContributionBillingGateway();

        $this->assertFalse($gateway->isConfigured());
    }

    public function testIsConfiguredWhenDevelopmentUsesSandboxAsaas(): void
    {
        $this->setEnvVar('APP_ENV', 'development');
        $this->setEnvVar('ASAAS_ENVIRONMENT', 'sandbox');
        $this->setEnvVar('ASAAS_API_KEY', 'test_key');
        $this->setEnvVar('ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION', 'false');

        $gateway = new AsaasContributionBillingGateway();

        $this->assertTrue($gateway->isConfigured());
    }

    public function testAllowsProductionAsaasOutsideProductionOnlyWithExplicitOverride(): void
    {
        $this->setEnvVar('APP_ENV', 'development');
        $this->setEnvVar('ASAAS_ENVIRONMENT', 'production');
        $this->setEnvVar('ASAAS_API_KEY', 'test_key');
        $this->setEnvVar('ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION', 'true');

        $gateway = new AsaasContributionBillingGateway();

        $this->assertTrue($gateway->isConfigured());
    }

    private function setEnvVar(string $key, ?string $value): void
    {
        if (!array_key_exists($key, $this->environmentBackup)) {
            $this->environmentBackup[$key] = array_key_exists($key, $_ENV)
                ? (string) $_ENV[$key]
                : null;
        }

        if ($value === null) {
            unset($_ENV[$key]);
            putenv($key);

            return;
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}
