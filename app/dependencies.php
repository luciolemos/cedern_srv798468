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

            $appDefaultPageTitle = trim((string) ($_ENV['APP_DEFAULT_PAGE_TITLE'] ?? 'CEDE | Centro de Estudos da Doutrina Espírita'));
            $appDefaultPageDescription = trim((string) ($_ENV['APP_DEFAULT_PAGE_DESCRIPTION'] ?? 'Centro de Estudos da Doutrina Espírita (CEDE): instituição filantrópica dedicada ao estudo, à prática e à divulgação da Doutrina Espírita.'));
            $appDefaultPageUrl = trim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'));
            $appDefaultPageImage = trim((string) ($_ENV['APP_DEFAULT_PAGE_IMAGE'] ?? 'https://cedern.org/assets/img/cedern/cede1_1600_1000.png'));
            $appDefaultSiteName = trim((string) ($_ENV['APP_DEFAULT_SITE_NAME'] ?? 'CEDE'));
            $appDefaultTwitterSite = trim((string) ($_ENV['APP_DEFAULT_TWITTER_SITE'] ?? '@cedeoficialrn'));

            if ($appDefaultPageTitle === '') {
                $appDefaultPageTitle = 'CEDE | Centro de Estudos da Doutrina Espírita';
            }

            if ($appDefaultPageDescription === '') {
                $appDefaultPageDescription = 'Centro de Estudos da Doutrina Espírita (CEDE): instituição filantrópica dedicada ao estudo, à prática e à divulgação da Doutrina Espírita.';
            }

            if ($appDefaultPageUrl === '') {
                $appDefaultPageUrl = 'https://cedern.org/';
            }

            if ($appDefaultPageImage === '') {
                $appDefaultPageImage = 'https://cedern.org/assets/img/cedern/cede1_1600_1000.png';
            }

            if ($appDefaultSiteName === '') {
                $appDefaultSiteName = 'CEDE';
            }

            if ($appDefaultTwitterSite === '') {
                $appDefaultTwitterSite = '@cedeoficialrn';
            }

            $defaultTheme = $resolveEnvChoice('APP_DEFAULT_THEME');
            $defaultMode = $resolveEnvChoice('APP_DEFAULT_MODE');
            $defaultDarkIntensity = $resolveEnvChoice('APP_DEFAULT_DARK_INTENSITY');
            $homeContent = require __DIR__ . '/content/home.php';
            $appAddress = (string) ($homeContent['sections']['cta']['address'] ?? '');
            $appInstagramUrl = (string) ($homeContent['sections']['cta']['instagramUrl'] ?? '');
            $appInstagramLabel = (string) ($homeContent['sections']['cta']['instagramLabel'] ?? 'Instagram oficial');

            $twig->getEnvironment()->addGlobal('app_default_page_title', $appDefaultPageTitle);
            $twig->getEnvironment()->addGlobal('app_default_page_description', $appDefaultPageDescription);
            $twig->getEnvironment()->addGlobal('app_default_page_url', $appDefaultPageUrl);
            $twig->getEnvironment()->addGlobal('app_default_page_image', $appDefaultPageImage);
            $twig->getEnvironment()->addGlobal('app_default_site_name', $appDefaultSiteName);
            $twig->getEnvironment()->addGlobal('app_default_twitter_site', $appDefaultTwitterSite);
            $twig->getEnvironment()->addGlobal('default_theme', $defaultTheme);
            $twig->getEnvironment()->addGlobal('default_mode', $defaultMode);
            $twig->getEnvironment()->addGlobal('default_dark_intensity', $defaultDarkIntensity);
            $twig->getEnvironment()->addGlobal('app_address', $appAddress);
            $twig->getEnvironment()->addGlobal('app_instagram_url', $appInstagramUrl);
            $twig->getEnvironment()->addGlobal('app_instagram_label', $appInstagramLabel);

            return $twig;
        },
    ]);
};
