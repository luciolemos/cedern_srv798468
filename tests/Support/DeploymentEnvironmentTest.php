<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\DeploymentEnvironment;
use PHPUnit\Framework\TestCase;

final class DeploymentEnvironmentTest extends TestCase
{
    public function testResolveUsesAppDeployStageWhenProvided(): void
    {
        $environment = DeploymentEnvironment::resolve([
            'APP_ENV' => 'production',
            'APP_DEPLOY_STAGE' => 'homolog',
        ]);

        $this->assertSame('homolog', $environment['stage']);
        $this->assertSame('Homologação', $environment['label']);
        $this->assertSame('test', $environment['tone']);
        $this->assertSame('[Homologação] ', $environment['title_prefix']);
    }

    public function testResolveFallsBackToCanonicalUrlForHomologation(): void
    {
        $environment = DeploymentEnvironment::resolve([
            'APP_ENV' => 'production',
            'APP_DEFAULT_PAGE_URL' => 'https://homolog.cedern.org/',
        ]);

        $this->assertSame('homolog', $environment['stage']);
        $this->assertSame('Homologação', $environment['label']);
    }

    public function testResolveFallsBackToAppEnvForDevelopment(): void
    {
        $environment = DeploymentEnvironment::resolve([
            'APP_ENV' => 'development',
        ]);

        $this->assertSame('development', $environment['stage']);
        $this->assertSame('Desenvolvimento', $environment['label']);
        $this->assertSame('dev', $environment['tone']);
    }

    public function testResolveAllowsCustomLabelOverride(): void
    {
        $environment = DeploymentEnvironment::resolve([
            'APP_DEPLOY_STAGE' => 'production',
            'APP_DEPLOY_STAGE_LABEL' => 'Operação Assistida',
        ]);

        $this->assertSame('production', $environment['stage']);
        $this->assertSame('Operação Assistida', $environment['label']);
        $this->assertSame('', $environment['title_prefix']);
    }
}
