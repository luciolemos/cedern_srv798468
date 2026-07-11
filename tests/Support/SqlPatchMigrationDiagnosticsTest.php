<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\SqlPatchMigrationDiagnostics;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class SqlPatchMigrationDiagnosticsTest extends TestCase
{
    private string $patchDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patchDirectory = sys_get_temp_dir() . '/cedern-migration-diag-' . bin2hex(random_bytes(6));
        mkdir($this->patchDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $paths = glob($this->patchDirectory . '/*');
        if ($paths !== false) {
            foreach ($paths as $path) {
                @unlink($path);
            }
        }

        @rmdir($this->patchDirectory);

        parent::tearDown();
    }

    public function testReportShowsDegradedWhenThereArePendingPatches(): void
    {
        $this->createPatch(
            '2026-07-08-001-normalize.sql',
            'UPDATE member_users SET status = "pending" WHERE status IS NULL;'
        );

        $diagnostics = new SqlPatchMigrationDiagnostics(new MigrationDiagnosticsFakePdo(), $this->patchDirectory);
        $report = $diagnostics->report();

        $this->assertSame('degraded', $report['status'] ?? null);
        $this->assertSame(1, $report['pending_count'] ?? null);
        $this->assertSame('pending_patches', $report['issues'][0]['code'] ?? null);
    }

    public function testApplyPendingReturnsOkAndClearsPendingCount(): void
    {
        $this->createPatch(
            '2026-07-08-001-normalize.sql',
            'UPDATE member_users SET status = "pending" WHERE status IS NULL;'
        );

        $pdo = new MigrationDiagnosticsFakePdo();
        $diagnostics = new SqlPatchMigrationDiagnostics($pdo, $this->patchDirectory);
        $payload = $diagnostics->applyPending();

        $this->assertSame('ok', $payload['status'] ?? null);
        $this->assertSame(1, $payload['apply_summary']['applied_count'] ?? null);
        $this->assertSame(0, $payload['report_after']['pending_count'] ?? null);
        $this->assertCount(1, $pdo->appliedRows);
    }

    private function createPatch(string $filename, string $content): void
    {
        file_put_contents($this->patchDirectory . '/' . $filename, $content);
    }
}

final class MigrationDiagnosticsFakePdo extends PDO
{
    /** @var array<int, array<string, string>> */
    public array $appliedRows;

    private bool $transactionOpen = false;

    /**
     * @param array<int, array<string, string>> $appliedRows
     */
    public function __construct(array $appliedRows = [])
    {
        $this->appliedRows = array_values($appliedRows);
    }

    public function exec(string $statement): int|false
    {
        return 0;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_contains($query, 'FROM schema_migrations')) {
            return MigrationDiagnosticsFakePdoStatement::create($this->appliedRows);
        }

        return MigrationDiagnosticsFakePdoStatement::create([]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return MigrationDiagnosticsFakePreparedStatement::createFromQuery($this, $query);
    }

    public function beginTransaction(): bool
    {
        $this->transactionOpen = true;

        return true;
    }

    public function commit(): bool
    {
        $this->transactionOpen = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->transactionOpen = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionOpen;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function registerAppliedMigration(array $params): void
    {
        $this->appliedRows[] = [
            'migration_key' => (string) ($params['migration_key'] ?? ''),
            'checksum_sha256' => (string) ($params['checksum_sha256'] ?? ''),
            'applied_at' => '2026-07-11 08:00:00',
        ];
    }
}

class MigrationDiagnosticsFakePdoStatement extends PDOStatement
{
    /** @var array<int, mixed> */
    protected array $rows;

    /**
     * @param array<int, mixed> $rows
     */
    public function __construct(array $rows = [])
    {
        $this->rows = array_values($rows);
    }

    /**
     * @param array<int, mixed> $rows
     */
    public static function create(array $rows = []): self
    {
        return new self($rows);
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }
}

final class MigrationDiagnosticsFakePreparedStatement extends MigrationDiagnosticsFakePdoStatement
{
    private MigrationDiagnosticsFakePdo $pdo;
    private string $query;

    private function __construct(MigrationDiagnosticsFakePdo $pdo, string $query)
    {
        parent::__construct();
        $this->pdo = $pdo;
        $this->query = $query;
    }

    public static function createFromQuery(MigrationDiagnosticsFakePdo $pdo, string $query): self
    {
        return new self($pdo, $query);
    }

    public function execute(?array $params = null): bool
    {
        if (str_contains($this->query, 'INSERT INTO schema_migrations')) {
            $this->pdo->registerAppliedMigration($params ?? []);
        }

        return true;
    }
}
