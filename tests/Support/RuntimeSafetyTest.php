<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\RuntimeSafety;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class RuntimeSafetyTest extends TestCase
{
    public function testRepositoryFallbackIsAllowedByDefaultInDevelopmentLikeEnvironments(): void
    {
        $this->assertTrue(RuntimeSafety::repositoryFallbackAllowed([
            'APP_ENV' => 'development',
        ]));
    }

    public function testRepositoryFallbackIsBlockedByDefaultInProduction(): void
    {
        $this->assertFalse(RuntimeSafety::repositoryFallbackAllowed([
            'APP_ENV' => 'production',
        ]));
    }

    public function testRepositoryFallbackCanBeExplicitlyEnabled(): void
    {
        $this->assertTrue(RuntimeSafety::repositoryFallbackAllowed([
            'APP_ENV' => 'production',
            'APP_ALLOW_REPOSITORY_FALLBACK' => 'true',
        ]));
    }

    public function testDiagnosticsRequireTokenInProduction(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/health/readiness?token=segredo')
            ->withQueryParams(['token' => 'segredo']);

        $this->assertTrue(RuntimeSafety::diagnosticRequestAuthorized($request, [
            'APP_ENV' => 'production',
            'APP_ENABLE_DIAGNOSTIC_ROUTES' => 'true',
            'APP_DIAGNOSTIC_TOKEN' => 'segredo',
        ]));
    }

    public function testDiagnosticsAreDeniedWithoutTokenInProduction(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health/readiness');

        $this->assertFalse(RuntimeSafety::diagnosticRequestAuthorized($request, [
            'APP_ENV' => 'production',
            'APP_ENABLE_DIAGNOSTIC_ROUTES' => 'true',
            'APP_DIAGNOSTIC_TOKEN' => 'segredo',
        ]));
    }

    public function testDiagnosticsStayAvailableInDevelopmentWithoutToken(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health/readiness');

        $this->assertTrue(RuntimeSafety::diagnosticRequestAuthorized($request, [
            'APP_ENV' => 'development',
        ]));
    }
}
