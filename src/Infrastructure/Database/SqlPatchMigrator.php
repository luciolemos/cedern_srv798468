<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use RuntimeException;
use Throwable;

final class SqlPatchMigrator
{
    private const MIGRATIONS_TABLE = 'schema_migrations';

    private PDO $pdo;
    private string $patchDirectory;
    private SqlStatementSplitter $statementSplitter;

    public function __construct(PDO $pdo, string $patchDirectory, ?SqlStatementSplitter $statementSplitter = null)
    {
        $this->pdo = $pdo;
        $this->patchDirectory = rtrim($patchDirectory, '/');
        $this->statementSplitter = $statementSplitter ?? new SqlStatementSplitter();
    }

    /**
     * @return array{
     *     available: array<int, array<string, mixed>>,
     *     applied: array<int, array<string, mixed>>,
     *     pending: array<int, array<string, mixed>>,
     *     orphaned: array<int, array<string, mixed>>,
     *     checksum_mismatches: array<int, array<string, mixed>>,
     *     tracking_table_exists: bool
     * }
     */
    public function getStatus(): array
    {
        $available = $this->readPatchFiles();
        $availableByKey = [];
        foreach ($available as $migration) {
            $availableByKey[(string) $migration['key']] = $migration;
        }

        $trackingTableExists = $this->hasMigrationsTable();
        $appliedRows = $trackingTableExists ? $this->fetchAppliedMigrations() : [];
        $appliedByKey = [];
        foreach ($appliedRows as $migration) {
            $appliedByKey[(string) $migration['key']] = $migration;
        }

        $applied = [];
        $pending = [];
        $checksumMismatches = [];

        foreach ($available as $migration) {
            $key = (string) $migration['key'];
            $appliedRow = $appliedByKey[$key] ?? null;

            if ($appliedRow === null) {
                $pending[] = $migration;
                continue;
            }

            $entry = array_merge($migration, [
                'applied_at' => $appliedRow['applied_at'] ?? null,
                'stored_checksum_sha256' => $appliedRow['checksum_sha256'] ?? '',
            ]);

            $applied[] = $entry;

            if ((string) ($appliedRow['checksum_sha256'] ?? '') !== (string) $migration['checksum_sha256']) {
                $checksumMismatches[] = $entry;
            }
        }

        $orphaned = [];
        foreach ($appliedRows as $appliedRow) {
            $key = (string) ($appliedRow['key'] ?? '');
            if ($key === '' || isset($availableByKey[$key])) {
                continue;
            }

            $orphaned[] = $appliedRow;
        }

        return [
            'available' => $available,
            'applied' => $applied,
            'pending' => $pending,
            'orphaned' => $orphaned,
            'checksum_mismatches' => $checksumMismatches,
            'tracking_table_exists' => $trackingTableExists,
        ];
    }

