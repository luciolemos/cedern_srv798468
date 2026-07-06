<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\SqlPatchMigrator;
use App\Infrastructure\Database\SqlStatementSplitter;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SqlPatchMigratorTest extends TestCase
{
    private string $patchDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patchDirectory = sys_get_temp_dir() . '/cedern-patches-' . bin2hex(random_bytes(6));
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

    public function testGetStatusSeparatesAppliedPendingAndOrphanedPatches(): void
    {
        $firstPath = $this->createPatch(
            '2026-07-06-001-create-courses.sql',
            'CREATE TABLE courses (id INT PRIMARY KEY);'
        );
        $this->createPatch(
            '2026-07-06-002-create-enrollments.sql',
            'CREATE TABLE course_enrollments (id INT PRIMARY KEY);'
        );

        $firstSql = file_get_contents($firstPath);
        self::assertNotFalse($firstSql);

        $pdo = new MigrationFakePdo([
            [
                'migration_key' => '2026-07-06-001-create-courses',
                'checksum_sha256' => hash('sha256', $firstSql),
                'applied_at' => '2026-07-06 10:00:00',
            ],
            [
                'migration_key' => '2026-07-01-999-orphaned',
                'checksum_sha256' => str_repeat('a', 64),
                'applied_at' => '2026-07-01 08:00:00',
            ],
        ]);

        $migrator = new SqlPatchMigrator($pdo, $this->patchDirectory, new SqlStatementSplitter());
        $status = $migrator->getStatus();

        $this->assertCount(2, $status['available']);
        $this->assertCount(1, $status['applied']);
        $this->assertCount(1, $status['pending']);
        $this->assertCount(1, $status['orphaned']);
        $this->assertSame('2026-07-06-002-create-enrollments', $status['pending'][0]['key']);
        $this->assertSame('2026-07-01-999-orphaned', $status['orphaned'][0]['key']);
    }

    public function testApplyPendingExecutesEachPatchAndRegistersIt(): void
    {
        $this->createPatch(
            '2026-07-06-001-create-courses.sql',
            <<<SQL
                CREATE TABLE courses (id INT PRIMARY KEY);
                CREATE INDEX idx_courses_id ON courses (id);
            SQL
        );
        $this->createPatch(
            '2026-07-06-002-create-enrollments.sql',
            'CREATE TABLE course_enrollments (id INT PRIMARY KEY);'
        );

        $pdo = new MigrationFakePdo();
        $migrator = new SqlPatchMigrator($pdo, $this->patchDirectory, new SqlStatementSplitter());

        $summary = $migrator->applyPending();

        $this->assertSame(2, $summary['applied_count']);
        $this->assertCount(3, $pdo->executedStatements);
        $this->assertSame(2, $pdo->beginTransactionCount);
        $this->assertSame(2, $pdo->commitCount);
        $this->assertSame(0, $pdo->rollBackCount);
        $this->assertCount(2, $pdo->appliedRows);
    }

    public function testApplyPendingFailsWhenAppliedChecksumDiffersFromFile(): void
    {
        $this->createPatch(
            '2026-07-06-001-create-courses.sql',
            'CREATE TABLE courses (id INT PRIMARY KEY);'
        );

        $pdo = new MigrationFakePdo([
            [
                'migration_key' => '2026-07-06-001-create-courses',
                'checksum_sha256' => str_repeat('b', 64),
                'applied_at' => '2026-07-06 10:00:00',
            ],
        ]);

        $migrator = new SqlPatchMigrator($pdo, $this->patchDirectory, new SqlStatementSplitter());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum diferente');

        $migrator->applyPending();
    }

    private function createPatch(string $filename, string $content): string
    {
        $path = $this->patchDirectory . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }
}

final class MigrationFakePdo extends PDO
{
    /** @var array<int, array<string, string>> */
    public array $appliedRows;

    /** @var array<int, string> */
    public array $executedStatements = [];

    public int $beginTransactionCount = 0;
    public int $commitCount = 0;
    public int $rollBackCount = 0;

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
        if (str_contains($statement, 'CREATE TABLE IF NOT EXISTS schema_migrations')) {
            return 0;
        }

        $this->executedStatements[] = trim($statement);

        return 0;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_contains($query, 'FROM schema_migrations')) {
            return MigrationFakePdoStatement::create($this->appliedRows);
        }

        return MigrationFakePdoStatement::create([]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return MigrationFakePreparedStatement::createFromQuery($this, $query);
    }

    public function beginTransaction(): bool
    {
        $this->transactionOpen = true;
        $this->beginTransactionCount++;

        return true;
    }

    public function commit(): bool
    {
        $this->transactionOpen = false;
        $this->commitCount++;

        return true;
    }

    public function rollBack(): bool
    {
        $this->transactionOpen = false;
        $this->rollBackCount++;

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
            'applied_at' => '2026-07-06 12:00:00',
        ];
    }
}

class MigrationFakePdoStatement extends PDOStatement
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

final class MigrationFakePreparedStatement extends MigrationFakePdoStatement
{
    private MigrationFakePdo $pdo;
    private string $query;

    private function __construct(MigrationFakePdo $pdo, string $query)
    {
        parent::__construct();
        $this->pdo = $pdo;
        $this->query = $query;
    }

    public static function createFromQuery(MigrationFakePdo $pdo, string $query): self
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
