<?php

declare(strict_types=1);

use App\Application\Billing\ContributionBillingCycleRunner;
use App\Domain\Billing\ContributionBillingGateway;
use App\Domain\Member\MemberAuthRepository;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = parseOptions($argv);

if ($options['help']) {
    renderHelp();
    exit(0);
}

$projectRoot = dirname(__DIR__);
loadEnvironment($projectRoot);
$lockHandle = null;

if (!$options['no_lock']) {
    $lockFile = resolveLockFilePath($projectRoot, $options['lock_file']);
    $lockHandle = acquireLock($lockFile);

    if (!is_resource($lockHandle)) {
        fwrite(STDERR, 'Outro processo do ciclo de cobrancas ja esta em execucao.' . "\n");
        exit(3);
    }
}

$containerBuilder = new ContainerBuilder();

$settings = require $projectRoot . '/app/settings.php';
$settings($containerBuilder);

$dependencies = require $projectRoot . '/app/dependencies.php';
$dependencies($containerBuilder);

$repositories = require $projectRoot . '/app/repositories.php';
$repositories($containerBuilder);

$container = $containerBuilder->build();

/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);
/** @var MemberAuthRepository $memberAuthRepository */
$memberAuthRepository = $container->get(MemberAuthRepository::class);
/** @var ContributionBillingGateway $billingGateway */
$billingGateway = $container->get(ContributionBillingGateway::class);

if ($memberAuthRepository instanceof FallbackMemberAuthRepository) {
    fwrite(
        STDERR,
        "Repositorio de membros em memoria detectado. Verifique o banco/configuracao antes de rodar a automacao.\n"
    );
    exit(1);
}

$runner = new ContributionBillingCycleRunner($logger, $memberAuthRepository, $billingGateway);

try {
    $summary = $runner->run($options['competence'], $options['billing_mode']);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha ao processar ciclo de cobrancas: ' . $exception->getMessage() . "\n");
    exit(1);
}

renderSummary($summary);

exit($summary['external']['failures'] === [] ? 0 : 2);

/**
 * @return array{competence: string, billing_mode: string, help: bool, lock_file: string, no_lock: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'competence' => '',
        'billing_mode' => 'preferred',
        'help' => false,
        'lock_file' => 'var/locks/contribution-billing-cycle.lock',
        'no_lock' => false,
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }

        if ($argument === '--no-lock') {
            $options['no_lock'] = true;
            continue;
        }

        if (strpos($argument, '--competence=') === 0) {
            $options['competence'] = trim(substr($argument, 13));
            continue;
        }

        if (strpos($argument, '--billing-mode=') === 0) {
            $options['billing_mode'] = trim(substr($argument, 15));
            continue;
        }

        if (strpos($argument, '--lock-file=') === 0) {
            $options['lock_file'] = trim(substr($argument, 12));
            continue;
        }

        fwrite(STDERR, "Opcao invalida: {$argument}\n");
        exit(1);
    }

    return $options;
}

function renderHelp(): void
{
    echo "Uso:\n";
    echo "  php scripts/process_contribution_billing_cycle.php [--competence=AAAA-MM] [--billing-mode=preferred|pix|boleto] [--lock-file=/caminho/arquivo.lock] [--no-lock]\n\n";
    echo "Comportamento:\n";
    echo "  Gera cobrancas mensais locais para a competencia informada e cria no Asaas as cobrancas pendentes.\n";
    echo "  Sem --competence, usa a competencia atual em America/Fortaleza.\n";
    echo "  Com --billing-mode=preferred, respeita a forma preferida do associado.\n";
    echo "  Por padrao usa lock em var/locks/contribution-billing-cycle.lock para evitar concorrencia em cron.\n";
}

function loadEnvironment(string $projectRoot): void
{
    $envFile = getenv('APP_ENV_FILE');
    $envFile = $envFile === false ? '' : trim((string) $envFile);

    if ($envFile === '' && isset($_SERVER['APP_ENV_FILE'])) {
        $envFile = trim((string) $_SERVER['APP_ENV_FILE']);
    }

    if ($envFile === '' && isset($_ENV['APP_ENV_FILE'])) {
        $envFile = trim((string) $_ENV['APP_ENV_FILE']);
    }

    if ($envFile !== '') {
        $resolvedEnvFilePath = str_starts_with($envFile, '/')
            ? $envFile
            : $projectRoot . '/' . ltrim($envFile, '/');

        if (is_file($resolvedEnvFilePath)) {
            Dotenv::createImmutable(dirname($resolvedEnvFilePath), basename($resolvedEnvFilePath))->safeLoad();
            return;
        }
    }

    if (is_file($projectRoot . '/.env')) {
        Dotenv::createImmutable($projectRoot)->safeLoad();
    }
}

function resolveLockFilePath(string $projectRoot, string $configuredPath): string
{
    $normalizedPath = trim($configuredPath);
    if ($normalizedPath === '') {
        $normalizedPath = 'var/locks/contribution-billing-cycle.lock';
    }

    return str_starts_with($normalizedPath, '/')
        ? $normalizedPath
        : $projectRoot . '/' . ltrim($normalizedPath, '/');
}

/**
 * @return resource|false
 */
