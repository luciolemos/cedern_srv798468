<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },
        Twig::class => function () {
            $twig = Twig::create(__DIR__ . '/../templates', [
                'cache' => false,
            ]);

            $uiDefaults = [
                'APP_DEFAULT_THEME' => [
                    'default' => 'amber',
                    'allowed' => ['blue', 'red', 'green', 'violet', 'amber'],
                ],
                'APP_DEFAULT_MODE' => [
                    'default' => 'light',
                    'allowed' => ['light', 'dark'],
                ],
                'APP_DEFAULT_DARK_INTENSITY' => [
                    'default' => 'neutral',
                    'allowed' => ['neutral', 'vivid'],
                ],
            ];

            $resolveEnvChoice = static function (string $key) use ($uiDefaults): string {
                $config = $uiDefaults[$key];
                $value = strtolower(trim((string) ($_ENV[$key] ?? $config['default'])));

                return in_array($value, $config['allowed'], true)
                    ? $value
                    : $config['default'];
            };

            $appDefaultPageTitle = trim((string) ($_ENV['APP_DEFAULT_PAGE_TITLE'] ?? 'NatalCode | Soluções web'));

            if ($appDefaultPageTitle === '') {
                $appDefaultPageTitle = 'NatalCode | Soluções web';
            }

            $defaultTheme = $resolveEnvChoice('APP_DEFAULT_THEME');
            $defaultMode = $resolveEnvChoice('APP_DEFAULT_MODE');
            $defaultDarkIntensity = $resolveEnvChoice('APP_DEFAULT_DARK_INTENSITY');
            $homeContent = require __DIR__ . '/content/home.php';
            $appAddress = (string) ($homeContent['sections']['cta']['address'] ?? '');

            $twig->getEnvironment()->addGlobal('app_default_page_title', $appDefaultPageTitle);
            $twig->getEnvironment()->addGlobal('default_theme', $defaultTheme);
            $twig->getEnvironment()->addGlobal('default_mode', $defaultMode);
            $twig->getEnvironment()->addGlobal('default_dark_intensity', $defaultDarkIntensity);
            $twig->getEnvironment()->addGlobal('app_address', $appAddress);

            return $twig;
        },
    ]);
};
