<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Member;

use App\Infrastructure\Persistence\Member\MySqlMemberAuthRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class MySqlMemberAuthRepositoryTest extends TestCase
{
    public function testFindAllRolesBootstrapsMissingFinanceOperatorRole(): void
    {
        $pdo = new MemberRepositoryFakePdo([
            [
                'id' => 1,
                'role_key' => 'member',
                'name' => 'Membro',
                'description' => 'Acesso à área de membro e recursos básicos.',
            ],
            [
                'id' => 2,
                'role_key' => 'operator',
                'name' => 'Operador',
                'description' => 'Operação de funcionalidades internas específicas.',
            ],
            [
                'id' => 3,
                'role_key' => 'manager',
                'name' => 'Gerente',
                'description' => 'Coordenação de conteúdos e fluxos internos.',
            ],
            [
                'id' => 4,
                'role_key' => 'admin',
                'name' => 'Administrador',
                'description' => 'Gestão completa de usuários e permissões.',
            ],
            [
                'id' => 5,
                'role_key' => 'bookshop_operator',
                'name' => 'Operador da Livraria',
                'description' => 'Acesso exclusivo ao módulo interno da Livraria.',
            ],
        ]);

        $repository = new MySqlMemberAuthRepository($pdo);
        $roles = $repository->findAllRoles();

        $this->assertContains('finance_operator', array_column($roles, 'role_key'));
        $this->assertSame('Operador Financeiro', $pdo->findRoleName('finance_operator'));
    }
}

final class MemberRepositoryFakePdo extends PDO
{
    /** @var array<int, array<string, mixed>> */
    private array $roles;

    /**
     * @param array<int, array<string, mixed>> $roles
     */
    public function __construct(array $roles)
    {
        $this->roles = array_values($roles);
    }

    public function exec(string $statement): int|false
    {
        if (str_starts_with(trim($statement), 'INSERT INTO roles')) {
            $this->mergeDefaultRoles();
        }

        return 1;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query)) ?? trim($query);

        if ($normalized === 'SELECT id, role_key, name, description FROM roles ORDER BY id ASC') {
            return MemberRepositoryFakePdoStatement::create($this->roles);
        }

        if (str_contains($normalized, 'SELECT id FROM institutional_managements WHERE is_current = 1')) {
            return MemberRepositoryFakePdoStatement::create([
                [0 => 1, 'id' => 1],
            ]);
        }

        return MemberRepositoryFakePdoStatement::create([]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return MemberRepositoryFakePreparedStatement::createFromQuery($this, $query);
    }

    public function findRoleByKeyValue(string $roleKey): ?array
    {
        foreach ($this->roles as $role) {
            if ((string) ($role['role_key'] ?? '') === $roleKey) {
                return $role;
            }
        }

        return null;
    }

    public function findRoleName(string $roleKey): ?string
    {
        $role = $this->findRoleByKeyValue($roleKey);

        return $role !== null ? (string) ($role['name'] ?? '') : null;
    }

    private function mergeDefaultRoles(): void
    {
        $defaults = [
            [
                'id' => 1,
                'role_key' => 'member',
                'name' => 'Membro',
                'description' => 'Acesso à área de membro e recursos básicos.',
            ],
            [
                'id' => 2,
                'role_key' => 'operator',
                'name' => 'Operador',
                'description' => 'Operação de funcionalidades internas específicas.',
            ],
            [
                'id' => 3,
                'role_key' => 'manager',
                'name' => 'Gerente',
                'description' => 'Coordenação de conteúdos e fluxos internos.',
            ],
            [
                'id' => 4,
                'role_key' => 'admin',
                'name' => 'Administrador',
                'description' => 'Gestão completa de usuários e permissões.',
            ],
            [
                'id' => 5,
                'role_key' => 'bookshop_operator',
                'name' => 'Operador da Livraria',
                'description' => 'Acesso exclusivo ao módulo interno da Livraria.',
            ],
            [
                'id' => 6,
                'role_key' => 'finance_operator',
                'name' => 'Operador Financeiro',
                'description' => 'Acesso exclusivo ao acompanhamento financeiro de vendas e cancelamentos.',
            ],
        ];

        $byKey = [];
        foreach ($this->roles as $role) {
            $byKey[(string) ($role['role_key'] ?? '')] = $role;
        }

        foreach ($defaults as $role) {
            $byKey[(string) $role['role_key']] = $role;
        }

        usort($byKey, static fn (array $firstRole, array $secondRole): int => ((int) $firstRole['id']) <=> ((int) $secondRole['id']));
        $this->roles = array_values($byKey);
    }
}

class MemberRepositoryFakePdoStatement extends PDOStatement
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

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        if ($this->rows === []) {
            return false;
        }

        return array_shift($this->rows);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[0] ?? null;
        if ($row === null) {
            return false;
        }

        if (!is_array($row)) {
            return $row;
        }

        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        $values = array_values($row);

        return $values[$column] ?? false;
    }

    /**
     * @param array<int, mixed> $rows
     */
    protected function setRows(array $rows): void
    {
        $this->rows = array_values($rows);
    }
}

final class MemberRepositoryFakePreparedStatement extends MemberRepositoryFakePdoStatement
{
    private MemberRepositoryFakePdo $pdo;
    private string $query;

    public function __construct(MemberRepositoryFakePdo $pdo, string $query)
    {
        parent::__construct([]);
        $this->pdo = $pdo;
        $this->query = $query;
    }

    public static function createFromQuery(MemberRepositoryFakePdo $pdo, string $query): self
    {
        return new self($pdo, $query);
    }

    public function execute(?array $params = null): bool
    {
        $normalized = preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query);

        if (str_contains($normalized, 'FROM INFORMATION_SCHEMA.COLUMNS')) {
            $this->setRows([[0 => 1]]);

            return true;
        }

        if (str_contains($normalized, 'FROM roles WHERE role_key = :role_key')) {
            $role = $this->pdo->findRoleByKeyValue((string) ($params['role_key'] ?? ''));
            $this->setRows($role !== null ? [$role] : []);

            return true;
        }

        $this->setRows([]);

        return true;
    }
}
