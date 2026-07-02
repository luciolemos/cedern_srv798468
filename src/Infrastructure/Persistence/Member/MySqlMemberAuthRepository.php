<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Member;

use App\Domain\Member\MemberAuthRepository;

class MySqlMemberAuthRepository implements MemberAuthRepository
{
    private const DEFAULT_MANAGEMENT_NAME = 'Gestão Atual';

    private \PDO $pdo;
    private bool $memberSchemaCompatibilityBooted = false;

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
                    association_status,
                    is_contributor,
                    profile_completed
                ) VALUES (
                    :full_name,
                    :email,
                    :password_hash,
                    'pending',
                    'applicant',
                    0,
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
                        association_status,
                        is_contributor,
                        profile_completed
                    ) VALUES (
                        :full_name,
                        :email,
                        :password_hash,
                        'pending',
                        'applicant',
                        0,
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

        $userId = (int) $this->pdo->lastInsertId();

        if ($userId > 0) {
            try {
                $this->bootMemberSchemaCompatibility();
                $this->appendUserAdministrationEvent(
                    $userId,
                    'signup_created',
                    'Cadastro criado como solicitante com acesso pendente.',
                    null,
                    [
                        'previous' => null,
                        'current' => $this->buildAdministrativeSnapshot([
                            'role_id' => null,
                            'role_key' => null,
                            'role_name' => null,
                            'institutional_role' => null,
                            'member_type' => null,
                            'association_status' => 'applicant',
                            'is_contributor' => 0,
                            'status' => 'pending',
                        ]),
                        'rules_applied' => ['new_signup_defaults'],
                    ]
                );
            } catch (\Throwable $exception) {
                $this->loggerSafeWarning('Falha ao registrar histórico administrativo do novo cadastro.', $exception, [
                    'member_user_id' => $userId,
                ]);
            }
        }

        return $userId;
    }

    public function createPasswordResetToken(
        int $userId,
        string $email,
        string $tokenHash,
        \DateTimeImmutable $expiresAt
    ): bool {
        try {
            return $this->createPasswordResetTokenInternal($userId, $email, $tokenHash, $expiresAt);
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                return $this->createPasswordResetTokenInternal($userId, $email, $tokenHash, $expiresAt);
            } catch (\Throwable $innerException) {
                return false;
            }
        }
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
                    u.cpf,
                    u.postal_code,
                    u.street_address,
                    u.address_number,
                    u.address_complement,
                    u.neighborhood,
                    u.address_city,
                    u.address_state,
                    u.preferred_due_day,
                    u.contribution_amount,
                    u.contribution_plan_label,
                    u.preferred_payment_method,
                    u.billing_email_opt_in,
                    u.billing_whatsapp_opt_in,
                    COALESCE(mmr.role_name, u.institutional_role) AS institutional_role,
                    u.member_type,
                    u.association_status,
                    u.is_contributor,
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

            return is_array($row) ? $this->normalizeMemberRowWithDefaults($row) : null;
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
                        NULL AS cpf,
                        NULL AS postal_code,
                        NULL AS street_address,
                        NULL AS address_number,
                        NULL AS address_complement,
                        NULL AS neighborhood,
                        NULL AS address_city,
                        NULL AS address_state,
                        NULL AS preferred_due_day,
                        NULL AS contribution_amount,
                        NULL AS contribution_plan_label,
                        NULL AS preferred_payment_method,
                        0 AS billing_email_opt_in,
                        0 AS billing_whatsapp_opt_in,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS association_status,
                        0 AS is_contributor,
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
                        NULL AS cpf,
                        NULL AS postal_code,
                        NULL AS street_address,
                        NULL AS address_number,
                        NULL AS address_complement,
                        NULL AS neighborhood,
                        NULL AS address_city,
                        NULL AS address_state,
                        NULL AS preferred_due_day,
                        NULL AS contribution_amount,
                        NULL AS contribution_plan_label,
                        NULL AS preferred_payment_method,
                        0 AS billing_email_opt_in,
                        0 AS billing_whatsapp_opt_in,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS association_status,
                        0 AS is_contributor,
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
                    u.cpf,
                    u.postal_code,
                    u.street_address,
                    u.address_number,
                    u.address_complement,
                    u.neighborhood,
                    u.address_city,
                    u.address_state,
                    u.preferred_due_day,
                    u.contribution_amount,
                    u.contribution_plan_label,
                    u.preferred_payment_method,
                    u.billing_email_opt_in,
                    u.billing_whatsapp_opt_in,
                    COALESCE(mmr.role_name, u.institutional_role) AS institutional_role,
                    u.member_type,
                    u.association_status,
                    u.is_contributor,
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

            return is_array($row) ? $this->normalizeMemberRowWithDefaults($row) : null;
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
                        NULL AS cpf,
                        NULL AS postal_code,
                        NULL AS street_address,
                        NULL AS address_number,
                        NULL AS address_complement,
                        NULL AS neighborhood,
                        NULL AS address_city,
                        NULL AS address_state,
                        NULL AS preferred_due_day,
                        NULL AS contribution_amount,
                        NULL AS contribution_plan_label,
                        NULL AS preferred_payment_method,
                        0 AS billing_email_opt_in,
                        0 AS billing_whatsapp_opt_in,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS association_status,
                        0 AS is_contributor,
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
                        NULL AS cpf,
                        NULL AS postal_code,
                        NULL AS street_address,
                        NULL AS address_number,
                        NULL AS address_complement,
                        NULL AS neighborhood,
                        NULL AS address_city,
                        NULL AS address_state,
                        NULL AS preferred_due_day,
                        NULL AS contribution_amount,
                        NULL AS contribution_plan_label,
                        NULL AS preferred_payment_method,
                        0 AS billing_email_opt_in,
                        0 AS billing_whatsapp_opt_in,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS association_status,
                        0 AS is_contributor,
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

    public function findActivePasswordResetByToken(string $tokenHash): ?array
    {
        try {
            return $this->findActivePasswordResetByTokenInternal($tokenHash);
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                return $this->findActivePasswordResetByTokenInternal($tokenHash);
            } catch (\Throwable $innerException) {
                return null;
            }
        }
    }

    public function findAllRoles(): array
    {
        $this->bootMemberSchemaCompatibility();

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
        }
    }

    public function findRoleByKey(string $roleKey): ?array
    {
        $this->bootMemberSchemaCompatibility();

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
                cpf = :cpf,
                postal_code = :postal_code,
                street_address = :street_address,
                address_number = :address_number,
                address_complement = :address_complement,
                neighborhood = :neighborhood,
                address_city = :address_city,
                address_state = :address_state,
                preferred_due_day = :preferred_due_day,
                contribution_amount = :contribution_amount,
                contribution_plan_label = :contribution_plan_label,
                preferred_payment_method = :preferred_payment_method,
                billing_email_opt_in = :billing_email_opt_in,
                billing_whatsapp_opt_in = :billing_whatsapp_opt_in,
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
            'cpf' => $this->nullableText($data['cpf'] ?? null),
            'postal_code' => $this->nullableText($data['postal_code'] ?? null),
            'street_address' => $this->nullableText($data['street_address'] ?? null),
            'address_number' => $this->nullableText($data['address_number'] ?? null),
            'address_complement' => $this->nullableText($data['address_complement'] ?? null),
            'neighborhood' => $this->nullableText($data['neighborhood'] ?? null),
            'address_city' => $this->nullableText($data['address_city'] ?? null),
            'address_state' => $this->nullableText($data['address_state'] ?? null),
            'preferred_due_day' => ($data['preferred_due_day'] ?? null) !== null
                ? (int) $data['preferred_due_day']
                : null,
            'contribution_amount' => ($data['contribution_amount'] ?? null) !== null
                ? (string) $data['contribution_amount']
                : null,
            'contribution_plan_label' => $this->nullableText($data['contribution_plan_label'] ?? null),
            'preferred_payment_method' => $this->nullableText($data['preferred_payment_method'] ?? null),
            'billing_email_opt_in' => (int) ($data['billing_email_opt_in'] ?? 0),
            'billing_whatsapp_opt_in' => (int) ($data['billing_whatsapp_opt_in'] ?? 0),
            'profile_photo_path' => $this->nullableText($data['profile_photo_path'] ?? null),
            'privacy_notice_version' => $this->nullableText($data['privacy_notice_version'] ?? null),
            'privacy_notice_accepted_at' => $this->nullableText($data['privacy_notice_accepted_at'] ?? null),
            'profile_completed' => (int) ($data['profile_completed'] ?? 0),
        ];

        try {
            $statement = $this->pdo->prepare($sql);
            $updated = $statement->execute($params);
            if ($updated) {
                $this->syncPendingContributionChargePaymentMethod(
                    $id,
                    $this->nullableText($data['preferred_payment_method'] ?? null)
                );
            }

            return $updated;
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                $statement = $this->pdo->prepare($sql);
                $updated = $statement->execute($params);
                if ($updated) {
                    $this->syncPendingContributionChargePaymentMethod(
                        $id,
                        $this->nullableText($data['preferred_payment_method'] ?? null)
                    );
                }

                return $updated;
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

    private function syncPendingContributionChargePaymentMethod(int $memberUserId, ?string $preferredPaymentMethod): void
    {
        $this->bootMemberSchemaCompatibility();

        $normalizedPaymentMethod = $this->resolveContributionPaymentMethod((string) $preferredPaymentMethod);

        try {
            $statement = $this->pdo->prepare(<<<SQL
                UPDATE member_contribution_charges
                SET
                    preferred_payment_method = :preferred_payment_method,
                    updated_at = CURRENT_TIMESTAMP
                WHERE member_user_id = :member_user_id
                  AND status = 'pending'
                  AND payment_recorded_method IS NULL
                  AND (gateway_payment_id IS NULL OR gateway_payment_id = '')
            SQL);

            $statement->execute([
                'member_user_id' => $memberUserId,
                'preferred_payment_method' => $normalizedPaymentMethod,
            ]);
        } catch (\Throwable $exception) {
            $this->loggerSafeWarning('Falha ao sincronizar forma de pagamento das cobranças pendentes.', $exception, [
                'member_user_id' => $memberUserId,
                'preferred_payment_method' => $normalizedPaymentMethod,
            ]);
        }
    }

    public function consumePasswordResetToken(int $resetId, int $userId, string $passwordHash): bool
    {
        try {
            return $this->consumePasswordResetTokenInternal($resetId, $userId, $passwordHash);
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                return $this->consumePasswordResetTokenInternal($resetId, $userId, $passwordHash);
            } catch (\Throwable $innerException) {
                return false;
            }
        }
    }

    public function approveAndAssignRole(
        int $id,
        int $roleId,
        ?string $institutionalRole = null,
        ?string $memberType = null,
        ?string $associationStatus = null,
        ?bool $isContributor = null,
        ?string $accountStatus = null,
        ?int $actedByUserId = null
    ): bool {
        [$normalizedState, $rulesApplied] = $this->normalizeAdministrativeState(
            $memberType,
            $institutionalRole,
            $associationStatus,
            $isContributor,
            $accountStatus
        );

        try {
            return $this->approveAndAssignRoleInternal($id, $roleId, $normalizedState, $rulesApplied, $actedByUserId);
        } catch (\Throwable $exception) {
            $this->ensureMemberSchemaCompatibility();

            try {
                return $this->approveAndAssignRoleInternal($id, $roleId, $normalizedState, $rulesApplied, $actedByUserId);
            } catch (\Throwable $innerException) {
                return $this->approveAndAssignRoleFallback($id, $roleId, $normalizedState, $innerException);
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
                    u.cpf,
                    u.postal_code,
                    u.street_address,
                    u.address_number,
                    u.address_complement,
                    u.neighborhood,
                    u.address_city,
                    u.address_state,
                    u.preferred_due_day,
                    u.contribution_amount,
                    u.contribution_plan_label,
                    u.preferred_payment_method,
                    u.billing_email_opt_in,
                    u.billing_whatsapp_opt_in,
                    COALESCE(mmr.role_name, u.institutional_role) AS institutional_role,
                    u.member_type,
                    u.association_status,
                    u.is_contributor,
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

            $rows = $statement->fetchAll() ?: [];

            return array_map(fn (array $row): array => $this->normalizeMemberRowWithDefaults($row), $rows);
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
                        NULL AS cpf,
                        NULL AS postal_code,
                        NULL AS street_address,
                        NULL AS address_number,
                        NULL AS address_complement,
                        NULL AS neighborhood,
                        NULL AS address_city,
                        NULL AS address_state,
                        NULL AS preferred_due_day,
                        NULL AS contribution_amount,
                        NULL AS contribution_plan_label,
                        NULL AS preferred_payment_method,
                        0 AS billing_email_opt_in,
                        0 AS billing_whatsapp_opt_in,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS association_status,
                        0 AS is_contributor,
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
                        NULL AS cpf,
                        NULL AS postal_code,
                        NULL AS street_address,
                        NULL AS address_number,
                        NULL AS address_complement,
                        NULL AS neighborhood,
                        NULL AS address_city,
                        NULL AS address_state,
                        NULL AS preferred_due_day,
                        NULL AS contribution_amount,
                        NULL AS contribution_plan_label,
                        NULL AS preferred_payment_method,
                        0 AS billing_email_opt_in,
                        0 AS billing_whatsapp_opt_in,
                        NULL AS institutional_role,
                        NULL AS member_type,
                        NULL AS association_status,
                        0 AS is_contributor,
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

    public function findUserAdministrationHistory(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $this->bootMemberSchemaCompatibility();

        try {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    e.id,
                    e.member_user_id,
                    e.acted_by_user_id,
                    e.event_type,
                    e.event_description,
                    e.payload_json,
                    e.created_at,
                    actor.full_name AS acted_by_user_full_name,
                    actor.email AS acted_by_user_email
                FROM member_user_administration_events e
                LEFT JOIN member_users actor ON actor.id = e.acted_by_user_id
                WHERE e.member_user_id = :member_user_id
                ORDER BY e.created_at DESC, e.id DESC
            SQL);
            $statement->execute([
                'member_user_id' => $userId,
            ]);

            $rows = $statement->fetchAll() ?: [];

            return array_map(fn (array $row): array => $this->normalizeUserAdministrationEvent($row), $rows);
        } catch (\Throwable $exception) {
            $this->loggerSafeWarning('Falha ao carregar histórico administrativo do usuário.', $exception, [
                'member_user_id' => $userId,
            ]);

            return [];
        }
    }

    public function findContributionMembersByCompetence(string $competence): array
    {
        $this->bootMemberSchemaCompatibility();

        $normalizedCompetence = $this->normalizeCompetence($competence);
        $today = date('Y-m-d');

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
                    u.cpf,
                    u.postal_code,
                    u.street_address,
                    u.address_number,
                    u.address_complement,
                    u.neighborhood,
                    u.address_city,
                    u.address_state,
                    u.preferred_due_day,
                    u.contribution_amount,
                    u.contribution_plan_label,
                    u.preferred_payment_method,
                    u.billing_email_opt_in,
                    u.billing_whatsapp_opt_in,
                    COALESCE(mmr.role_name, u.institutional_role) AS institutional_role,
                    u.member_type,
                    u.association_status,
                    u.is_contributor,
                    u.profile_photo_path,
                    u.privacy_notice_version,
                    u.privacy_notice_accepted_at,
                    u.profile_completed,
                    u.role_id,
                    r.role_key,
                    r.name AS role_name,
                    c.id AS charge_id,
                    c.competence AS charge_competence,
                    c.due_date AS charge_due_date,
                    c.amount_due AS charge_amount_due,
                    c.status AS charge_status,
                    c.preferred_payment_method AS charge_preferred_payment_method,
                    c.payment_recorded_method AS charge_payment_recorded_method,
                    c.paid_at AS charge_paid_at,
                    c.exemption_reason AS charge_exemption_reason,
                    c.gateway_provider AS charge_gateway_provider,
                    c.gateway_customer_id AS charge_gateway_customer_id,
                    c.gateway_payment_id AS charge_gateway_payment_id,
                    c.gateway_billing_type AS charge_gateway_billing_type,
                    c.gateway_status AS charge_gateway_status,
                    c.gateway_invoice_url AS charge_gateway_invoice_url,
                    c.gateway_bank_slip_url AS charge_gateway_bank_slip_url,
                    c.gateway_transaction_receipt_url AS charge_gateway_transaction_receipt_url,
                    c.gateway_pix_payload AS charge_gateway_pix_payload,
                    c.gateway_pix_encoded_image AS charge_gateway_pix_encoded_image,
                    c.gateway_pix_expiration_date AS charge_gateway_pix_expiration_date,
                    c.gateway_last_synced_at AS charge_gateway_last_synced_at,
                    COALESCE(stats.overdue_charge_count, 0) AS overdue_charge_count,
                    stats.oldest_overdue_due_date,
                    stats.last_paid_at
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
                LEFT JOIN member_contribution_charges c
                    ON c.member_user_id = u.id
                   AND c.competence = :competence
                LEFT JOIN (
                    SELECT
                        member_user_id,
                        SUM(CASE WHEN status = 'pending' AND due_date < :today_for_count THEN 1 ELSE 0 END)
                            AS overdue_charge_count,
                        MIN(CASE WHEN status = 'pending' AND due_date < :today_for_oldest THEN due_date ELSE NULL END)
                            AS oldest_overdue_due_date,
                        MAX(CASE WHEN status = 'paid' THEN paid_at ELSE NULL END) AS last_paid_at
                    FROM member_contribution_charges
                    GROUP BY member_user_id
                ) stats ON stats.member_user_id = u.id
                WHERE u.status = 'active'
                  AND u.association_status = 'member'
                  AND u.is_contributor = 1
                ORDER BY u.full_name ASC, u.id ASC
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'competence' => $normalizedCompetence,
                'today_for_count' => $today,
                'today_for_oldest' => $today,
            ]);

            $rows = $statement->fetchAll() ?: [];

            return array_map(
                fn (array $row): array => $this->normalizeContributionMemberRow($row, $normalizedCompetence),
                $rows
            );
        } catch (\Throwable $exception) {
            $this->loggerSafeWarning('Falha ao consultar associados para contribuições.', $exception, [
                'competence' => $normalizedCompetence,
            ]);

            return [];
        }
    }

    public function generateContributionCharges(string $competence, ?int $generatedByUserId = null): array
    {
        $this->bootMemberSchemaCompatibility();

        $normalizedCompetence = $this->normalizeCompetence($competence);
        $created = 0;
        $skippedExisting = 0;
        $skippedIncompleteProfile = 0;

        $insertStatement = $this->pdo->prepare(<<<SQL
            INSERT INTO member_contribution_charges (
                member_user_id,
                competence,
                due_date,
                amount_due,
                status,
                preferred_payment_method,
                payment_recorded_method,
                paid_at,
                exemption_reason,
                generated_by_user_id
            ) VALUES (
                :member_user_id,
                :competence,
                :due_date,
                :amount_due,
                'pending',
                :preferred_payment_method,
                NULL,
                NULL,
                NULL,
                :generated_by_user_id
            )
        SQL);

        foreach ($this->findContributionMembersByCompetence($normalizedCompetence) as $member) {
            $userId = (int) ($member['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (($member['charge_id'] ?? null) !== null) {
                $skippedExisting++;
                continue;
            }

            $amountDue = is_numeric((string) ($member['contribution_amount'] ?? null))
                ? (float) ($member['contribution_amount'] ?? 0)
                : 0.0;
            $preferredDueDay = (int) ($member['preferred_due_day'] ?? 0);

            if ($amountDue <= 0 || $preferredDueDay < 1 || $preferredDueDay > 28) {
                $skippedIncompleteProfile++;
                continue;
            }

            $dueDate = sprintf('%s-%02d', $normalizedCompetence, $preferredDueDay);

            try {
                $insertStatement->execute([
                    'member_user_id' => $userId,
                    'competence' => $normalizedCompetence,
                    'due_date' => $dueDate,
                    'amount_due' => number_format($amountDue, 2, '.', ''),
                    'preferred_payment_method' => $this->resolveContributionPaymentMethod(
                        (string) ($member['preferred_payment_method'] ?? '')
                    ),
                    'generated_by_user_id' => $generatedByUserId,
                ]);

                $chargeId = (int) $this->pdo->lastInsertId();
                if ($chargeId > 0) {
                    $this->appendContributionEvent(
                        $chargeId,
                        $userId,
                        'generated',
                        'Cobrança mensal gerada automaticamente.',
                        $generatedByUserId
                    );
                }

                $created++;
            } catch (\Throwable $exception) {
                if ($this->findContributionChargeForMemberAndCompetence($userId, $normalizedCompetence) !== null) {
                    $skippedExisting++;
                    continue;
                }

                $this->loggerSafeWarning('Falha ao gerar cobrança mensal de associado.', $exception, [
                    'member_user_id' => $userId,
                    'competence' => $normalizedCompetence,
                ]);
            }
        }

        return [
            'created' => $created,
            'skipped_existing' => $skippedExisting,
            'skipped_incomplete_profile' => $skippedIncompleteProfile,
        ];
    }

    public function findContributionChargeById(int $chargeId): ?array
    {
        $this->bootMemberSchemaCompatibility();

        try {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    c.*,
                    u.full_name AS member_full_name,
                    u.email AS member_email,
                    u.cpf AS member_cpf
                FROM member_contribution_charges c
                INNER JOIN member_users u ON u.id = c.member_user_id
                WHERE c.id = :id
                LIMIT 1
            SQL);
            $statement->execute(['id' => $chargeId]);
            $row = $statement->fetch();

            return is_array($row) ? $this->normalizeContributionChargeRow($row) : null;
        } catch (\Throwable $exception) {
            $this->loggerSafeWarning('Falha ao localizar cobrança mensal.', $exception, [
                'charge_id' => $chargeId,
            ]);

            return null;
        }
    }

    public function findContributionChargesByMember(int $memberUserId, int $limit = 12): array
    {
        $this->bootMemberSchemaCompatibility();

        $normalizedMemberId = max(0, $memberUserId);
        $normalizedLimit = max(1, min(240, $limit));

        if ($normalizedMemberId <= 0) {
            return [];
        }

        try {
            $statement = $this->pdo->prepare(sprintf(<<<SQL
                SELECT
                    c.*,
                    u.full_name AS member_full_name,
                    u.email AS member_email,
                    u.cpf AS member_cpf
                FROM member_contribution_charges c
                INNER JOIN member_users u ON u.id = c.member_user_id
                WHERE c.member_user_id = :member_user_id
                ORDER BY c.competence DESC, c.id DESC
                LIMIT %d
            SQL, $normalizedLimit));
            $statement->execute([
                'member_user_id' => $normalizedMemberId,
            ]);
            $rows = $statement->fetchAll() ?: [];

            return array_map(
                fn (array $row): array => $this->normalizeContributionChargeRow($row),
                $rows
            );
        } catch (\Throwable $exception) {
            $this->loggerSafeWarning('Falha ao carregar histórico de contribuições do membro.', $exception, [
                'member_user_id' => $normalizedMemberId,
                'limit' => $normalizedLimit,
            ]);

            return [];
        }
    }

    public function markContributionChargeAsPaid(
        int $chargeId,
        string $paymentMethod,
        ?int $actedByUserId = null
    ): bool {
        $this->bootMemberSchemaCompatibility();

        $normalizedPaymentMethod = $this->resolveContributionPaymentMethod($paymentMethod);

        try {
            $this->pdo->beginTransaction();

            $charge = $this->findContributionChargeByIdForUpdate($chargeId);
            if ($charge === null || (string) ($charge['status'] ?? '') !== 'pending') {
                $this->pdo->rollBack();

                return false;
            }

            $statement = $this->pdo->prepare(<<<SQL
                UPDATE member_contribution_charges
                SET
                    status = 'paid',
                    payment_recorded_method = :payment_recorded_method,
                    paid_at = NOW(),
                    exemption_reason = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                LIMIT 1
            SQL);
            $statement->execute([
                'id' => $chargeId,
                'payment_recorded_method' => $normalizedPaymentMethod,
            ]);

            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();

                return false;
            }

            $this->appendContributionEvent(
                $chargeId,
                (int) ($charge['member_user_id'] ?? 0),
                'paid',
                'Cobrança baixada manualmente como paga.',
                $actedByUserId,
                ['payment_method' => $normalizedPaymentMethod]
            );

            $this->pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->loggerSafeWarning('Falha ao baixar cobrança mensal como paga.', $exception, [
                'charge_id' => $chargeId,
            ]);

            return false;
        }
    }

    public function markContributionChargeAsExempt(
        int $chargeId,
        ?string $reason = null,
        ?int $actedByUserId = null
    ): bool {
        $this->bootMemberSchemaCompatibility();

        try {
            $this->pdo->beginTransaction();

            $charge = $this->findContributionChargeByIdForUpdate($chargeId);
            if ($charge === null || (string) ($charge['status'] ?? '') !== 'pending') {
                $this->pdo->rollBack();

                return false;
            }

            $normalizedReason = $this->nullableText($reason) ?? 'Isenção registrada manualmente.';

            $statement = $this->pdo->prepare(<<<SQL
                UPDATE member_contribution_charges
                SET
                    status = 'exempt',
                    payment_recorded_method = NULL,
                    paid_at = NULL,
                    exemption_reason = :exemption_reason,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                LIMIT 1
            SQL);
            $statement->execute([
                'id' => $chargeId,
                'exemption_reason' => $normalizedReason,
            ]);

            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();

                return false;
            }

            $this->appendContributionEvent(
                $chargeId,
                (int) ($charge['member_user_id'] ?? 0),
                'exempt',
                'Cobrança marcada como isenta.',
                $actedByUserId,
                ['reason' => $normalizedReason]
            );

            $this->pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->loggerSafeWarning('Falha ao isentar cobrança mensal.', $exception, [
                'charge_id' => $chargeId,
            ]);

            return false;
        }
    }

    public function registerContributionReminderEvent(
        int $chargeId,
        string $channel,
        ?int $actedByUserId = null,
        array $payload = []
    ): bool {
        $this->bootMemberSchemaCompatibility();

        try {
            $charge = $this->findContributionChargeById($chargeId);
            if ($charge === null) {
                return false;
            }

            $normalizedChannel = strtolower(trim($channel));
            $eventType = match ($normalizedChannel) {
                'email' => 'reminder_email_sent',
                'whatsapp' => 'reminder_whatsapp_opened',
                default => 'reminder_manual',
            };
            $eventDescription = match ($normalizedChannel) {
                'email' => 'Lembrete de cobrança enviado por e-mail.',
                'whatsapp' => 'Lembrete de cobrança aberto no WhatsApp.',
                default => 'Lembrete de cobrança registrado manualmente.',
            };

            $this->appendContributionEvent(
                $chargeId,
                (int) ($charge['member_user_id'] ?? 0),
                $eventType,
                $eventDescription,
                $actedByUserId,
                $payload
            );

            return true;
        } catch (\Throwable $exception) {
            $this->loggerSafeWarning('Falha ao registrar evento de lembrete de contribuição.', $exception, [
                'charge_id' => $chargeId,
                'channel' => $channel,
            ]);

            return false;
        }
    }

    public function updateContributionGatewayData(int $chargeId, array $data): bool
    {
        $this->bootMemberSchemaCompatibility();

        $charge = $this->findContributionChargeById($chargeId);
        if ($charge === null) {
            return false;
        }

        $sql = <<<SQL
            UPDATE member_contribution_charges
            SET
                gateway_provider = :gateway_provider,
                gateway_customer_id = :gateway_customer_id,
                gateway_payment_id = :gateway_payment_id,
                gateway_billing_type = :gateway_billing_type,
                gateway_status = :gateway_status,
                gateway_invoice_url = :gateway_invoice_url,
                gateway_bank_slip_url = :gateway_bank_slip_url,
                gateway_transaction_receipt_url = :gateway_transaction_receipt_url,
                gateway_pix_payload = :gateway_pix_payload,
                gateway_pix_encoded_image = :gateway_pix_encoded_image,
                gateway_pix_expiration_date = :gateway_pix_expiration_date,
                gateway_last_synced_at = :gateway_last_synced_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);

        return $statement->execute([
            'id' => $chargeId,
            'gateway_provider' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_provider'),
            'gateway_customer_id' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_customer_id'),
            'gateway_payment_id' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_payment_id'),
            'gateway_billing_type' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_billing_type'),
            'gateway_status' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_status'),
            'gateway_invoice_url' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_invoice_url'),
            'gateway_bank_slip_url' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_bank_slip_url'),
            'gateway_transaction_receipt_url' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_transaction_receipt_url'),
            'gateway_pix_payload' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_pix_payload'),
            'gateway_pix_encoded_image' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_pix_encoded_image'),
            'gateway_pix_expiration_date' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_pix_expiration_date'),
            'gateway_last_synced_at' => $this->resolveGatewayUpdateField($data, $charge, 'gateway_last_synced_at'),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $charge
     */
    private function resolveGatewayUpdateField(array $data, array $charge, string $field): ?string
    {
        if (array_key_exists($field, $data)) {
            return $this->nullableText($data[$field]);
        }

        return $this->nullableText($charge[$field] ?? null);
    }

    public function findContributionChargeByGatewayPaymentId(string $gatewayPaymentId): ?array
    {
        $this->bootMemberSchemaCompatibility();

        $normalizedPaymentId = trim($gatewayPaymentId);
        if ($normalizedPaymentId === '') {
            return null;
        }

        try {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    c.*,
                    u.full_name AS member_full_name,
                    u.email AS member_email,
                    u.cpf AS member_cpf
                FROM member_contribution_charges c
                INNER JOIN member_users u ON u.id = c.member_user_id
                WHERE c.gateway_payment_id = :gateway_payment_id
                LIMIT 1
            SQL);
            $statement->execute([
                'gateway_payment_id' => $normalizedPaymentId,
            ]);
            $row = $statement->fetch();

            return is_array($row) ? $this->normalizeContributionChargeRow($row) : null;
        } catch (\Throwable $exception) {
            $this->loggerSafeWarning('Falha ao localizar cobrança por payment_id do gateway.', $exception, [
                'gateway_payment_id' => $normalizedPaymentId,
            ]);

            return null;
        }
    }

    private function createPasswordResetTokenInternal(
        int $userId,
        string $email,
        string $tokenHash,
        \DateTimeImmutable $expiresAt
    ): bool {
        $normalizedEmail = strtolower(trim($email));

        $this->pdo->beginTransaction();

        try {
            $invalidateStatement = $this->pdo->prepare(
                'UPDATE member_password_resets '
                . 'SET used_at = NOW() '
                . 'WHERE member_user_id = :member_user_id AND used_at IS NULL'
            );
            $invalidateStatement->execute([
                'member_user_id' => $userId,
            ]);

            $insertStatement = $this->pdo->prepare(
                'INSERT INTO member_password_resets (member_user_id, email, token_hash, expires_at) '
                . 'VALUES (:member_user_id, :email, :token_hash, :expires_at)'
            );
            $insertStatement->execute([
                'member_user_id' => $userId,
                'email' => $normalizedEmail,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            $this->pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findActivePasswordResetByTokenInternal(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT
                pr.id,
                pr.member_user_id,
                pr.email,
                pr.expires_at,
                pr.created_at,
                u.full_name AS user_full_name,
                u.email AS user_email,
                u.status AS user_status
            FROM member_password_resets pr
            INNER JOIN member_users u ON u.id = pr.member_user_id
            WHERE pr.token_hash = :token_hash
              AND pr.used_at IS NULL
              AND pr.expires_at >= NOW()
            ORDER BY pr.id DESC
            LIMIT 1
        SQL);
        $statement->execute([
            'token_hash' => $tokenHash,
        ]);

        $row = $statement->fetch();

        return $row ?: null;
    }

    private function consumePasswordResetTokenInternal(int $resetId, int $userId, string $passwordHash): bool
    {
        $this->pdo->beginTransaction();

        try {
            $consumeStatement = $this->pdo->prepare(
                'UPDATE member_password_resets '
                . 'SET used_at = NOW() '
                . 'WHERE id = :id '
                . 'AND member_user_id = :member_user_id '
                . 'AND used_at IS NULL '
                . 'AND expires_at >= NOW() '
                . 'LIMIT 1'
            );
            $consumeStatement->execute([
                'id' => $resetId,
                'member_user_id' => $userId,
            ]);

            if ($consumeStatement->rowCount() !== 1) {
                $this->pdo->rollBack();

                return false;
            }

            $updatePasswordStatement = $this->pdo->prepare(
                'UPDATE member_users '
                . 'SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP '
                . 'WHERE id = :id '
                . 'LIMIT 1'
            );
            $updatePasswordStatement->execute([
                'id' => $userId,
                'password_hash' => $passwordHash,
            ]);

            if ($updatePasswordStatement->rowCount() !== 1) {
                $this->pdo->rollBack();

                return false;
            }

            $invalidateRemainingStatement = $this->pdo->prepare(
                'UPDATE member_password_resets '
                . 'SET used_at = NOW() '
                . 'WHERE member_user_id = :member_user_id AND used_at IS NULL'
            );
            $invalidateRemainingStatement->execute([
                'member_user_id' => $userId,
            ]);

            $this->pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
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
            5 => 'bookshop_operator',
            6 => 'finance_operator',
        ];
        $roleNameById = [
            1 => 'Membro',
            2 => 'Operador',
            3 => 'Gerente',
            4 => 'Administrador',
            5 => 'Operador da Livraria',
            6 => 'Operador Financeiro',
        ];

        $fallbackRoleKey = (string) ($roleKeyById[$roleId] ?? 'member');
        $fallbackRoleName = (string) ($roleNameById[$roleId] ?? 'Membro');

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $associationStatus = $this->resolveAssociationStatusValue(
            $row['association_status'] ?? null,
            $row,
            $status === 'pending' ? 'applicant' : 'member'
        );
        $roleKey = trim((string) ($row['role_key'] ?? ''));
        $roleName = trim((string) ($row['role_name'] ?? ''));
        $shouldFallbackRole = $associationStatus === 'member';

        $row['role_id'] = $shouldFallbackRole && $roleId > 0 ? $roleId : null;
        $row['role_key'] = $shouldFallbackRole
            ? ($roleKey !== '' ? $roleKey : $fallbackRoleKey)
            : '';
        $row['role_name'] = $shouldFallbackRole
            ? ($roleName !== '' ? $roleName : $fallbackRoleName)
            : '';
        $row['phone_mobile'] = $row['phone_mobile'] ?? null;
        $row['phone_landline'] = $row['phone_landline'] ?? null;
        $row['birth_date'] = $row['birth_date'] ?? null;
        $row['birth_place'] = $row['birth_place'] ?? null;
        $row['cpf'] = $row['cpf'] ?? null;
        $row['postal_code'] = $row['postal_code'] ?? null;
        $row['street_address'] = $row['street_address'] ?? null;
        $row['address_number'] = $row['address_number'] ?? null;
        $row['address_complement'] = $row['address_complement'] ?? null;
        $row['neighborhood'] = $row['neighborhood'] ?? null;
        $row['address_city'] = $row['address_city'] ?? null;
        $row['address_state'] = $row['address_state'] ?? null;
        $row['preferred_due_day'] = array_key_exists('preferred_due_day', $row) && $row['preferred_due_day'] !== null
            ? (int) $row['preferred_due_day']
            : null;
        $row['contribution_amount'] = $row['contribution_amount'] ?? null;
        $row['contribution_plan_label'] = $row['contribution_plan_label'] ?? null;
        $row['preferred_payment_method'] = $row['preferred_payment_method'] ?? null;
        $row['billing_email_opt_in'] = (int) ($row['billing_email_opt_in'] ?? 0);
        $row['billing_whatsapp_opt_in'] = (int) ($row['billing_whatsapp_opt_in'] ?? 0);
        $row['institutional_role'] = $row['institutional_role'] ?? null;
        $row['member_type'] = $row['member_type'] ?? null;
        $row['member_type_label'] = $this->resolveMemberTypeLabel((string) ($row['member_type'] ?? ''));
        $row['association_status'] = $associationStatus;
        $row['association_status_label'] = $this->resolveAssociationStatusLabel((string) ($row['association_status'] ?? ''));
        $row['is_contributor'] = (int) ($row['is_contributor'] ?? 0);
        $row['contributor_label'] = (int) ($row['is_contributor'] ?? 0) === 1 ? 'Sim' : 'Não';
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

    /**
     * @param array<string, mixed> $row
     */
    private function resolveAssociationStatusValue(?string $value, array $row, string $fallback): string
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['applicant', 'member', 'former'], true)) {
            return $normalized;
        }

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status === 'pending') {
            return 'applicant';
        }

        return in_array($fallback, ['applicant', 'member', 'former'], true) ? $fallback : 'member';
    }

    private function resolveAssociationStatusLabel(string $associationStatus): string
    {
        return match (strtolower(trim($associationStatus))) {
            'member' => 'Associado',
            'former' => 'Desligado',
            default => 'Solicitante',
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeContributionMemberRow(array $row, string $defaultCompetence): array
    {
        $row = $this->normalizeMemberRowWithDefaults($row);
        $row['charge_id'] = array_key_exists('charge_id', $row) && $row['charge_id'] !== null
            ? (int) $row['charge_id']
            : null;
        $row['charge_competence'] = $row['charge_competence'] ?? $defaultCompetence;
        $row['charge_due_date'] = $row['charge_due_date'] ?? null;
        $row['charge_amount_due'] = $row['charge_amount_due'] ?? null;
        $row['charge_status'] = $row['charge_status'] ?? null;
        $row['charge_preferred_payment_method'] = $row['charge_preferred_payment_method'] ?? null;
        $row['charge_payment_recorded_method'] = $row['charge_payment_recorded_method'] ?? null;
        $row['charge_paid_at'] = $row['charge_paid_at'] ?? null;
        $row['charge_exemption_reason'] = $row['charge_exemption_reason'] ?? null;
        $row['charge_gateway_provider'] = $row['charge_gateway_provider'] ?? null;
        $row['charge_gateway_customer_id'] = $row['charge_gateway_customer_id'] ?? null;
        $row['charge_gateway_payment_id'] = $row['charge_gateway_payment_id'] ?? null;
        $row['charge_gateway_billing_type'] = $row['charge_gateway_billing_type'] ?? null;
        $row['charge_gateway_status'] = $row['charge_gateway_status'] ?? null;
        $row['charge_gateway_invoice_url'] = $row['charge_gateway_invoice_url'] ?? null;
        $row['charge_gateway_bank_slip_url'] = $row['charge_gateway_bank_slip_url'] ?? null;
        $row['charge_gateway_transaction_receipt_url'] = $row['charge_gateway_transaction_receipt_url'] ?? null;
        $row['charge_gateway_pix_payload'] = $row['charge_gateway_pix_payload'] ?? null;
        $row['charge_gateway_pix_encoded_image'] = $row['charge_gateway_pix_encoded_image'] ?? null;
        $row['charge_gateway_pix_expiration_date'] = $row['charge_gateway_pix_expiration_date'] ?? null;
        $row['charge_gateway_last_synced_at'] = $row['charge_gateway_last_synced_at'] ?? null;
        $row['overdue_charge_count'] = (int) ($row['overdue_charge_count'] ?? 0);
        $row['oldest_overdue_due_date'] = $row['oldest_overdue_due_date'] ?? null;
        $row['last_paid_at'] = $row['last_paid_at'] ?? null;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeContributionChargeRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['member_user_id'] = (int) ($row['member_user_id'] ?? 0);
        $row['amount_due'] = $row['amount_due'] ?? null;
        $row['status'] = $row['status'] ?? null;
        $row['preferred_payment_method'] = $row['preferred_payment_method'] ?? null;
        $row['payment_recorded_method'] = $row['payment_recorded_method'] ?? null;
        $row['paid_at'] = $row['paid_at'] ?? null;
        $row['exemption_reason'] = $row['exemption_reason'] ?? null;
        $row['gateway_provider'] = $row['gateway_provider'] ?? null;
        $row['gateway_customer_id'] = $row['gateway_customer_id'] ?? null;
        $row['gateway_payment_id'] = $row['gateway_payment_id'] ?? null;
        $row['gateway_billing_type'] = $row['gateway_billing_type'] ?? null;
        $row['gateway_status'] = $row['gateway_status'] ?? null;
        $row['gateway_invoice_url'] = $row['gateway_invoice_url'] ?? null;
        $row['gateway_bank_slip_url'] = $row['gateway_bank_slip_url'] ?? null;
        $row['gateway_transaction_receipt_url'] = $row['gateway_transaction_receipt_url'] ?? null;
        $row['gateway_pix_payload'] = $row['gateway_pix_payload'] ?? null;
        $row['gateway_pix_encoded_image'] = $row['gateway_pix_encoded_image'] ?? null;
        $row['gateway_pix_expiration_date'] = $row['gateway_pix_expiration_date'] ?? null;
        $row['gateway_last_synced_at'] = $row['gateway_last_synced_at'] ?? null;
        $row['member_full_name'] = $row['member_full_name'] ?? '';
        $row['member_email'] = $row['member_email'] ?? '';
        $row['member_cpf'] = $row['member_cpf'] ?? null;

        return $row;
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
                cpf VARCHAR(14) NULL,
                postal_code VARCHAR(9) NULL,
                street_address VARCHAR(160) NULL,
                address_number VARCHAR(20) NULL,
                address_complement VARCHAR(120) NULL,
                neighborhood VARCHAR(120) NULL,
                address_city VARCHAR(120) NULL,
                address_state CHAR(2) NULL,
                preferred_due_day TINYINT UNSIGNED NULL,
                contribution_amount DECIMAL(10,2) NULL,
                contribution_plan_label VARCHAR(120) NULL,
                preferred_payment_method VARCHAR(30) NULL,
                billing_email_opt_in TINYINT(1) NOT NULL DEFAULT 0,
                billing_whatsapp_opt_in TINYINT(1) NOT NULL DEFAULT 0,
                institutional_role VARCHAR(120) NULL,
                member_type VARCHAR(20) NULL,
                association_status VARCHAR(20) NOT NULL DEFAULT 'applicant',
                is_contributor TINYINT(1) NOT NULL DEFAULT 0,
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

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS member_password_resets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_user_id BIGINT UNSIGNED NOT NULL,
                email VARCHAR(180) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_member_password_resets_token_hash (token_hash),
                KEY idx_member_password_resets_member_user_id (member_user_id),
                CONSTRAINT fk_member_password_resets_member
                    FOREIGN KEY (member_user_id) REFERENCES member_users(id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS member_contribution_charges (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_user_id BIGINT UNSIGNED NOT NULL,
                competence CHAR(7) NOT NULL,
                due_date DATE NOT NULL,
                amount_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                preferred_payment_method VARCHAR(30) NULL,
                payment_recorded_method VARCHAR(30) NULL,
                paid_at DATETIME NULL,
                exemption_reason VARCHAR(255) NULL,
                gateway_provider VARCHAR(30) NULL,
                gateway_customer_id VARCHAR(64) NULL,
                gateway_payment_id VARCHAR(64) NULL,
                gateway_billing_type VARCHAR(20) NULL,
                gateway_status VARCHAR(40) NULL,
                gateway_invoice_url VARCHAR(255) NULL,
                gateway_bank_slip_url VARCHAR(255) NULL,
                gateway_transaction_receipt_url VARCHAR(255) NULL,
                gateway_pix_payload LONGTEXT NULL,
                gateway_pix_encoded_image LONGTEXT NULL,
                gateway_pix_expiration_date DATETIME NULL,
                gateway_last_synced_at DATETIME NULL,
                generated_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_member_contribution_charge_member_competence (member_user_id, competence),
                KEY idx_member_contribution_charge_status_due (status, due_date),
                KEY idx_member_contribution_charge_member (member_user_id),
                KEY idx_member_contribution_charge_gateway_payment (gateway_payment_id),
                CONSTRAINT fk_member_contribution_charge_member
                    FOREIGN KEY (member_user_id) REFERENCES member_users(id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS member_contribution_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                charge_id BIGINT UNSIGNED NOT NULL,
                member_user_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(40) NOT NULL,
                event_description VARCHAR(255) NOT NULL,
                acted_by_user_id BIGINT UNSIGNED NULL,
                payload_json LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_member_contribution_events_charge (charge_id),
                KEY idx_member_contribution_events_member (member_user_id),
                CONSTRAINT fk_member_contribution_events_charge
                    FOREIGN KEY (charge_id) REFERENCES member_contribution_charges(id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT fk_member_contribution_events_member
                    FOREIGN KEY (member_user_id) REFERENCES member_users(id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS member_user_administration_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_user_id BIGINT UNSIGNED NOT NULL,
                acted_by_user_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(40) NOT NULL,
                event_description VARCHAR(255) NOT NULL,
                payload_json LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_member_user_administration_events_member (member_user_id),
                KEY idx_member_user_administration_events_actor (acted_by_user_id),
                CONSTRAINT fk_member_user_administration_events_member
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
            'cpf',
            'ALTER TABLE member_users ADD COLUMN cpf VARCHAR(14) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'postal_code',
            'ALTER TABLE member_users ADD COLUMN postal_code VARCHAR(9) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'street_address',
            'ALTER TABLE member_users ADD COLUMN street_address VARCHAR(160) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'address_number',
            'ALTER TABLE member_users ADD COLUMN address_number VARCHAR(20) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'address_complement',
            'ALTER TABLE member_users ADD COLUMN address_complement VARCHAR(120) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'neighborhood',
            'ALTER TABLE member_users ADD COLUMN neighborhood VARCHAR(120) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'address_city',
            'ALTER TABLE member_users ADD COLUMN address_city VARCHAR(120) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'address_state',
            'ALTER TABLE member_users ADD COLUMN address_state CHAR(2) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'preferred_due_day',
            'ALTER TABLE member_users ADD COLUMN preferred_due_day TINYINT UNSIGNED NULL'
        );
        $this->ensureColumn(
            'member_users',
            'contribution_amount',
            'ALTER TABLE member_users ADD COLUMN contribution_amount DECIMAL(10,2) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'contribution_plan_label',
            'ALTER TABLE member_users ADD COLUMN contribution_plan_label VARCHAR(120) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'preferred_payment_method',
            'ALTER TABLE member_users ADD COLUMN preferred_payment_method VARCHAR(30) NULL'
        );
        $this->ensureColumn(
            'member_users',
            'billing_email_opt_in',
            'ALTER TABLE member_users ADD COLUMN billing_email_opt_in TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->ensureColumn(
            'member_users',
            'billing_whatsapp_opt_in',
            'ALTER TABLE member_users ADD COLUMN billing_whatsapp_opt_in TINYINT(1) NOT NULL DEFAULT 0'
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
            'association_status',
            "ALTER TABLE member_users ADD COLUMN association_status VARCHAR(20) NOT NULL DEFAULT 'applicant'"
        );
        $this->ensureColumn(
            'member_users',
            'is_contributor',
            'ALTER TABLE member_users ADD COLUMN is_contributor TINYINT(1) NOT NULL DEFAULT 0'
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
        $this->ensureColumn(
            'member_password_resets',
            'member_user_id',
            'ALTER TABLE member_password_resets ADD COLUMN member_user_id BIGINT UNSIGNED NOT NULL'
        );
        $this->ensureColumn(
            'member_password_resets',
            'email',
            'ALTER TABLE member_password_resets ADD COLUMN email VARCHAR(180) NOT NULL'
        );
        $this->ensureColumn(
            'member_password_resets',
            'token_hash',
            'ALTER TABLE member_password_resets ADD COLUMN token_hash CHAR(64) NOT NULL'
        );
        $this->ensureColumn(
            'member_password_resets',
            'expires_at',
            'ALTER TABLE member_password_resets ADD COLUMN expires_at DATETIME NOT NULL'
        );
        $this->ensureColumn(
            'member_password_resets',
            'used_at',
            'ALTER TABLE member_password_resets ADD COLUMN used_at DATETIME NULL'
        );
        $this->ensureColumn(
            'member_password_resets',
            'created_at',
            'ALTER TABLE member_password_resets ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'member_user_id',
            'ALTER TABLE member_contribution_charges ADD COLUMN member_user_id BIGINT UNSIGNED NOT NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'competence',
            'ALTER TABLE member_contribution_charges ADD COLUMN competence CHAR(7) NOT NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'due_date',
            'ALTER TABLE member_contribution_charges ADD COLUMN due_date DATE NOT NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'amount_due',
            'ALTER TABLE member_contribution_charges ADD COLUMN amount_due DECIMAL(10,2) NOT NULL DEFAULT 0.00'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'status',
            "ALTER TABLE member_contribution_charges ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'"
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'preferred_payment_method',
            'ALTER TABLE member_contribution_charges ADD COLUMN preferred_payment_method VARCHAR(30) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'payment_recorded_method',
            'ALTER TABLE member_contribution_charges ADD COLUMN payment_recorded_method VARCHAR(30) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'paid_at',
            'ALTER TABLE member_contribution_charges ADD COLUMN paid_at DATETIME NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'exemption_reason',
            'ALTER TABLE member_contribution_charges ADD COLUMN exemption_reason VARCHAR(255) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_provider',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_provider VARCHAR(30) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_customer_id',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_customer_id VARCHAR(64) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_payment_id',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_payment_id VARCHAR(64) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_billing_type',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_billing_type VARCHAR(20) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_status',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_status VARCHAR(40) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_invoice_url',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_invoice_url VARCHAR(255) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_bank_slip_url',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_bank_slip_url VARCHAR(255) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_transaction_receipt_url',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_transaction_receipt_url VARCHAR(255) NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_pix_payload',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_pix_payload LONGTEXT NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_pix_encoded_image',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_pix_encoded_image LONGTEXT NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_pix_expiration_date',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_pix_expiration_date DATETIME NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'gateway_last_synced_at',
            'ALTER TABLE member_contribution_charges ADD COLUMN gateway_last_synced_at DATETIME NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'generated_by_user_id',
            'ALTER TABLE member_contribution_charges ADD COLUMN generated_by_user_id BIGINT UNSIGNED NULL'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'created_at',
            'ALTER TABLE member_contribution_charges ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );
        $this->ensureColumn(
            'member_contribution_charges',
            'updated_at',
            'ALTER TABLE member_contribution_charges ADD COLUMN updated_at TIMESTAMP NOT NULL '
            . 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
        $this->ensureColumn(
            'member_contribution_events',
            'charge_id',
            'ALTER TABLE member_contribution_events ADD COLUMN charge_id BIGINT UNSIGNED NOT NULL'
        );
        $this->ensureColumn(
            'member_contribution_events',
            'member_user_id',
            'ALTER TABLE member_contribution_events ADD COLUMN member_user_id BIGINT UNSIGNED NOT NULL'
        );
        $this->ensureColumn(
            'member_contribution_events',
            'event_type',
            'ALTER TABLE member_contribution_events ADD COLUMN event_type VARCHAR(40) NOT NULL'
        );
        $this->ensureColumn(
            'member_contribution_events',
            'event_description',
            'ALTER TABLE member_contribution_events ADD COLUMN event_description VARCHAR(255) NOT NULL'
        );
        $this->ensureColumn(
            'member_contribution_events',
            'acted_by_user_id',
            'ALTER TABLE member_contribution_events ADD COLUMN acted_by_user_id BIGINT UNSIGNED NULL'
        );
        $this->ensureColumn(
            'member_contribution_events',
            'payload_json',
            'ALTER TABLE member_contribution_events ADD COLUMN payload_json LONGTEXT NULL'
        );
        $this->ensureColumn(
            'member_contribution_events',
            'created_at',
            'ALTER TABLE member_contribution_events ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );
        $this->ensureColumn(
            'member_user_administration_events',
            'member_user_id',
            'ALTER TABLE member_user_administration_events ADD COLUMN member_user_id BIGINT UNSIGNED NOT NULL'
        );
        $this->ensureColumn(
            'member_user_administration_events',
            'acted_by_user_id',
            'ALTER TABLE member_user_administration_events ADD COLUMN acted_by_user_id BIGINT UNSIGNED NULL'
        );
        $this->ensureColumn(
            'member_user_administration_events',
            'event_type',
            'ALTER TABLE member_user_administration_events ADD COLUMN event_type VARCHAR(40) NOT NULL'
        );
        $this->ensureColumn(
            'member_user_administration_events',
            'event_description',
            'ALTER TABLE member_user_administration_events ADD COLUMN event_description VARCHAR(255) NOT NULL'
        );
        $this->ensureColumn(
            'member_user_administration_events',
            'payload_json',
            'ALTER TABLE member_user_administration_events ADD COLUMN payload_json LONGTEXT NULL'
        );
        $this->ensureColumn(
            'member_user_administration_events',
            'created_at',
            'ALTER TABLE member_user_administration_events ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );

        $this->ensureDefaultRoles();
        $this->backfillAssociationFields();
        $currentManagementId = $this->ensureCurrentManagementId();
        $this->migrateLegacyInstitutionalRolesToCurrentManagement($currentManagementId);
    }

    private function bootMemberSchemaCompatibility(): void
    {
        if ($this->memberSchemaCompatibilityBooted) {
            return;
        }

        try {
            $this->ensureMemberSchemaCompatibility();
        } catch (\Throwable $exception) {
        }

        $this->memberSchemaCompatibilityBooted = true;
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
                :management_id_insert,
                u.id,
                u.institutional_role,
                COALESCE(DATE(u.approved_at), DATE(u.created_at), CURRENT_DATE),
                NULL
            FROM member_users u
            LEFT JOIN member_management_roles mmr
                ON mmr.management_id = :management_id_join
               AND mmr.member_user_id = u.id
               AND mmr.ends_at IS NULL
            WHERE u.institutional_role IS NOT NULL
              AND TRIM(u.institutional_role) <> ''
              AND mmr.id IS NULL
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'management_id_insert' => $managementId,
            'management_id_join' => $managementId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findContributionChargeForMemberAndCompetence(int $userId, string $competence): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT *
            FROM member_contribution_charges
            WHERE member_user_id = :member_user_id
              AND competence = :competence
            LIMIT 1
        SQL);
        $statement->execute([
            'member_user_id' => $userId,
            'competence' => $competence,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->normalizeContributionChargeRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findContributionChargeByIdForUpdate(int $chargeId): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT *
            FROM member_contribution_charges
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        SQL);
        $statement->execute(['id' => $chargeId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->normalizeContributionChargeRow($row) : null;
    }

    private function normalizeCompetence(string $competence): string
    {
        $normalized = trim($competence);

        if (preg_match('/^\d{4}-\d{2}$/', $normalized) === 1) {
            return $normalized;
        }

        return date('Y-m');
    }

    private function resolveContributionPaymentMethod(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['boleto', 'pix', 'pix_automatico', 'manual'], true)
            ? $normalized
            : 'manual';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendContributionEvent(
        int $chargeId,
        int $memberUserId,
        string $eventType,
        string $eventDescription,
        ?int $actedByUserId = null,
        array $payload = []
    ): void {
        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO member_contribution_events (
                charge_id,
                member_user_id,
                event_type,
                event_description,
                acted_by_user_id,
                payload_json
            ) VALUES (
                :charge_id,
                :member_user_id,
                :event_type,
                :event_description,
                :acted_by_user_id,
                :payload_json
            )
        SQL);

        $payloadJson = null;
        if ($payload !== []) {
            $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payloadJson = $encodedPayload !== false ? $encodedPayload : null;
        }

        $statement->execute([
            'charge_id' => $chargeId,
            'member_user_id' => $memberUserId,
            'event_type' => $eventType,
            'event_description' => $eventDescription,
            'acted_by_user_id' => $actedByUserId,
            'payload_json' => $payloadJson,
        ]);
    }

    /**
     * @param array{
     *     member_type: ?string,
     *     institutional_role: ?string,
     *     association_status: string,
     *     is_contributor: int,
     *     status: string
     * } $normalizedState
     * @param array<int, string> $rulesApplied
     */
    private function approveAndAssignRoleInternal(
        int $id,
        int $roleId,
        array $normalizedState,
        array $rulesApplied,
        ?int $actedByUserId = null
    ): bool {
        if ($id <= 0) {
            return false;
        }

        if ($normalizedState['association_status'] === 'member' && $roleId <= 0) {
            return false;
        }

        $currentUser = $this->findById($id);
        if ($currentUser === null) {
            return false;
        }

        $previousSnapshot = $this->buildAdministrativeSnapshot($currentUser);
        $roleIdForUpdate = $normalizedState['association_status'] === 'member' ? $roleId : null;

        $sql = <<<SQL
            UPDATE member_users
            SET
                role_id = :role_id,
                institutional_role = :institutional_role,
                member_type = :member_type,
                association_status = :association_status,
                is_contributor = :is_contributor,
                status = :account_status,
                approved_at = CASE
                    WHEN :account_status_for_approval = 'active' AND approved_at IS NULL THEN NOW()
                    ELSE approved_at
                END
            WHERE id = :id
            LIMIT 1
        SQL;

        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'id' => $id,
                'role_id' => $roleIdForUpdate,
                'institutional_role' => $normalizedState['institutional_role'],
                'member_type' => $normalizedState['member_type'],
                'association_status' => $normalizedState['association_status'],
                'is_contributor' => $normalizedState['is_contributor'],
                'account_status' => $normalizedState['status'],
                'account_status_for_approval' => $normalizedState['status'],
            ]);

            if (!$this->syncInstitutionalRoleForCurrentManagement($id, $normalizedState['institutional_role'])) {
                $this->pdo->rollBack();

                return false;
            }

            $updatedUser = $this->findById($id);
            if ($updatedUser !== null) {
                $currentSnapshot = $this->buildAdministrativeSnapshot($updatedUser);

                if ($currentSnapshot !== $previousSnapshot) {
                    try {
                        $this->appendUserAdministrationEvent(
                            $id,
                            'admin_state_changed',
                            $this->buildAdministrativeEventDescription($currentSnapshot),
                            $actedByUserId,
                            [
                                'previous' => $previousSnapshot,
                                'current' => $currentSnapshot,
                                'rules_applied' => $rulesApplied,
                            ]
                        );
                    } catch (\Throwable $eventException) {
                        $this->loggerSafeWarning('Falha ao registrar histórico administrativo do usuário.', $eventException, [
                            'member_user_id' => $id,
                            'acted_by_user_id' => $actedByUserId,
                        ]);
                    }
                }
            }

            $this->pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array{
     *     member_type: ?string,
     *     institutional_role: ?string,
     *     association_status: string,
     *     is_contributor: int,
     *     status: string
     * } $normalizedState
     */
    private function approveAndAssignRoleFallback(
        int $id,
        int $roleId,
        array $normalizedState,
        \Throwable $previousException
    ): bool {
        try {
            $roleIdForUpdate = $normalizedState['association_status'] === 'member' ? $roleId : null;
            $fallbackStatement = $this->pdo->prepare(<<<SQL
                UPDATE member_users
                SET
                    role_id = :role_id,
                    status = :account_status,
                    approved_at = CASE
                        WHEN :account_status_for_approval = 'active' AND approved_at IS NULL THEN NOW()
                        ELSE approved_at
                    END
                WHERE id = :id
                LIMIT 1
            SQL);

            return $fallbackStatement->execute([
                'id' => $id,
                'role_id' => $roleIdForUpdate,
                'account_status' => $normalizedState['status'],
                'account_status_for_approval' => $normalizedState['status'],
            ]) && $this->syncInstitutionalRoleForCurrentManagement($id, $normalizedState['institutional_role']);
        } catch (\Throwable $fallbackException) {
            $this->loggerSafeWarning('Falha ao atualizar situação administrativa do usuário.', $fallbackException, [
                'member_user_id' => $id,
                'role_id' => $roleId,
                'association_status' => $normalizedState['association_status'],
                'status' => $normalizedState['status'],
                'previous_error' => $previousException->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{
     *     0: array{
     *         member_type: ?string,
     *         institutional_role: ?string,
     *         association_status: string,
     *         is_contributor: int,
     *         status: string
     *     },
     *     1: array<int, string>
     * }
     */
    private function normalizeAdministrativeState(
        ?string $memberType,
        ?string $institutionalRole,
        ?string $associationStatus,
        ?bool $isContributor,
        ?string $accountStatus
    ): array {
        $rulesApplied = [];
        $normalizedMemberType = $this->nullableText($memberType);
        $normalizedInstitutionalRole = $this->nullableText($institutionalRole);
        $normalizedAssociationStatus = strtolower(trim((string) $associationStatus));
        if (!in_array($normalizedAssociationStatus, ['applicant', 'member', 'former'], true)) {
            $normalizedAssociationStatus = 'member';
        }

        $normalizedAccountStatus = strtolower(trim((string) $accountStatus));
        if (!in_array($normalizedAccountStatus, ['pending', 'active', 'blocked'], true)) {
            $normalizedAccountStatus = 'active';
        }

        $normalizedContributor = $isContributor;
        if ($normalizedContributor === null) {
            $normalizedContributor = $normalizedMemberType !== null;
            $rulesApplied[] = 'contributor_defaulted_from_member_type';
        }

        if ($normalizedAssociationStatus === 'applicant') {
            $rulesApplied[] = 'applicant_pending_access';
            $rulesApplied[] = 'applicant_without_member_metadata';

            return [[
                'member_type' => null,
                'institutional_role' => null,
                'association_status' => 'applicant',
                'is_contributor' => 0,
                'status' => 'pending',
            ], $rulesApplied];
        }

        if ($normalizedAssociationStatus === 'former') {
            $rulesApplied[] = 'former_blocked_access';
            $rulesApplied[] = 'former_without_member_metadata';

            return [[
                'member_type' => null,
                'institutional_role' => null,
                'association_status' => 'former',
                'is_contributor' => 0,
                'status' => 'blocked',
            ], $rulesApplied];
        }

        if (!in_array($normalizedAccountStatus, ['active', 'blocked'], true)) {
            $normalizedAccountStatus = 'active';
            $rulesApplied[] = 'member_access_normalized_to_active';
        }

        if ($normalizedAccountStatus !== 'active') {
            $normalizedInstitutionalRole = null;
            $rulesApplied[] = 'inactive_member_without_institutional_role';
        }

        return [[
            'member_type' => $normalizedMemberType,
            'institutional_role' => $normalizedInstitutionalRole,
            'association_status' => 'member',
            'is_contributor' => $normalizedContributor ? 1 : 0,
            'status' => $normalizedAccountStatus,
        ], $rulesApplied];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function buildAdministrativeSnapshot(array $user): array
    {
        $associationStatus = $this->resolveAssociationStatusValue(
            $user['association_status'] ?? null,
            $user,
            strtolower(trim((string) ($user['status'] ?? ''))) === 'pending' ? 'applicant' : 'member'
        );
        $status = strtolower(trim((string) ($user['status'] ?? 'pending')));
        if (!in_array($status, ['pending', 'active', 'blocked'], true)) {
            $status = 'pending';
        }

        return [
            'role_id' => (int) ($user['role_id'] ?? 0),
            'role_key' => (string) ($user['role_key'] ?? ''),
            'role_name' => (string) ($user['role_name'] ?? ''),
            'institutional_role' => $this->nullableText($user['institutional_role'] ?? null),
            'member_type' => $this->nullableText($user['member_type'] ?? null),
            'association_status' => $associationStatus,
            'is_contributor' => (int) ($user['is_contributor'] ?? 0) === 1 ? 1 : 0,
            'status' => $status,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function buildAdministrativeEventDescription(array $snapshot): string
    {
        return sprintf(
            'Situação administrativa atualizada: acesso %s, vínculo %s, contribuinte %s.',
            strtolower($this->resolveAccountStatusLabel((string) ($snapshot['status'] ?? 'pending'))),
            strtolower($this->resolveAssociationStatusLabel((string) ($snapshot['association_status'] ?? 'applicant'))),
            ((int) ($snapshot['is_contributor'] ?? 0) === 1) ? 'sim' : 'não'
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendUserAdministrationEvent(
        int $memberUserId,
        string $eventType,
        string $eventDescription,
        ?int $actedByUserId = null,
        array $payload = []
    ): void {
        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO member_user_administration_events (
                member_user_id,
                acted_by_user_id,
                event_type,
                event_description,
                payload_json
            ) VALUES (
                :member_user_id,
                :acted_by_user_id,
                :event_type,
                :event_description,
                :payload_json
            )
        SQL);

        $payloadJson = null;
        if ($payload !== []) {
            $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payloadJson = $encodedPayload !== false ? $encodedPayload : null;
        }

        $statement->execute([
            'member_user_id' => $memberUserId,
            'acted_by_user_id' => $actedByUserId,
            'event_type' => $eventType,
            'event_description' => $eventDescription,
            'payload_json' => $payloadJson,
        ]);
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function normalizeUserAdministrationEvent(array $event): array
    {
        $payload = [];
        $payloadJson = trim((string) ($event['payload_json'] ?? ''));
        if ($payloadJson !== '') {
            $decoded = json_decode($payloadJson, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $actedByUserId = (int) ($event['acted_by_user_id'] ?? 0);

        return array_merge($event, [
            'payload' => $payload,
            'acted_by_user_display' => $this->resolveUserDisplayName(
                $event['acted_by_user_full_name'] ?? null,
                $event['acted_by_user_email'] ?? null,
                $actedByUserId > 0 ? $actedByUserId : null
            ),
        ]);
    }

    private function resolveUserDisplayName(mixed $fullName, mixed $email, ?int $userId = null): string
    {
        $normalizedFullName = trim((string) $fullName);
        if ($normalizedFullName !== '') {
            return $normalizedFullName;
        }

        $normalizedEmail = trim((string) $email);
        if ($normalizedEmail !== '') {
            return $normalizedEmail;
        }

        if ($userId !== null && $userId > 0) {
            return 'Usuário #' . $userId;
        }

        return 'Sistema';
    }

    private function resolveAccountStatusLabel(string $value): string
    {
        return match (strtolower(trim($value))) {
            'active' => 'Ativo',
            'blocked' => 'Bloqueado',
            default => 'Pendente',
        };
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
                ('admin', 'Administrador', 'Gestão completa de usuários e permissões.'),
                ('bookshop_operator', 'Operador da Livraria', 'Acesso exclusivo ao módulo interno da Livraria.'),
                ('finance_operator', 'Operador Financeiro', 'Acesso exclusivo ao acompanhamento financeiro de vendas e cancelamentos.')
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description)
        SQL);
    }

    private function backfillAssociationFields(): void
    {
        $this->pdo->exec(<<<SQL
            UPDATE member_users
            SET association_status = CASE
                WHEN status = 'pending' THEN 'applicant'
                WHEN association_status IS NULL OR TRIM(association_status) = '' THEN 'member'
                ELSE association_status
            END
        SQL);

        $this->pdo->exec(<<<SQL
            UPDATE member_users
            SET is_contributor = CASE
                WHEN association_status <> 'member' THEN 0
                WHEN is_contributor = 1 THEN 1
                WHEN member_type IS NOT NULL AND TRIM(member_type) <> '' THEN 1
                WHEN contribution_amount IS NOT NULL AND contribution_amount > 0 THEN 1
                ELSE 0
            END
        SQL);

        $this->pdo->exec(<<<SQL
            UPDATE member_users
            SET
                status = 'pending',
                role_id = NULL,
                member_type = NULL,
                institutional_role = NULL,
                is_contributor = 0
            WHERE association_status = 'applicant'
        SQL);

        $this->pdo->exec(<<<SQL
            UPDATE member_users
            SET
                status = 'blocked',
                role_id = NULL,
                member_type = NULL,
                institutional_role = NULL,
                is_contributor = 0
            WHERE association_status = 'former'
        SQL);

        $this->pdo->exec(<<<SQL
            UPDATE member_users
            SET status = 'active'
            WHERE association_status = 'member'
              AND status NOT IN ('active', 'blocked')
        SQL);

        $this->pdo->exec(<<<SQL
            UPDATE member_users
            SET institutional_role = NULL
            WHERE association_status = 'member'
              AND status <> 'active'
        SQL);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function loggerSafeWarning(string $message, \Throwable $exception, array $context = []): void
    {
        $context['error'] = $exception->getMessage();

        $encodedContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedContext === false) {
            $encodedContext = '{}';
        }

        @error_log($message . ' ' . $encodedContext);
    }
}
