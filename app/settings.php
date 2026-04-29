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
            $appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? 'production'));
            $isDevelopment = in_array($appEnv, ['dev', 'development', 'local', 'test'], true);

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
                    'path' => ($_ENV['APP_ENV'] ?? '') === 'test'
                        ? 'php://stderr'
                        : (isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log'),
                    'level' => Logger::DEBUG,
                ],
                'agenda' => [
                    'public_upcoming_limit' => max(1, min(100, (int) ($_ENV['APP_AGENDA_PUBLIC_LIMIT'] ?? 12))),
                ],
            ]);
        }
    ]);
};
