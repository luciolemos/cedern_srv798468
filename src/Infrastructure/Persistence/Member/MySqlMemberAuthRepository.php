<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Member;

use App\Domain\Member\MemberAuthRepository;

class MySqlMemberAuthRepository implements MemberAuthRepository
{
    private const DEFAULT_MANAGEMENT_NAME = 'Gestão Atual';

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createPendingUser(array $data): int
    {
        $params = [
            'full_name' => $this->nullableText($data['full_name'] ?? null),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'password_hash' => (string) ($data['password_hash'] ?? ''),
        ];

        try {
            $sql = <<<SQL
                INSERT INTO member_users (
                    full_name,
                    email,
                    password_hash,
                    status,
                    profile_completed
                ) VALUES (
                    :full_name,
                    :email,
                    :password_hash,
                    'pending',
                    0
                )
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                $sql = <<<SQL
                    INSERT INTO member_users (
                        full_name,
                        email,
                        password_hash,
                        status,
                        profile_completed
                    ) VALUES (
                        :full_name,
                        :email,
                        :password_hash,
                        'pending',
                        0
                    )
                SQL;

                $statement = $this->pdo->prepare($sql);
                $statement->execute($params);
            } catch (\Throwable $innerException) {
                $sql = <<<SQL
                    INSERT INTO member_users (
                        full_name,
                        email,
                        password_hash
                    ) VALUES (
                        :full_name,
                        :email,
                        :password_hash
                    )
                SQL;

                $statement = $this->pdo->prepare($sql);
                $statement->execute($params);
            }
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $normalizedEmail = strtolower(trim($email));

        try {
            $sql = <<<SQL
                SELECT
                    u.id,
                    u.full_name,
                    u.email,
                    u.password_hash,
                    u.status,
                    u.phone_mobile,
                    u.phone_landline,
                    u.birth_date,
                    u.birth_place,
                    COALESCE(mmr.role_name, u.institutional_role) AS institutional_role,
                    u.member_type,
                    u.profile_photo_path,
                    u.privacy_notice_version,
                    u.privacy_notice_accepted_at,
                    u.profile_completed,
                    u.role_id,
                    r.role_key,
                    r.name AS role_name
                FROM member_users u
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN member_management_roles mmr
                    ON mmr.member_user_id = u.id
                   AND mmr.ends_at IS NULL
                   AND mmr.management_id = (
                        SELECT m.id
                        FROM institutional_managements m
                        WHERE m.is_current = 1
                        ORDER BY m.id DESC
                        LIMIT 1
                   )
                WHERE u.email = :email
                LIMIT 1
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->execute(['email' => $normalizedEmail]);
            $row = $statement->fetch();

            return $row ?: null;
        } catch (\Throwable $exception) {
            try {
                $sql = <<<SQL
                    SELECT
                        u.id,
                        u.full_name,
                        u.email,
                        u.password_hash,
                        u.status,
                        u.phone_mobile,
                        u.phone_landline,
                        u.birth_date,
                        u.birth_place,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        u.profile_photo_path,
                        NULL AS privacy_notice_version,
                        NULL AS privacy_notice_accepted_at,
                        u.profile_completed,
                        u.role_id,
                        r.role_key,
                        r.name AS role_name
                    FROM member_users u
                    LEFT JOIN roles r ON r.id = u.role_id
                    WHERE u.email = :email
                    LIMIT 1
                SQL;

                $statement = $this->pdo->prepare($sql);
                $statement->execute(['email' => $normalizedEmail]);
                $row = $statement->fetch();
            } catch (\Throwable $innerException) {
                $sql = <<<SQL
                    SELECT
                        u.id,
                        u.full_name,
                        u.email,
                        u.password_hash,
                        u.status,
                        NULL AS phone_mobile,
                        NULL AS phone_landline,
                        NULL AS birth_date,
                        NULL AS birth_place,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS profile_photo_path,
                        NULL AS privacy_notice_version,
                        NULL AS privacy_notice_accepted_at,
                        0 AS profile_completed,
                        u.role_id,
                        NULL AS role_key,
                        NULL AS role_name
                    FROM member_users u
                    WHERE u.email = :email
                    LIMIT 1
                SQL;

                $statement = $this->pdo->prepare($sql);
                $statement->execute(['email' => $normalizedEmail]);
                $row = $statement->fetch();
            }

            if (!$row) {
                return null;
            }

            return $this->normalizeMemberRowWithDefaults($row);
        }
    }