function acquireLock(string $lockFile)
{
    $lockDirectory = dirname($lockFile);

    if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0775, true) && !is_dir($lockDirectory)) {
        fwrite(STDERR, 'Nao foi possivel criar o diretorio de lock: ' . $lockDirectory . "\n");
        exit(1);
    }

    $handle = fopen($lockFile, 'c+');
    if ($handle === false) {
        fwrite(STDERR, 'Nao foi possivel abrir o arquivo de lock: ' . $lockFile . "\n");
        exit(1);
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);

        return false;
    }

    ftruncate($handle, 0);
    fwrite($handle, (string) getmypid());
    fflush($handle);

    return $handle;
}

/**
 * @param array{
 *     competence: string,
 *     billing_mode: string,
 *     local: array{created: int, skipped_existing: int, skipped_incomplete_profile: int},
 *     external: array{
 *         created: int,
 *         skipped_existing: int,
 *         skipped_non_pending: int,
 *         skipped_missing_context: int,
 *         failures: list<array{charge_id: int, member_user_id: int, member_name: string, error: string}>
 *     }
 * } $summary
 */
function renderSummary(array $summary): void
{
    echo "Ciclo de cobrancas processado.\n";
    echo 'Competencia: ' . $summary['competence'] . "\n";
    echo 'Modo de cobranca: ' . $summary['billing_mode'] . "\n";
    echo 'Locais criadas: ' . $summary['local']['created'] . "\n";
    echo 'Locais ja existentes: ' . $summary['local']['skipped_existing'] . "\n";
    echo 'Locais ignoradas por cadastro incompleto: ' . $summary['local']['skipped_incomplete_profile'] . "\n";
    echo 'Asaas criadas: ' . $summary['external']['created'] . "\n";
    echo 'Asaas ignoradas por ja existirem: ' . $summary['external']['skipped_existing'] . "\n";
    echo 'Asaas ignoradas por status nao pendente: ' . $summary['external']['skipped_non_pending'] . "\n";
    echo 'Asaas ignoradas por contexto ausente: ' . $summary['external']['skipped_missing_context'] . "\n";
    echo 'Falhas externas: ' . count($summary['external']['failures']) . "\n";

    if ($summary['external']['failures'] === []) {
        return;
    }

    echo "\nFalhas:\n";

    foreach ($summary['external']['failures'] as $failure) {
        echo sprintf(
            '- charge #%d | member #%d | %s | %s',
            $failure['charge_id'],
            $failure['member_user_id'],
            $failure['member_name'],
            $failure['error']
        ) . "\n";
    }
}
