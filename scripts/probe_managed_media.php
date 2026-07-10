<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Support\ManagedMediaPathReport;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
loadEnvironment($projectRoot);

$options = parseProbeOptions($argv);
$kind = $options['kind'] ?? null;
$file = $options['file'] ?? null;

if ($kind === null || $file === null) {
    fwrite(
        STDERR,
        "Usage: php scripts/probe_managed_media.php --kind KIND --file FILE [--json]\n"
    );
    exit(1);
}

$report = (new ManagedMediaPathReport($projectRoot))->build($kind, $file);

if (!empty($options['json'])) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

$probe = (array) ($report['probe'] ?? []);
$managedStorageRoot = (array) ($report['managed_storage_root'] ?? []);

echo 'Projeto: ' . ($report['project_root'] ?? $projectRoot) . PHP_EOL;
echo 'APP_MANAGED_STORAGE_ROOT bruto: '
    . (($managedStorageRoot['raw'] ?? '') !== '' ? $managedStorageRoot['raw'] : '(vazio)') . PHP_EOL;
echo 'APP_MANAGED_STORAGE_ROOT resolvido: '
    . (($managedStorageRoot['path'] ?? null) !== null ? $managedStorageRoot['path'] : '(desativado)') . PHP_EOL;
echo 'Probe: ' . ($probe['kind'] ?? $kind) . ' / ' . ($probe['file'] ?? $file) . PHP_EOL;

if (isset($probe['error'])) {
    echo 'Erro: ' . $probe['error'] . PHP_EOL;
    exit(1);
}

$matches = (array) ($probe['existing_matches'] ?? []);
echo 'Correspondencias encontradas: ' . count($matches) . PHP_EOL;

foreach ((array) ($probe['candidates'] ?? []) as $candidate) {
    $exists = !empty($candidate['exists']) ? 'sim' : 'nao';
    $readable = !empty($candidate['readable']) ? 'sim' : 'nao';
    $label = (string) ($candidate['label'] ?? 'candidate');
    $path = (string) ($candidate['path'] ?? '');
    $publicUrl = (string) ($candidate['public_url'] ?? '');

    echo PHP_EOL . '[' . $label . ']' . PHP_EOL;
    echo 'path: ' . $path . PHP_EOL;
    echo 'exists: ' . $exists . PHP_EOL;
    echo 'readable: ' . $readable . PHP_EOL;

    if ($publicUrl !== '') {
        echo 'public_url: ' . $publicUrl . PHP_EOL;
    }

    if (($candidate['permissions'] ?? null) !== null) {
        echo 'permissions: ' . $candidate['permissions'] . PHP_EOL;
    }
}

/**
 * @return array{kind?: string, file?: string, json?: bool}
 */
function parseProbeOptions(array $argv): array
{
    $options = [];
    $arguments = array_slice($argv, 1);

    while ($arguments !== []) {
        $argument = array_shift($arguments);

        if ($argument === '--kind') {
            $options['kind'] = takeProbeOptionValue($argument, $arguments);
            continue;
        }

        if ($argument === '--file') {
            $options['file'] = takeProbeOptionValue($argument, $arguments);
            continue;
        }

        if ($argument === '--json') {
            $options['json'] = true;
            continue;
        }

        fwrite(STDERR, "Unknown argument: {$argument}\n");
        exit(1);
    }

    return $options;
}

/**
 * @param array<int, string> $arguments
 */
function takeProbeOptionValue(string $optionName, array &$arguments): string
{
    $value = array_shift($arguments);
    if ($value === null || trim($value) === '') {
        fwrite(STDERR, "Missing value for {$optionName}\n");
        exit(1);
    }

    return trim($value);
}

function loadEnvironment(string $projectRoot): void
{
    $envFileFromServer = '';
    $appEnvFileFromGetenv = getenv('APP_ENV_FILE');
    if ($appEnvFileFromGetenv !== false) {
        $envFileFromServer = trim((string) $appEnvFileFromGetenv);
    } elseif (isset($_SERVER['APP_ENV_FILE'])) {
        $envFileFromServer = trim((string) $_SERVER['APP_ENV_FILE']);
    } elseif (isset($_ENV['APP_ENV_FILE'])) {
        $envFileFromServer = trim((string) $_ENV['APP_ENV_FILE']);
    }

    $dotenvLoaded = false;
    if ($envFileFromServer !== '') {
        $resolvedEnvFilePath = str_starts_with($envFileFromServer, '/')
            ? $envFileFromServer
            : $projectRoot . '/' . ltrim($envFileFromServer, '/');

        if (is_file($resolvedEnvFilePath)) {
            Dotenv::createImmutable(dirname($resolvedEnvFilePath), basename($resolvedEnvFilePath))->safeLoad();
            $dotenvLoaded = true;
        }
    }

    if (!$dotenvLoaded && is_file($projectRoot . '/.env')) {
        Dotenv::createImmutable($projectRoot)->safeLoad();
    }
}