    public function findById(int $id): ?array
    {
        try {
            $sql = <<<SQL
                SELECT
                    u.id,
                    u.full_name,
                    u.email,
                    u.password_hash,
                    u.status,
                    u.phone_mobile,
                    u.phone_landline,
                    u.birth_date,
                    u.birth_place,
                    COALESCE(mmr.role_name, u.institutional_role) AS institutional_role,
                    u.member_type,
                    u.profile_photo_path,
                    u.privacy_notice_version,
                    u.privacy_notice_accepted_at,
                    u.profile_completed,
                    u.role_id,
                    r.role_key,
                    r.name AS role_name
                FROM member_users u
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN member_management_roles mmr
                    ON mmr.member_user_id = u.id
                   AND mmr.ends_at IS NULL
                   AND mmr.management_id = (
                        SELECT m.id
                        FROM institutional_managements m
                        WHERE m.is_current = 1
                        ORDER BY m.id DESC
                        LIMIT 1
                   )
                WHERE u.id = :id
                LIMIT 1
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();

            return $row ?: null;
        } catch (\Throwable $exception) {
            try {
                $sql = <<<SQL
                    SELECT
                        u.id,
                        u.full_name,
                        u.email,
                        u.password_hash,
                        u.status,
                        u.phone_mobile,
                        u.phone_landline,
                        u.birth_date,
                        u.birth_place,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        u.profile_photo_path,
                        NULL AS privacy_notice_version,
                        NULL AS privacy_notice_accepted_at,
                        u.profile_completed,
                        u.role_id,
                        r.role_key,
                        r.name AS role_name
                    FROM member_users u
                    LEFT JOIN roles r ON r.id = u.role_id
                    WHERE u.id = :id
                    LIMIT 1
                SQL;

                $statement = $this->pdo->prepare($sql);
                $statement->execute(['id' => $id]);
                $row = $statement->fetch();
            } catch (\Throwable $innerException) {
                $sql = <<<SQL
                    SELECT
                        u.id,
                        u.full_name,
                        u.email,
                        u.password_hash,
                        u.status,
                        NULL AS phone_mobile,
                        NULL AS phone_landline,
                        NULL AS birth_date,
                        NULL AS birth_place,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS profile_photo_path,
                        NULL AS privacy_notice_version,
                        NULL AS privacy_notice_accepted_at,
                        0 AS profile_completed,
                        u.role_id,
                        NULL AS role_key,
                        NULL AS role_name
                    FROM member_users u
                    WHERE u.id = :id
                    LIMIT 1
                SQL;

                $statement = $this->pdo->prepare($sql);
                $statement->execute(['id' => $id]);
                $row = $statement->fetch();
            }

            if (!$row) {
                return null;
            }

            return $this->normalizeMemberRowWithDefaults($row);
        }
    }

    public function findAllRoles(): array
    {
        try {
            $statement = $this->pdo->query('SELECT id, role_key, name, description FROM roles ORDER BY id ASC');

            return $statement->fetchAll() ?: [];
        } catch (\Throwable $exception) {
            return [
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
            ];
        }
    }

