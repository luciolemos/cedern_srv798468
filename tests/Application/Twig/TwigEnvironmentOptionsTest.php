<?php

declare(strict_types=1);

namespace Tests\Application\Twig;

use Slim\Views\Twig;
use Tests\TestCase;

final class TwigEnvironmentOptionsTest extends TestCase
{
    public function testProductionTwigKeepsAutoReloadEnabled(): void
    {
        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $previousAssetVersion = $_ENV['APP_ASSET_VERSION'] ?? null;

        putenv('APP_ENV=production');
        putenv('APP_ASSET_VERSION=test-auto-reload');
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_ASSET_VERSION'] = 'test-auto-reload';

        try {
            $app = $this->getAppInstance();

            /** @var Twig $twig */
            $twig = $app->getContainer()->get(Twig::class);

            $this->assertTrue($twig->getEnvironment()->isAutoReload());
        } finally {
            if ($previousEnv === null) {
                putenv('APP_ENV');
                unset($_ENV['APP_ENV']);
            } else {
                putenv('APP_ENV=' . $previousEnv);
                $_ENV['APP_ENV'] = $previousEnv;
            }

            if ($previousAssetVersion === null) {
                putenv('APP_ASSET_VERSION');
                unset($_ENV['APP_ASSET_VERSION']);
            } else {
                putenv('APP_ASSET_VERSION=' . $previousAssetVersion);
                $_ENV['APP_ASSET_VERSION'] = $previousAssetVersion;
            }
        }
    }
}
