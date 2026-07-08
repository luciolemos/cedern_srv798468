<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            $readEnv = static function (string $key, string $default = ''): string {
                $value = getenv($key);
                if ($value !== false) {
                    return trim((string) $value);
                }

                if (isset($_SERVER[$key])) {
                    return trim((string) $_SERVER[$key]);
                }

                if (isset($_ENV[$key])) {
                    return trim((string) $_ENV[$key]);
                }

                return $default;
            };

            $appEnv = strtolower($readEnv('APP_ENV', 'production'));
            $isDevelopment = in_array($appEnv, ['dev', 'development', 'local', 'test'], true);
            $isDockerEnv = filter_var(
                $readEnv('docker'),
                FILTER_VALIDATE_BOOLEAN
            );
            $customLogPath = $readEnv('APP_LOG_PATH');
            $loggerPath = $customLogPath !== ''
                ? $customLogPath
                : ($appEnv === 'test'
                    ? 'php://stderr'
                    : ($isDockerEnv ? 'php://stdout' : __DIR__ . '/../logs/app.log'));

            return new Settings([
                'displayErrorDetails' => $isDevelopment,
                'logError'            => !$isDevelopment,
                'logErrorDetails'     => !$isDevelopment,
                'db' => [
                    'host' => trim((string) ($_ENV['DB_HOST'] ?? '')),
                    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                    'name' => trim((string) ($_ENV['DB_NAME'] ?? '')),
                    'user' => trim((string) ($_ENV['DB_USER'] ?? '')),
                    'pass' => (string) ($_ENV['DB_PASS'] ?? ''),
                    'charset' => trim((string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4')),
                    'timezone' => trim((string) ($_ENV['DB_TIMEZONE'] ?? '+00:00')),
                ],
                'logger' => [
                    'name' => 'slim-app',
                    'path' => $loggerPath,
                    'level' => Logger::DEBUG,
                ],
                'agenda' => [
                    'public_upcoming_limit' => max(1, min(100, (int) ($_ENV['APP_AGENDA_PUBLIC_LIMIT'] ?? 12))),
                ],
            ]);
        }
    ]);
};