    public function findRoleByKey(string $roleKey): ?array
    {
        try {
            $statement = $this->pdo->prepare('SELECT id, role_key, name FROM roles WHERE role_key = :role_key LIMIT 1');
            $statement->execute(['role_key' => $roleKey]);
            $row = $statement->fetch();

            return $row ?: null;
        } catch (\Throwable $exception) {
            foreach ($this->findAllRoles() as $role) {
                if ((string) ($role['role_key'] ?? '') === $roleKey) {
                    return $role;
                }
            }

            return null;
        }
    }

    public function updateProfile(int $id, array $data): bool
    {
        $sql = <<<SQL
            UPDATE member_users
            SET
                full_name = :full_name,
                phone_mobile = :phone_mobile,
                phone_landline = :phone_landline,
                birth_date = :birth_date,
                birth_place = :birth_place,
                profile_photo_path = :profile_photo_path,
                privacy_notice_version = :privacy_notice_version,
                privacy_notice_accepted_at = :privacy_notice_accepted_at,
                profile_completed = :profile_completed
            WHERE id = :id
            LIMIT 1
        SQL;

        $params = [
            'id' => $id,
            'full_name' => $this->nullableText($data['full_name'] ?? null),
            'phone_mobile' => $this->nullableText($data['phone_mobile'] ?? null),
            'phone_landline' => $this->nullableText($data['phone_landline'] ?? null),
            'birth_date' => $this->nullableText($data['birth_date'] ?? null),
            'birth_place' => $this->nullableText($data['birth_place'] ?? null),
            'profile_photo_path' => $this->nullableText($data['profile_photo_path'] ?? null),
            'privacy_notice_version' => $this->nullableText($data['privacy_notice_version'] ?? null),
            'privacy_notice_accepted_at' => $this->nullableText($data['privacy_notice_accepted_at'] ?? null),
            'profile_completed' => (int) ($data['profile_completed'] ?? 0),
        ];

        try {
            $statement = $this->pdo->prepare($sql);

            return $statement->execute($params);
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                $statement = $this->pdo->prepare($sql);

                return $statement->execute($params);
            } catch (\Throwable $innerException) {
                $fallbackSql = <<<SQL
                    UPDATE member_users
                    SET
                        full_name = :full_name,
                        phone_mobile = :phone_mobile,
                        phone_landline = :phone_landline,
                        profile_completed = :profile_completed
                    WHERE id = :id
                    LIMIT 1
                SQL;

                $fallbackStatement = $this->pdo->prepare($fallbackSql);

                return $fallbackStatement->execute([
                    'id' => $id,
                    'full_name' => $params['full_name'],
                    'phone_mobile' => $params['phone_mobile'],
                    'phone_landline' => $params['phone_landline'],
                    'profile_completed' => $params['profile_completed'],
                ]);
            }
        }
    }

    public function approveAndAssignRole(
        int $id,
        int $roleId,
        ?string $institutionalRole = null,
        ?string $memberType = null
    ): bool {
        $normalizedInstitutionalRole = $this->nullableText($institutionalRole);
        $normalizedMemberType = $this->nullableText($memberType);

        $sql = <<<SQL
            UPDATE member_users
            SET
                role_id = :role_id,
                institutional_role = :institutional_role,
                member_type = :member_type,
                status = 'active',
                approved_at = NOW()
            WHERE id = :id
            LIMIT 1
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);

            return $statement->execute([
                'id' => $id,
                'role_id' => $roleId,
                'institutional_role' => $normalizedInstitutionalRole,
                'member_type' => $normalizedMemberType,
            ]) && $this->syncInstitutionalRoleForCurrentManagement($id, $normalizedInstitutionalRole);
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                $statement = $this->pdo->prepare($sql);

                return $statement->execute([
                    'id' => $id,
                    'role_id' => $roleId,
                    'institutional_role' => $normalizedInstitutionalRole,
                    'member_type' => $normalizedMemberType,
                ]) && $this->syncInstitutionalRoleForCurrentManagement($id, $normalizedInstitutionalRole);
            } catch (\Throwable $innerException) {
                $fallbackSql = <<<SQL
                    UPDATE member_users
                    SET
                        role_id = :role_id,
                        status = 'active',
                        approved_at = NOW()
                    WHERE id = :id
                    LIMIT 1
                SQL;

                $fallbackStatement = $this->pdo->prepare($fallbackSql);

                return $fallbackStatement->execute([
                    'id' => $id,
                    'role_id' => $roleId,
                ]) && $this->syncInstitutionalRoleForCurrentManagement($id, $normalizedInstitutionalRole);
            }
        }
    }

    public function hasActiveInstitutionalRole(string $institutionalRole, int $exceptUserId = 0): bool
    {
        $normalizedRole = trim($institutionalRole);

        if ($normalizedRole === '') {
            return false;
        }

        try {
                        $currentManagementId = $this->ensureCurrentManagementId();

            $sql = <<<SQL
                SELECT COUNT(*)
                                FROM member_management_roles mmr
                                INNER JOIN member_users u ON u.id = mmr.member_user_id
                                WHERE mmr.management_id = :management_id
                                    AND mmr.role_name = :institutional_role
                                    AND mmr.ends_at IS NULL
                                    AND u.status = 'active'
                                    AND (:except_user_id_check <= 0 OR u.id <> :except_user_id_filter)
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                                'management_id' => $currentManagementId,
                'institutional_role' => $normalizedRole,
                'except_user_id_check' => $exceptUserId,
                'except_user_id_filter' => $exceptUserId,
            ]);

            return (int) ($statement->fetchColumn() ?: 0) > 0;
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                $currentManagementId = $this->ensureCurrentManagementId();

                $sql = <<<SQL
                    SELECT COUNT(*)
                    FROM member_management_roles mmr
                    INNER JOIN member_users u ON u.id = mmr.member_user_id
                    WHERE mmr.management_id = :management_id
                      AND mmr.role_name = :institutional_role
                      AND mmr.ends_at IS NULL
                      AND u.status = 'active'
                      AND (:except_user_id_check <= 0 OR u.id <> :except_user_id_filter)
                SQL;

                $statement = $this->pdo->prepare($sql);
                $statement->execute([
                    'management_id' => $currentManagementId,
                    'institutional_role' => $normalizedRole,
                    'except_user_id_check' => $exceptUserId,
                    'except_user_id_filter' => $exceptUserId,
                ]);

                return (int) ($statement->fetchColumn() ?: 0) > 0;
            } catch (\Throwable $innerException) {
                $fallbackSql = <<<SQL
                    SELECT COUNT(*)
                    FROM member_users u
                    WHERE u.status = 'active'
                      AND u.institutional_role = :institutional_role
                      AND (:except_user_id_check <= 0 OR u.id <> :except_user_id_filter)
                SQL;

                $fallbackStatement = $this->pdo->prepare($fallbackSql);
                $fallbackStatement->execute([
                    'institutional_role' => $normalizedRole,
                    'except_user_id_check' => $exceptUserId,
                    'except_user_id_filter' => $exceptUserId,
                ]);

                return (int) ($fallbackStatement->fetchColumn() ?: 0) > 0;
            }
        }
    }

    public function findAllUsersForAdmin(): array
    {
        try {
            $sql = <<<SQL
                SELECT
                    u.id,
                    u.full_name,
                    u.email,
                    u.status,
                    u.phone_mobile,
                    u.phone_landline,
                    u.birth_date,
                    u.birth_place,
                    COALESCE(mmr.role_name, u.institutional_role) AS institutional_role,
                    u.member_type,
                    u.profile_photo_path,
                    u.profile_completed,
                    u.created_at,
                    u.updated_at,
                    u.role_id,
                    r.role_key,
                    r.name AS role_name
                FROM member_users u
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN member_management_roles mmr
                    ON mmr.member_user_id = u.id
                   AND mmr.ends_at IS NULL
                   AND mmr.management_id = (
                        SELECT m.id
                        FROM institutional_managements m
                        WHERE m.is_current = 1
                        ORDER BY m.id DESC
                        LIMIT 1
                   )
                ORDER BY u.created_at DESC
            SQL;

            $statement = $this->pdo->query($sql);

            return $statement->fetchAll() ?: [];
        } catch (\Throwable $exception) {
            try {
                $sql = <<<SQL
                    SELECT
                        u.id,
                        u.full_name,
                        u.email,
                        u.status,
                        u.phone_mobile,
                        u.phone_landline,
                        u.birth_date,
                        u.birth_place,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        u.profile_photo_path,
                        u.profile_completed,
                        u.created_at,
                        u.updated_at,
                        u.role_id,
                        r.role_key,
                        r.name AS role_name
                    FROM member_users u
                    LEFT JOIN roles r ON r.id = u.role_id
                    ORDER BY u.id DESC
                SQL;

                $statement = $this->pdo->query($sql);
                $rows = $statement->fetchAll() ?: [];
            } catch (\Throwable $innerException) {
                $sql = <<<SQL
                    SELECT
                        u.id,
                        u.full_name,
                        u.email,
                        u.status,
                        NULL AS phone_mobile,
                        NULL AS phone_landline,
                        NULL AS birth_date,
                        NULL AS birth_place,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS profile_photo_path,
                        0 AS profile_completed,
                        NULL AS created_at,
                        NULL AS updated_at,
                        u.role_id,
                        NULL AS role_key,
                        NULL AS role_name
                    FROM member_users u
                    ORDER BY u.id DESC
                SQL;

                $statement = $this->pdo->query($sql);
                $rows = $statement->fetchAll() ?: [];
            }

            return array_map(fn (array $row): array => $this->normalizeMemberRowWithDefaults($row), $rows);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeMemberRowWithDefaults(array $row): array
    {
        $roleId = (int) ($row['role_id'] ?? 0);
        $roleKeyById = [
            1 => 'member',
            2 => 'operator',
            3 => 'manager',
            4 => 'admin',
        ];
        $roleNameById = [
            1 => 'Membro',
            2 => 'Operador',
            3 => 'Gerente',
            4 => 'Administrador',
        ];

        $fallbackRoleKey = (string) ($roleKeyById[$roleId] ?? 'member');
        $fallbackRoleName = (string) ($roleNameById[$roleId] ?? 'Membro');

        $roleKey = trim((string) ($row['role_key'] ?? ''));
        $roleName = trim((string) ($row['role_name'] ?? ''));

        $row['role_key'] = $roleKey !== '' ? $roleKey : $fallbackRoleKey;
        $row['role_name'] = $roleName !== '' ? $roleName : $fallbackRoleName;
        $row['phone_mobile'] = $row['phone_mobile'] ?? null;
        $row['phone_landline'] = $row['phone_landline'] ?? null;
        $row['birth_date'] = $row['birth_date'] ?? null;
        $row['birth_place'] = $row['birth_place'] ?? null;
        $row['institutional_role'] = $row['institutional_role'] ?? null;
        $row['member_type'] = $row['member_type'] ?? null;
        $row['member_type_label'] = $this->resolveMemberTypeLabel((string) ($row['member_type'] ?? ''));
        $row['profile_photo_path'] = $row['profile_photo_path'] ?? null;
        $row['privacy_notice_version'] = $row['privacy_notice_version'] ?? null;
        $row['privacy_notice_accepted_at'] = $row['privacy_notice_accepted_at'] ?? null;
        $row['profile_completed'] = (int) ($row['profile_completed'] ?? 0);

        return $row;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function resolveMemberTypeLabel(string $memberType): string
    {
        return match (strtolower(trim($memberType))) {
            'fundador' => 'Fundador',
            'efetivo' => 'Efetivo',
            default => 'Não definido',
        };
    }

    private function ensureMemberSchemaCompatibility(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_key VARCHAR(40) NOT NULL UNIQUE,
                name VARCHAR(80) NOT NULL,
                description VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS member_users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(160) NULL,
                email VARCHAR(180) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role_id BIGINT UNSIGNED NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                phone_mobile VARCHAR(30) NULL,
                phone_landline VARCHAR(30) NULL,
                birth_date DATE NULL,
                birth_place VARCHAR(140) NULL,
                institutional_role VARCHAR(120) NULL,
                member_type VARCHAR(20) NULL,
                profile_photo_path VARCHAR(255) NULL,
                privacy_notice_version VARCHAR(40) NULL,
                privacy_notice_accepted_at DATETIME NULL,
                profile_completed TINYINT(1) NOT NULL DEFAULT 0,
                approved_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS institutional_managements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                starts_at DATE NULL,
                ends_at DATE NULL,
                is_current TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS member_management_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                management_id BIGINT UNSIGNED NOT NULL,
                member_user_id BIGINT UNSIGNED NOT NULL,
                role_name VARCHAR(120) NOT NULL,
                starts_at DATE NULL,
                ends_at DATE NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_member_management_unique_member (management_id, member_user_id),
                KEY idx_member_management_role_name (management_id, role_name),
                CONSTRAINT fk_member_management_roles_management
                    FOREIGN KEY (management_id) REFERENCES institutional_managements(id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT fk_member_management_roles_member
                    FOREIGN KEY (member_user_id) REFERENCES member_users(id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->ensureColumn(
            'member_users',
            'full_name',
            'ALTER TABLE member_users ADD COLUMN full_name VARCHAR(160) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'email',
            'ALTER TABLE member_users ADD COLUMN email VARCHAR(180) NOT NULL'
        );
        $this->ensureColumn(
            'member_users',
            'password_hash',
            'ALTER TABLE member_users ADD COLUMN password_hash VARCHAR(255) NOT NULL'
        );
        $this->ensureColumn(
            'member_users',
            'status',
            "ALTER TABLE member_users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'"
        );
        $this->ensureColumn(
            'member_users',
            'profile_completed',
            'ALTER TABLE member_users ADD COLUMN profile_completed TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->ensureColumn(
            'member_users',
            'phone_mobile',
            'ALTER TABLE member_users ADD COLUMN phone_mobile VARCHAR(30) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'phone_landline',
            'ALTER TABLE member_users ADD COLUMN phone_landline VARCHAR(30) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'birth_date',
            'ALTER TABLE member_users ADD COLUMN birth_date DATE NULL'
        );
        $this->ensureColumn(
            'member_users',
            'birth_place',
            'ALTER TABLE member_users ADD COLUMN birth_place VARCHAR(140) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'institutional_role',
            'ALTER TABLE member_users ADD COLUMN institutional_role VARCHAR(120) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'member_type',
            'ALTER TABLE member_users ADD COLUMN member_type VARCHAR(20) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'profile_photo_path',
            'ALTER TABLE member_users ADD COLUMN profile_photo_path VARCHAR(255) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'privacy_notice_version',
            'ALTER TABLE member_users ADD COLUMN privacy_notice_version VARCHAR(40) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'privacy_notice_accepted_at',
            'ALTER TABLE member_users ADD COLUMN privacy_notice_accepted_at DATETIME NULL'
        );
        $this->ensureColumn(
            'member_users',
            'role_id',
            'ALTER TABLE member_users ADD COLUMN role_id BIGINT UNSIGNED NULL'
        );
        $this->ensureColumn(
            'member_users',
            'approved_at',
            'ALTER TABLE member_users ADD COLUMN approved_at DATETIME NULL'
        );
        $this->ensureColumn(
            'member_users',
            'created_at',
            'ALTER TABLE member_users ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );
        $this->ensureColumn(
            'member_users',
            'updated_at',
            'ALTER TABLE member_users ADD COLUMN updated_at TIMESTAMP NOT NULL '
            . 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );

        $this->ensureDefaultRoles();
        $currentManagementId = $this->ensureCurrentManagementId();
        $this->migrateLegacyInstitutionalRolesToCurrentManagement($currentManagementId);
    }

    private function ensureCurrentManagementId(): int
    {
        try {
            $statement = $this->pdo->query(
                "SELECT id FROM institutional_managements WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
            );
            $currentId = (int) ($statement !== false ? $statement->fetchColumn() : 0);

            if ($currentId > 0) {
                return $currentId;
            }

            $insertStatement = $this->pdo->prepare(
                'INSERT INTO institutional_managements (name, starts_at, is_current) VALUES (:name, :starts_at, 1)'
            );
            $insertStatement->execute([
                'name' => self::DEFAULT_MANAGEMENT_NAME,
                'starts_at' => date('Y-m-d'),
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function syncInstitutionalRoleForCurrentManagement(int $userId, ?string $institutionalRole): bool
    {
        $managementId = $this->ensureCurrentManagementId();

        if ($managementId <= 0 || $userId <= 0) {
            return true;
        }

        if ($institutionalRole === null) {
            $deleteStatement = $this->pdo->prepare(
                'DELETE FROM member_management_roles '
                . 'WHERE management_id = :management_id '
                . 'AND member_user_id = :member_user_id '
                . 'LIMIT 1'
            );

            return $deleteStatement->execute([
                'management_id' => $managementId,
                'member_user_id' => $userId,
            ]);
        }

        $sql = <<<SQL
            INSERT INTO member_management_roles (
                management_id,
                member_user_id,
                role_name,
                starts_at,
                ends_at
            ) VALUES (
                :management_id,
                :member_user_id,
                :role_name,
                :starts_at,
                NULL
            )
            ON DUPLICATE KEY UPDATE
                role_name = VALUES(role_name),
                ends_at = NULL
        SQL;

        $statement = $this->pdo->prepare($sql);

        return $statement->execute([
            'management_id' => $managementId,
            'member_user_id' => $userId,
            'role_name' => $institutionalRole,
            'starts_at' => date('Y-m-d'),
        ]);
    }

    private function migrateLegacyInstitutionalRolesToCurrentManagement(int $managementId): void
    {
        if ($managementId <= 0) {
            return;
        }

        $sql = <<<SQL
            INSERT INTO member_management_roles (
                management_id,
                member_user_id,
                role_name,
                starts_at,
                ends_at
            )
            SELECT
                :management_id,
                u.id,
                u.institutional_role,
                COALESCE(DATE(u.approved_at), DATE(u.created_at), CURRENT_DATE),
                NULL
            FROM member_users u
            LEFT JOIN member_management_roles mmr
                ON mmr.management_id = :management_id
               AND mmr.member_user_id = u.id
               AND mmr.ends_at IS NULL
            WHERE u.institutional_role IS NOT NULL
              AND TRIM(u.institutional_role) <> ''
              AND mmr.id IS NULL
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'management_id' => $managementId,
        ]);
    }

    private function ensureColumn(string $tableName, string $columnName, string $alterSql): void
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name '
            . 'AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        $exists = (int) $statement->fetchColumn() > 0;

        if (!$exists) {
            $this->pdo->exec($alterSql);
        }
    }

    private function ensureDefaultRoles(): void
    {
        $this->pdo->exec(<<<SQL
            INSERT INTO roles (role_key, name, description)
            VALUES
                ('member', 'Membro', 'Acesso à área de membro e recursos básicos.'),
                ('operator', 'Operador', 'Operação de funcionalidades internas específicas.'),
                ('manager', 'Gerente', 'Coordenação de conteúdos e fluxos internos.'),
                ('admin', 'Administrador', 'Gestão completa de usuários e permissões.')
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description)
        SQL);
    }
}
