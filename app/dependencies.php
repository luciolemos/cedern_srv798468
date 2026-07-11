<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Application\Security\RecaptchaVerifier;
use App\Domain\Billing\ContributionBillingGateway;
use App\Infrastructure\Billing\AsaasContributionBillingGateway;
use App\Infrastructure\Billing\NullContributionBillingGateway;
use App\Support\DeploymentEnvironment;
use App\Support\ManagedPublicMediaPath;
use App\Support\ThemeConfig;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Twig\TwigFunction;

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
        RecaptchaVerifier::class => function (ContainerInterface $c): RecaptchaVerifier {
            return new RecaptchaVerifier($c->get(LoggerInterface::class));
        },
        ContributionBillingGateway::class => function (): ContributionBillingGateway {
            $gateway = new AsaasContributionBillingGateway();

            return $gateway->isConfigured() ? $gateway : new NullContributionBillingGateway();
        },
        \PDO::class => function (ContainerInterface $c): \PDO {
            $settings = $c->get(SettingsInterface::class);
            $db = (array) $settings->get('db');

            $host = (string) ($db['host'] ?? '');
            $name = (string) ($db['name'] ?? '');
            $user = (string) ($db['user'] ?? '');
            $pass = (string) ($db['pass'] ?? '');
            $port = (int) ($db['port'] ?? 3306);
            $charset = (string) ($db['charset'] ?? 'utf8mb4');
            $timezone = (string) ($db['timezone'] ?? '+00:00');

            if ($host === '' || $name === '' || $user === '') {
                throw new \RuntimeException('Configuração de banco incompleta. Defina DB_HOST, DB_NAME, DB_USER e DB_PASS no .env (ou no arquivo apontado por APP_ENV_FILE).');
            }

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $pdo->exec(sprintf("SET time_zone = '%s'", str_replace("'", "''", $timezone)));

            return $pdo;
        },
        Twig::class => function (ContainerInterface $c) {
            $appEnv = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'production')));
            $isDevelopment = in_array($appEnv, ['dev', 'development', 'local', 'test'], true);
            $appAssetVersion = trim((string) ($_ENV['APP_ASSET_VERSION'] ?? '1'));

            if ($appAssetVersion === '') {
                $appAssetVersion = '1';
            }

            $cacheVersionSuffix = preg_replace('/[^a-zA-Z0-9._-]/', '-', $appAssetVersion) ?? '';
            if ($cacheVersionSuffix === '') {
                $cacheVersionSuffix = '1';
            }

            $twigCache = false;

            if (!$isDevelopment) {
                $twigCacheDirectory = __DIR__ . '/../var/cache/twig/v' . $cacheVersionSuffix;
                $twigCacheReady = is_dir($twigCacheDirectory) || @mkdir($twigCacheDirectory, 0775, true);

                if ($twigCacheReady && is_writable($twigCacheDirectory)) {
                    $twigCache = $twigCacheDirectory;
                } else {
                    error_log(
                        '[cedern bootstrap] Twig cache disabled: cache directory is not writable: '
                        . $twigCacheDirectory
                    );
                }
            }

            $twig = Twig::create(__DIR__ . '/../templates', [
                'cache' => $twigCache,
                'auto_reload' => true,
            ]);

            $twig->getEnvironment()->addFunction(new TwigFunction(
                'member_profile_photo_url',
                static function (?string $rawPath, string $baseUrl = ''): string {
                    return ManagedPublicMediaPath::toUrl($rawPath, 'media/membros/fotos', $baseUrl);
                }
            ));

            $appDefaultPageTitle = trim((string) ($_ENV['APP_DEFAULT_PAGE_TITLE'] ?? 'CEDE | Centro de Estudos da Doutrina Espírita'));
            $appDefaultPageDescription = trim((string) ($_ENV['APP_DEFAULT_PAGE_DESCRIPTION'] ?? 'Centro de Estudos da Doutrina Espírita (CEDE): instituição filantrópica dedicada ao estudo, à prática e à divulgação da Doutrina Espírita.'));
            $appDefaultPageUrl = trim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'));
            $appDefaultPageImage = trim((string) ($_ENV['APP_DEFAULT_PAGE_IMAGE'] ?? 'https://cedern.org/assets/img/cedern/cede1_1600_1000.png'));
            $appDefaultSiteName = trim((string) ($_ENV['APP_DEFAULT_SITE_NAME'] ?? 'CEDE'));
            $appDefaultTwitterSite = trim((string) ($_ENV['APP_DEFAULT_TWITTER_SITE'] ?? '@cedeoficialrn'));
            $recaptchaVerifier = $c->get(RecaptchaVerifier::class);
            $appRecaptchaEnabled = $recaptchaVerifier->isReady();
            $appRecaptchaSiteKey = $recaptchaVerifier->getSiteKey();
            $deploymentEnvironment = DeploymentEnvironment::resolve($_ENV);
            $themeConfig = ThemeConfig::resolve($_ENV);

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

            $defaultTheme = $themeConfig['default_theme'];
            $defaultMode = $themeConfig['default_mode'];
            $defaultDarkIntensity = $themeConfig['default_dark_intensity'];
            $homeContent = require __DIR__ . '/content/home.php';
            $navigationContent = require __DIR__ . '/content/navigation.php';
            $siteContent = require __DIR__ . '/content/site.php';

            $assertNavigationConfig = static function (array $config): void {
                if (!isset($config['labels']) || !is_array($config['labels'])) {
                    throw new \InvalidArgumentException('Navigation config inválida: `labels` deve ser um array.');
                }

                if (!isset($config['menu']) || !is_array($config['menu'])) {
                    throw new \InvalidArgumentException('Navigation config inválida: `menu` deve ser um array.');
                }

                $validateStandaloneLinks = static function (array $links, string $sectionName): void {
                    foreach ($links as $index => $link) {
                        if (!is_array($link) || !isset($link['path']) || !is_string($link['path']) || $link['path'] === '') {
                            throw new \InvalidArgumentException(sprintf('Navigation config inválida: `%s[%d].path` é obrigatório.', $sectionName, $index));
                        }

                        $hasLabel = isset($link['label']) && is_string($link['label']) && $link['label'] !== '';
                        $hasKey = isset($link['key']) && is_string($link['key']) && $link['key'] !== '';

                        if (!$hasLabel && !$hasKey) {
                            throw new \InvalidArgumentException(sprintf('Navigation config inválida: `%s[%d]` precisa de `key` ou `label`.', $sectionName, $index));
                        }
                    }
                };

                foreach ($config['menu'] as $groupIndex => $group) {
                    if (!is_array($group)) {
                        throw new \InvalidArgumentException(sprintf('Navigation config inválida: `menu[%d]` deve ser um array.', $groupIndex));
                    }

                    if (!isset($group['key']) || !is_string($group['key']) || $group['key'] === '') {
                        throw new \InvalidArgumentException(sprintf('Navigation config inválida: `menu[%d].key` é obrigatório.', $groupIndex));
                    }

                    if (!isset($group['base']) || !is_string($group['base']) || $group['base'] === '') {
                        throw new \InvalidArgumentException(sprintf('Navigation config inválida: `menu[%d].base` é obrigatório.', $groupIndex));
                    }

                    if (!isset($group['items']) || !is_array($group['items'])) {
                        throw new \InvalidArgumentException(sprintf('Navigation config inválida: `menu[%d].items` deve ser um array.', $groupIndex));
                    }

                    foreach ($group['items'] as $itemIndex => $item) {
                        if (!is_array($item)) {
                            throw new \InvalidArgumentException(sprintf('Navigation config inválida: `menu[%d].items[%d]` deve ser um array.', $groupIndex, $itemIndex));
                        }

                        if (!isset($item['path']) || !is_string($item['path']) || $item['path'] === '') {
                            throw new \InvalidArgumentException(sprintf('Navigation config inválida: `menu[%d].items[%d].path` é obrigatório.', $groupIndex, $itemIndex));
                        }

                        $hasLabel = isset($item['label']) && is_string($item['label']) && $item['label'] !== '';
                        $hasKey = isset($item['key']) && is_string($item['key']) && $item['key'] !== '';

                        if (!$hasLabel && !$hasKey) {
                            throw new \InvalidArgumentException(sprintf('Navigation config inválida: `menu[%d].items[%d]` precisa de `key` ou `label`.', $groupIndex, $itemIndex));
                        }
                    }
                }

                $before = $config['links_before_groups'] ?? [];
                $after = $config['links_after_groups'] ?? [];

                if (!is_array($before) || !is_array($after)) {
                    throw new \InvalidArgumentException('Navigation config inválida: `links_before_groups` e `links_after_groups` devem ser arrays.');
                }

                $validateStandaloneLinks($before, 'links_before_groups');
                $validateStandaloneLinks($after, 'links_after_groups');
            };

            $assertNavigationConfig($navigationContent);

            $navigationLabels = (array) ($navigationContent['labels'] ?? []);
            $navigationMenu = (array) ($navigationContent['menu'] ?? []);
            $navigationLinksBeforeGroups = (array) ($navigationContent['links_before_groups'] ?? []);
            $navigationLinksAfterGroups = (array) ($navigationContent['links_after_groups'] ?? []);
            $siteFooter = (array) ($siteContent['footer'] ?? []);
            $siteFooterNavGroups = (array) ($siteFooter['navGroups'] ?? []);

            $normalizedFooterNavGroups = [];
            foreach ($siteFooterNavGroups as $group) {
                $groupLinks = [];
                foreach ((array) ($group['links'] ?? []) as $link) {
                    $label = trim((string) ($link['label'] ?? ''));
                    $key = trim((string) ($link['key'] ?? ''));

                    if ($label === '' && $key !== '') {
                        $label = (string) ($navigationLabels[$key] ?? $key);
                    }

                    $link['label'] = $label !== '' ? $label : (string) ($link['path'] ?? '');
                    $groupLinks[] = $link;
                }

                $group['links'] = $groupLinks;
                $normalizedFooterNavGroups[] = $group;
            }

            $siteFooter['navGroups'] = $normalizedFooterNavGroups;

            $siteContent['footer'] = $siteFooter;

            $appAddress = (string) ($siteContent['contact']['address'] ?? '');
            $appInstagramUrl = (string) ($siteContent['social']['instagram']['url'] ?? '');
            $appInstagramLabel = (string) ($siteContent['social']['instagram']['label'] ?? 'Instagram oficial');

            $twig->getEnvironment()->addGlobal('app_default_page_title', $appDefaultPageTitle);
            $twig->getEnvironment()->addGlobal('app_default_page_description', $appDefaultPageDescription);
            $twig->getEnvironment()->addGlobal('app_default_page_url', $appDefaultPageUrl);
            $twig->getEnvironment()->addGlobal('app_default_page_image', $appDefaultPageImage);
            $twig->getEnvironment()->addGlobal('app_default_site_name', $appDefaultSiteName);
            $twig->getEnvironment()->addGlobal('app_default_twitter_site', $appDefaultTwitterSite);
            $twig->getEnvironment()->addGlobal('app_asset_version', $appAssetVersion);
            $twig->getEnvironment()->addGlobal('app_env', $appEnv);
            $twig->getEnvironment()->addGlobal('app_deploy_stage', $deploymentEnvironment['stage']);
            $twig->getEnvironment()->addGlobal('app_environment_label', $deploymentEnvironment['label']);
            $twig->getEnvironment()->addGlobal('app_environment_tone', $deploymentEnvironment['tone']);
            $twig->getEnvironment()->addGlobal('app_environment_title_prefix', $deploymentEnvironment['title_prefix']);
            $twig->getEnvironment()->addGlobal('app_theme_palette_enabled', $themeConfig['theme_palette_enabled']);
            $twig->getEnvironment()->addGlobal('app_dashboard_theme_palette_enabled', $themeConfig['dashboard_theme_palette_enabled']);
            $twig->getEnvironment()->addGlobal('app_theme_allowed_themes', $themeConfig['allowed_themes']);
            $twig->getEnvironment()->addGlobal('app_theme_allowed_modes', $themeConfig['allowed_modes']);
            $twig->getEnvironment()->addGlobal('app_theme_allowed_dark_intensities', $themeConfig['allowed_dark_intensities']);
            $twig->getEnvironment()->addGlobal('app_theme_options', $themeConfig['theme_options']);
            $twig->getEnvironment()->addGlobal('app_mode_options', $themeConfig['mode_options']);
            $twig->getEnvironment()->addGlobal('app_dark_intensity_options', $themeConfig['dark_intensity_options']);
            $twig->getEnvironment()->addGlobal('app_recaptcha_enabled', $appRecaptchaEnabled);
            $twig->getEnvironment()->addGlobal('app_recaptcha_site_key', $appRecaptchaSiteKey);
            $twig->getEnvironment()->addGlobal('default_theme', $defaultTheme);
            $twig->getEnvironment()->addGlobal('default_mode', $defaultMode);
            $twig->getEnvironment()->addGlobal('default_dark_intensity', $defaultDarkIntensity);
            $twig->getEnvironment()->addGlobal('site', $siteContent);
            $twig->getEnvironment()->addGlobal('app_address', $appAddress);
            $twig->getEnvironment()->addGlobal('app_instagram_url', $appInstagramUrl);
            $twig->getEnvironment()->addGlobal('app_instagram_label', $appInstagramLabel);
            $twig->getEnvironment()->addGlobal('navigation_labels', $navigationLabels);
            $twig->getEnvironment()->addGlobal('navigation_menu', $navigationMenu);
            $twig->getEnvironment()->addGlobal('navigation_links_before_groups', $navigationLinksBeforeGroups);
            $twig->getEnvironment()->addGlobal('navigation_links_after_groups', $navigationLinksAfterGroups);

            return $twig;
        },
    ]);
};
