<?php

declare(strict_types=1);

use App\Infrastructure\Database\SqlPatchMigrator;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = parseOptions($argv);

if ($options['help']) {
    renderHelp();
    exit(0);
}

$projectRoot = dirname(__DIR__);

try {
    loadEnvironment($projectRoot);
    $patchDirectory = resolvePath($projectRoot, $options['path']);
    $pdo = createPdoFromEnvironment();
    $migrator = new SqlPatchMigrator($pdo, $patchDirectory);

    $status = $migrator->getStatus();
    renderStatus($status, $patchDirectory);

    if (!$options['apply']) {
        exit($status['checksum_mismatches'] === [] ? 0 : 2);
    }

    if ($status['checksum_mismatches'] !== []) {
        fwrite(STDERR, "Existem patches aplicados com checksum divergente. Corrija isso antes do --apply.\n");
        exit(2);
    }

    $summary = $migrator->applyPending();
    renderApplySummary($summary);

    if ($summary['orphaned_count'] > 0) {
        fwrite(STDERR, "Aviso: existem patches órfãos registrados no banco.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Falha no fluxo de migrations: {$exception->getMessage()}\n");
    exit(1);
}

/**
 * @return array{apply: bool, help: bool, path: string}
 */
function parseOptions(array $argv): array
{
    $options = [
        'apply' => false,
        'help' => false,
        'path' => 'database/patches',
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--apply') {
            $options['apply'] = true;
            continue;
        }

        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }

        if (str_starts_with($argument, '--path=')) {
            $path = trim(substr($argument, 7));
            if ($path === '') {
                fwrite(STDERR, "--path não pode ser vazio.\n");
                exit(1);
            }

            $options['path'] = $path;
            continue;
        }

        if ($argument === '--status') {
            continue;
        }

        fwrite(STDERR, "Opção inválida: {$argument}\n");
        exit(1);
    }

    return $options;
}

function renderHelp(): void
{
    echo "Uso:\n";
    echo "  php scripts/migrate.php [--path=database/patches] [--apply]\n\n";
    echo "Comportamento:\n";
    echo "  Sem --apply: mostra o status dos patches SQL.\n";
    echo "  Com --apply: aplica apenas os patches pendentes.\n";
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

function resolvePath(string $projectRoot, string $path): string
{
    if (str_starts_with($path, '/')) {
        return $path;
    }

    return $projectRoot . '/' . ltrim($path, '/');
}

function createPdoFromEnvironment(): PDO
{
    $host = trim((string) ($_ENV['DB_HOST'] ?? ''));
    $name = trim((string) ($_ENV['DB_NAME'] ?? ''));
    $user = trim((string) ($_ENV['DB_USER'] ?? ''));
    $pass = (string) ($_ENV['DB_PASS'] ?? '');
    $port = (int) ($_ENV['DB_PORT'] ?? 3306);
    $charset = trim((string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));
    $timezone = trim((string) ($_ENV['DB_TIMEZONE'] ?? '+00:00'));

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('Configuração de banco incompleta no ambiente atual.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $port,
        $name,
        $charset
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec(sprintf("SET time_zone = '%s'", str_replace("'", "''", $timezone)));

    return $pdo;
}

/**
 * @param array{
 *     available: array<int, array<string, mixed>>,
 *     applied: array<int, array<string, mixed>>,
 *     pending: array<int, array<string, mixed>>,
 *     orphaned: array<int, array<string, mixed>>,
 *     checksum_mismatches: array<int, array<string, mixed>>,
 *     tracking_table_exists: bool
 * } $status
 */
function renderStatus(array $status, string $patchDirectory): void
{
    echo "Status dos patches SQL\n";
    echo "Diretório: {$patchDirectory}\n";
    echo 'Disponíveis: ' . count($status['available']) . "\n";
    echo 'Aplicados: ' . count($status['applied']) . "\n";
    echo 'Pendentes: ' . count($status['pending']) . "\n";
    echo 'Órfãos: ' . count($status['orphaned']) . "\n";
    echo 'Checksums divergentes: ' . count($status['checksum_mismatches']) . "\n";

    if (empty($status['tracking_table_exists'])) {
        echo "Tabela schema_migrations: ainda não criada\n";
    }

    if ($status['pending'] !== []) {
        echo "\nPendentes:\n";
        foreach ($status['pending'] as $migration) {
            echo '- ' . (string) ($migration['key'] ?? '')
                . ' (' . (int) ($migration['statement_count'] ?? 0) . " statements)\n";
        }
    }

    if ($status['orphaned'] !== []) {
        echo "\nÓrfãos no banco:\n";
        foreach ($status['orphaned'] as $migration) {
            echo '- ' . (string) ($migration['key'] ?? '')
                . ' (aplicado em ' . (string) ($migration['applied_at'] ?? 'data desconhecida') . ")\n";
        }
    }

    if ($status['checksum_mismatches'] !== []) {
        echo "\nPatches com checksum divergente:\n";
        foreach ($status['checksum_mismatches'] as $migration) {
            echo '- ' . (string) ($migration['key'] ?? '') . "\n";
        }
    }

    echo "\n";
}

/**
 * @param array{
 *     applied_count: int,
 *     applied: array<int, array<string, mixed>>,
 *     pending_count_before: int,
 *     orphaned_count: int
 * } $summary
 */
function renderApplySummary(array $summary): void
{
    if ($summary['applied_count'] === 0) {
        echo "Nenhum patch pendente para aplicar.\n";
        return;
    }

    echo "Patches aplicados com sucesso: {$summary['applied_count']}\n";
    foreach ($summary['applied'] as $migration) {
        echo '- ' . (string) ($migration['key'] ?? '')
            . ' (' . (int) ($migration['statement_count'] ?? 0) . " statements)\n";
    }
}
