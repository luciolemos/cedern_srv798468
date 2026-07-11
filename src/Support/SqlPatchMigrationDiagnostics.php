<?php

declare(strict_types=1);

namespace App\Support;

use App\Infrastructure\Database\SqlPatchMigrator;
use PDO;
use Throwable;

final class SqlPatchMigrationDiagnostics
{
    private PDO $pdo;
    private string $patchDirectory;

    public function __construct(PDO $pdo, string $patchDirectory)
    {
        $this->pdo = $pdo;
        $this->patchDirectory = rtrim(str_replace('\\', '/', $patchDirectory), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        try {
            $status = $this->migrator()->getStatus();
            $issues = $this->buildIssues($status);

            return [
                'status' => $this->resolveReportStatus($status),
                'mode' => 'report',
                'patch_directory' => $this->patchDirectory,
                'tracking_table_exists' => (bool) $status['tracking_table_exists'],
                'available_count' => count($status['available']),
                'applied_count' => count($status['applied']),
                'pending_count' => count($status['pending']),
                'orphaned_count' => count($status['orphaned']),
                'checksum_mismatch_count' => count($status['checksum_mismatches']),
                'pending' => $this->compactMigrations($status['pending']),
                'orphaned' => $this->compactMigrations($status['orphaned']),
                'checksum_mismatches' => $this->compactMigrations($status['checksum_mismatches']),
                'issues' => $issues,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'mode' => 'report',
                'patch_directory' => $this->patchDirectory,
                'error' => $exception->getMessage(),
                'issues' => [
                    [
                        'severity' => 'critical',
                        'code' => 'migration_status_unavailable',
                        'message' => 'Nao foi possivel avaliar o status dos patches SQL.',
                    ],
                ],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function applyPending(): array
    {
        $before = $this->report();

        if ((string) ($before['status'] ?? 'error') === 'error') {
            return [
                'status' => 'error',
                'mode' => 'apply',
                'report_before' => $before,
                'error' => 'Nao foi possivel obter o status inicial dos patches SQL.',
            ];
        }

        try {
            $summary = $this->migrator()->applyPending();
            $after = $this->report();

            return [
                'status' => (string) ($after['status'] ?? 'ok'),
                'mode' => 'apply',
                'patch_directory' => $this->patchDirectory,
                'apply_summary' => [
                    'applied_count' => $summary['applied_count'],
                    'pending_count_before' => $summary['pending_count_before'],
                    'orphaned_count' => $summary['orphaned_count'],
                    'applied' => $this->compactMigrations($summary['applied']),
                ],
                'report_before' => $before,
                'report_after' => $after,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'mode' => 'apply',
                'patch_directory' => $this->patchDirectory,
                'report_before' => $before,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $status
     * @return array<int, array<string, string>>
     */
    private function buildIssues(array $status): array
    {
        $issues = [];

        if (count($status['checksum_mismatches']) > 0) {
            $issues[] = [
                'severity' => 'critical',
                'code' => 'checksum_mismatch',
                'message' => 'Existem patches SQL aplicados com checksum divergente.',
            ];
        }

        if (count($status['orphaned']) > 0) {
            $issues[] = [
                'severity' => 'critical',
                'code' => 'orphaned_patches',
                'message' => 'Existem patches orfaos registrados no banco.',
            ];
        }

        if (count($status['pending']) > 0) {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'pending_patches',
                'message' => 'Existem patches SQL pendentes de aplicacao.',
            ];
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function resolveReportStatus(array $status): string
    {
        if (count($status['checksum_mismatches']) > 0 || count($status['orphaned']) > 0) {
            return 'error';
        }

        if (count($status['pending']) > 0) {
            return 'degraded';
        }

        return 'ok';
    }

    /**
     * @param array<int, array<string, mixed>> $migrations
     * @return array<int, array<string, mixed>>
     */
    private function compactMigrations(array $migrations): array
    {
        return array_map(
            static function (array $migration): array {
                return array_filter([
                    'key' => isset($migration['key']) ? (string) $migration['key'] : null,
                    'filename' => isset($migration['filename']) ? (string) $migration['filename'] : null,
                    'statement_count' => isset($migration['statement_count']) ? (int) $migration['statement_count'] : null,
                    'checksum_sha256' => isset($migration['checksum_sha256'])
                        ? (string) $migration['checksum_sha256']
                        : null,
                    'applied_at' => isset($migration['applied_at']) ? (string) $migration['applied_at'] : null,
                ], static fn (mixed $value): bool => $value !== null && $value !== '');
            },
            $migrations
        );
    }

    private function migrator(): SqlPatchMigrator
    {
        return new SqlPatchMigrator($this->pdo, $this->patchDirectory);
    }
}
