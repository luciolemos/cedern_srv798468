<?php

declare(strict_types=1);

namespace App\Support;

use App\Application\Security\RecaptchaVerifier;
use App\Application\Settings\SettingsInterface;
use App\Domain\Agenda\AgendaRepository;
use App\Domain\Analytics\SiteVisitRepository;
use App\Domain\Bookshop\BookshopRepository;
use App\Domain\Institutional\InstitutionalContentRepository;
use App\Domain\Library\LibraryRepository;
use App\Domain\Member\MemberAuthRepository;
use App\Domain\Patrimony\PatrimonyRepository;
use App\Infrastructure\Database\SqlPatchMigrator;
use PDO;
use Psr\Container\ContainerInterface;
use Throwable;

final class OperationalReadinessReport
{
    private ContainerInterface $container;
    private string $projectRoot;

    public function __construct(ContainerInterface $container, string $projectRoot)
    {
        $this->container = $container;
        $this->projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $loggerStatus = $this->buildLoggerStatus();
        $databaseStatus = $this->buildDatabaseStatus();
        $repositoryStatus = $this->buildRepositoryStatus();
        $migrationStatus = $this->buildMigrationStatus();
        $storageStatus = $this->buildStorageStatus();
        $securityStatus = $this->buildSecurityStatus();

        $issues = array_merge(
            $loggerStatus['issues'],
            $databaseStatus['issues'],
            $repositoryStatus['issues'],
            $migrationStatus['issues'],
            $storageStatus['issues'],
            $securityStatus['issues']
        );

        return [
            'status' => $this->resolveOverallStatus($issues),
            'generated_at' => gmdate('c'),
            'environment' => [
                'app_env' => RuntimeSafety::readString('APP_ENV', $_ENV, 'production'),
                'app_base' => RuntimeSafety::readString('APP_BASE', $_ENV),
                'app_asset_version' => RuntimeSafety::readString('APP_ASSET_VERSION', $_ENV, '1'),
                'managed_storage_root_raw' => RuntimeSafety::readString('APP_MANAGED_STORAGE_ROOT', $_ENV),
            ],
            'security' => $securityStatus['payload'],
            'logger' => $loggerStatus['payload'],
            'database' => $databaseStatus['payload'],
            'repositories' => $repositoryStatus['payload'],
            'migrations' => $migrationStatus['payload'],
            'storage' => $storageStatus['payload'],
            'issues' => $issues,
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, issues: array<int, array<string, string>>}
     */
    private function buildSecurityStatus(): array
    {
        $recaptchaVerifier = new RecaptchaVerifier();
        $payload = [
            'repository_fallback_allowed' => RuntimeSafety::repositoryFallbackAllowed($_ENV),
            'diagnostics_feature_enabled' => RuntimeSafety::diagnosticsFeatureEnabled($_ENV),
            'diagnostic_token_configured' => RuntimeSafety::readString('APP_DIAGNOSTIC_TOKEN', $_ENV) !== '',
            'recaptcha_enabled' => RuntimeSafety::readBool('RECAPTCHA_ENABLED', $_ENV, false),
            'recaptcha_site_key_configured' => RuntimeSafety::readString('RECAPTCHA_SITE_KEY', $_ENV) !== '',
            'recaptcha_secret_key_configured' => RuntimeSafety::readString('RECAPTCHA_SECRET_KEY', $_ENV) !== '',
            'recaptcha_allowed_hostname' => RuntimeSafety::readString('RECAPTCHA_ALLOWED_HOSTNAME', $_ENV),
            'recaptcha_min_score' => $recaptchaVerifier->getMinScore(),
        ];

        $issues = [];

        if (!$this->isDevelopmentLike() && $payload['repository_fallback_allowed'] === true) {
            $issues[] = $this->buildIssue(
                'critical',
                'security',
                'repository_fallback_enabled',
                'APP_ALLOW_REPOSITORY_FALLBACK permanece ativo em producao.'
            );
        }

        if (
            !$this->isDevelopmentLike()
            && $payload['diagnostics_feature_enabled'] === true
            && $payload['diagnostic_token_configured'] === false
        ) {
            $issues[] = $this->buildIssue(
                'critical',
                'security',
                'diagnostics_unprotected',
                'Rotas diagnosticas estao liberadas sem APP_DIAGNOSTIC_TOKEN em producao.'
            );
        }

        if (
            $payload['recaptcha_enabled'] === true
            && (
                $payload['recaptcha_site_key_configured'] === false
                || $payload['recaptcha_secret_key_configured'] === false
            )
        ) {
            $issues[] = $this->buildIssue(
                'critical',
                'security',
                'recaptcha_incomplete',
                'reCAPTCHA habilitado sem chaves completas no ambiente.'
            );
        }

        return [
            'payload' => $payload,
            'issues' => $issues,
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, issues: array<int, array<string, string>>}
     */
    private function buildLoggerStatus(): array
    {
        /** @var SettingsInterface $settings */
        $settings = $this->container->get(SettingsInterface::class);
        $loggerSettings = (array) $settings->get('logger');
        $loggerPath = trim((string) ($loggerSettings['path'] ?? ''));
        $isStreamTarget = preg_match('#^[a-z0-9.+-]+://#i', $loggerPath) === 1;
        $loggerDirectory = $isStreamTarget || $loggerPath === '' ? null : dirname($loggerPath);

        $payload = [
            'path' => $loggerPath,
            'is_stream_target' => $isStreamTarget,
            'target_exists' => $loggerPath !== '' && file_exists($loggerPath),
            'target_is_file' => $loggerPath !== '' && is_file($loggerPath),
            'target_is_writable' => $loggerPath !== '' && is_writable($loggerPath),
            'directory' => $loggerDirectory,
            'directory_exists' => $loggerDirectory !== null && is_dir($loggerDirectory),
            'directory_is_writable' => $loggerDirectory !== null && is_writable($loggerDirectory),
        ];

        $issues = [];

        if (!$isStreamTarget) {
            $targetReady = $payload['target_exists'] === true && $payload['target_is_writable'] === true;
            $directoryReady = $payload['directory_exists'] === true && $payload['directory_is_writable'] === true;

            if (!$targetReady && !$directoryReady) {
                $issues[] = $this->buildIssue(
                    'critical',
                    'logger',
                    'logger_not_writable',
                    'Logger sem arquivo gravavel e sem diretorio gravavel no ambiente atual.'
                );
            }
        }

        return [
            'payload' => $payload,
            'issues' => $issues,
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, issues: array<int, array<string, string>>}
     */
    private function buildDatabaseStatus(): array
    {
        $payload = [
            'connected' => false,
            'database_name' => null,
            'server_version' => null,
            'error' => null,
        ];
        $issues = [];

        try {
            /** @var PDO $pdo */
            $pdo = $this->container->get(PDO::class);
            $row = $pdo->query('SELECT DATABASE() AS database_name, VERSION() AS server_version')->fetch(PDO::FETCH_ASSOC);

            $payload['connected'] = true;
            $payload['database_name'] = is_array($row) ? (string) ($row['database_name'] ?? '') : null;
            $payload['server_version'] = is_array($row) ? (string) ($row['server_version'] ?? '') : null;
        } catch (Throwable $exception) {
            $payload['error'] = $exception->getMessage();
            $issues[] = $this->buildIssue(
                'critical',
                'database',
                'db_unavailable',
                'Conexao PDO indisponivel para a aplicacao.'
            );
        }

        return [
            'payload' => $payload,
            'issues' => $issues,
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, issues: array<int, array<string, string>>}
     */
    private function buildRepositoryStatus(): array
    {
        $repositoryMap = [
            'agenda' => AgendaRepository::class,
            'analytics' => SiteVisitRepository::class,
            'bookshop' => BookshopRepository::class,
            'institutional' => InstitutionalContentRepository::class,
            'library' => LibraryRepository::class,
            'member_auth' => MemberAuthRepository::class,
            'patrimony' => PatrimonyRepository::class,
        ];

        $items = [];
        $issues = [];
        $fallbackCount = 0;
        $errorCount = 0;

        foreach ($repositoryMap as $label => $interface) {
            try {
                $repository = $this->container->get($interface);
                $className = $repository::class;
                $isFallback = str_contains($className, '\\Fallback');

                if ($isFallback) {
                    $fallbackCount += 1;
                    $issues[] = $this->buildIssue(
                        'critical',
                        'repository',
                        'fallback_' . $label,
                        'Repositorio em fallback: ' . $label
                    );
                }

                $items[$label] = [
                    'interface' => $interface,
                    'resolved_class' => $className,
                    'is_fallback' => $isFallback,
                    'status' => $isFallback ? 'fallback' : 'ok',
                ];
            } catch (Throwable $exception) {
                $errorCount += 1;
                $items[$label] = [
                    'interface' => $interface,
                    'resolved_class' => null,
                    'is_fallback' => false,
                    'status' => 'error',
                    'error' => $exception->getMessage(),
                ];

                $issues[] = $this->buildIssue(
                    'critical',
                    'repository',
                    'repository_error_' . $label,
                    'Repositorio indisponivel: ' . $label
                );
            }
        }

        return [
            'payload' => [
                'fallback_allowed' => RuntimeSafety::repositoryFallbackAllowed($_ENV),
                'fallback_count' => $fallbackCount,
                'error_count' => $errorCount,
                'items' => $items,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, issues: array<int, array<string, string>>}
     */
    private function buildMigrationStatus(): array
    {
        $payload = [
            'available_count' => null,
            'applied_count' => null,
            'pending_count' => null,
            'orphaned_count' => null,
            'checksum_mismatch_count' => null,
            'tracking_table_exists' => null,
            'error' => null,
        ];
        $issues = [];

        try {
            /** @var PDO $pdo */
            $pdo = $this->container->get(PDO::class);
            $migrator = new SqlPatchMigrator($pdo, $this->projectRoot . '/database/patches');
            $status = $migrator->getStatus();

            $payload['available_count'] = count($status['available']);
            $payload['applied_count'] = count($status['applied']);
            $payload['pending_count'] = count($status['pending']);
            $payload['orphaned_count'] = count($status['orphaned']);
            $payload['checksum_mismatch_count'] = count($status['checksum_mismatches']);
            $payload['tracking_table_exists'] = (bool) $status['tracking_table_exists'];

            if ($payload['checksum_mismatch_count'] > 0) {
                $issues[] = $this->buildIssue(
                    'critical',
                    'migrations',
                    'checksum_mismatch',
                    'Existem patches SQL aplicados com checksum divergente.'
                );
            }

            if ($payload['orphaned_count'] > 0) {
                $issues[] = $this->buildIssue(
                    'critical',
                    'migrations',
                    'orphaned_patches',
                    'Existem patches orfaos registrados no banco.'
                );
            }

            if ($payload['pending_count'] > 0) {
                $issues[] = $this->buildIssue(
                    'warning',
                    'migrations',
                    'pending_patches',
                    'Existem patches SQL pendentes de aplicacao.'
                );
            }
        } catch (Throwable $exception) {
            $payload['error'] = $exception->getMessage();
            $issues[] = $this->buildIssue(
                'critical',
                'migrations',
                'migration_status_unavailable',
                'Nao foi possivel avaliar o status das migrations SQL.'
            );
        }

        return [
            'payload' => $payload,
            'issues' => $issues,
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, issues: array<int, array<string, string>>}
     */
    private function buildStorageStatus(): array
    {
        $report = new ManagedMediaPathReport($this->projectRoot);
        $rawReport = $report->build();

        $payload = [
            'managed_storage_root' => $rawReport['managed_storage_root'] ?? [],
            'targets' => [],
        ];
        $issues = [];

        if (
            RuntimeSafety::readString('APP_MANAGED_STORAGE_ROOT', $_ENV) !== ''
            && (($rawReport['managed_storage_root']['exists'] ?? false) !== true
                || ($rawReport['managed_storage_root']['writable'] ?? false) !== true)
        ) {
            $issues[] = $this->buildIssue(
                'critical',
                'storage',
                'managed_storage_root_unavailable',
                'APP_MANAGED_STORAGE_ROOT configurado sem diretorio existente e gravavel.'
            );
        }

        foreach ((array) ($rawReport['targets'] ?? []) as $label => $target) {
            $configuredDirectory = (array) ($target['configured_directory'] ?? []);
            $payload['targets'][$label] = [
                'path' => (string) ($configuredDirectory['path'] ?? ''),
                'exists' => (bool) ($configuredDirectory['exists'] ?? false),
                'writable' => (bool) ($configuredDirectory['writable'] ?? false),
                'public_prefix' => (string) ($target['configured_public_prefix'] ?? ''),
            ];

            if (
                ($configuredDirectory['exists'] ?? false) !== true
                || ($configuredDirectory['writable'] ?? false) !== true
            ) {
                $issues[] = $this->buildIssue(
                    'critical',
                    'storage',
                    'storage_unready_' . $label,
                    'Diretorio configurado de storage indisponivel para ' . $label . '.'
                );
            }
        }

        return [
            'payload' => $payload,
            'issues' => $issues,
        ];
    }

    /**
     * @param array<int, array<string, string>> $issues
     */
    private function resolveOverallStatus(array $issues): string
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'critical') {
                return 'error';
            }
        }

        return $issues === [] ? 'ok' : 'degraded';
    }

    private function isDevelopmentLike(): bool
    {
        return RuntimeSafety::isDevelopmentLike($_ENV);
    }

    /**
     * @return array<string, string>
     */
    private function buildIssue(string $severity, string $component, string $code, string $message): array
    {
        return [
            'severity' => $severity,
            'component' => $component,
            'code' => $code,
            'message' => $message,
        ];
    }
}