    /**
     * @return array{
     *     applied_count: int,
     *     applied: array<int, array<string, mixed>>,
     *     pending_count_before: int,
     *     orphaned_count: int
     * }
     */
    public function applyPending(): array
    {
        $status = $this->getStatus();
        $mismatches = $status['checksum_mismatches'];

        if ($mismatches !== []) {
            $keys = array_map(
                static fn (array $migration): string => (string) ($migration['key'] ?? ''),
                $mismatches
            );

            throw new RuntimeException(
                'Existem patches aplicados com checksum diferente do arquivo atual: ' . implode(', ', $keys)
            );
        }

        $this->ensureMigrationsTable();

        $appliedNow = [];

        foreach ($status['pending'] as $migration) {
            $key = (string) ($migration['key'] ?? '');
            $statements = (array) ($migration['statements'] ?? []);
            $requiresNonTransactionalExecution = $this->requiresNonTransactionalExecution($statements);

            try {
                if (!$requiresNonTransactionalExecution) {
                    $this->pdo->beginTransaction();
                }

                foreach ($statements as $statement) {
                    $normalizedStatement = trim((string) $statement);
                    if ($normalizedStatement === '') {
                        continue;
                    }

                    $this->pdo->exec($normalizedStatement);
                }

                $insertStatement = $this->pdo->prepare(
                    'INSERT INTO ' . self::MIGRATIONS_TABLE . ' (migration_key, checksum_sha256, applied_at) '
                    . 'VALUES (:migration_key, :checksum_sha256, NOW())'
                );

                $insertStatement->execute([
                    'migration_key' => $key,
                    'checksum_sha256' => (string) ($migration['checksum_sha256'] ?? ''),
                ]);

                if (!$requiresNonTransactionalExecution && $this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
            } catch (Throwable $exception) {
                if (!$requiresNonTransactionalExecution && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw new RuntimeException(
                    'Falha ao aplicar o patch "' . $key . '": ' . $exception->getMessage()
                    . ($requiresNonTransactionalExecution
                        ? ' O patch pode ter sido aplicado parcialmente e deve ser revisado no banco.'
                        : ''),
                    0,
                    $exception
                );
            }

            $migration['applied_at'] = date('Y-m-d H:i:s');
            $migration['transaction_strategy'] = $requiresNonTransactionalExecution
                ? 'non_transactional'
                : 'transactional';
            $appliedNow[] = $migration;
        }

        return [
            'applied_count' => count($appliedNow),
            'applied' => $appliedNow,
            'pending_count_before' => count($status['pending']),
            'orphaned_count' => count($status['orphaned']),
        ];
    }

    /**
     * @param array<int, string> $statements
     */
    private function requiresNonTransactionalExecution(array $statements): bool
    {
        foreach ($statements as $statement) {
            $normalizedStatement = ltrim((string) $statement);

            if (
                preg_match(
                    '/^(ALTER|CREATE|DROP|TRUNCATE|RENAME|GRANT|REVOKE|ANALYZE|OPTIMIZE|REPAIR|FLUSH|LOCK|UNLOCK)\b/i',
                    $normalizedStatement
                ) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    private function ensureMigrationsTable(): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS schema_migrations (
                migration_key VARCHAR(190) NOT NULL PRIMARY KEY,
                checksum_sha256 CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL,
                KEY idx_schema_migrations_applied_at (applied_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $this->pdo->exec($sql);
    }

    private function hasMigrationsTable(): bool
    {
        try {
            $statement = $this->pdo->query(
                'SELECT migration_key FROM ' . self::MIGRATIONS_TABLE . ' LIMIT 1'
            );

            return $statement !== false;
        } catch (Throwable $exception) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAppliedMigrations(): array
    {
        $statement = $this->pdo->query(
            'SELECT migration_key, checksum_sha256, applied_at '
            . 'FROM ' . self::MIGRATIONS_TABLE . ' ORDER BY migration_key ASC'
        );

        $rows = $statement !== false ? ($statement->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        return array_map(
            static function (array $row): array {
                return [
                    'key' => (string) ($row['migration_key'] ?? ''),
                    'checksum_sha256' => (string) ($row['checksum_sha256'] ?? ''),
                    'applied_at' => (string) ($row['applied_at'] ?? ''),
                ];
            },
            $rows
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readPatchFiles(): array
    {
        if (!is_dir($this->patchDirectory)) {
            throw new RuntimeException('Diretório de patches não encontrado: ' . $this->patchDirectory);
        }

        $paths = glob($this->patchDirectory . '/*.sql');
        if ($paths === false) {
            throw new RuntimeException('Não foi possível listar os patches SQL em ' . $this->patchDirectory);
        }

        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        $migrations = [];

        foreach ($paths as $path) {
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('Não foi possível ler o patch SQL: ' . $path);
            }

            $filename = basename($path);
            $key = preg_replace('/\.sql$/i', '', $filename) ?? $filename;
            $statements = $this->statementSplitter->split($content);

            $migrations[] = [
                'key' => $key,
                'filename' => $filename,
                'path' => $path,
                'checksum_sha256' => hash('sha256', $content),
                'statements' => $statements,
                'statement_count' => count($statements),
            ];
        }

        return $migrations;
    }
}
