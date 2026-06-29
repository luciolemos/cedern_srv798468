<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Member;

use App\Domain\Member\MemberAuthRepository;

class FallbackMemberAuthRepository implements MemberAuthRepository
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $roles = [
        ['id' => 1, 'role_key' => 'member', 'name' => 'Membro', 'description' => 'Acesso básico.'],
        ['id' => 2, 'role_key' => 'operator', 'name' => 'Operador', 'description' => 'Acesso operacional.'],
        ['id' => 3, 'role_key' => 'manager', 'name' => 'Gerente', 'description' => 'Acesso de gestão.'],
        ['id' => 4, 'role_key' => 'admin', 'name' => 'Administrador', 'description' => 'Acesso administrativo.'],
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

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $users = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $passwordResets = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $contributionCharges = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $contributionEvents = [];

    private int $nextId = 1;

    private int $nextPasswordResetId = 1;

    private int $nextContributionChargeId = 1;

    private int $nextContributionEventId = 1;

    public function createPendingUser(array $data): int
    {
        $id = $this->nextId++;

        $this->users[$id] = [
            'id' => $id,
            'full_name' => trim((string) ($data['full_name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'password_hash' => (string) ($data['password_hash'] ?? ''),
            'status' => 'pending',
            'phone_mobile' => null,
            'phone_landline' => null,
            'birth_date' => null,
            'birth_place' => null,
            'cpf' => null,
            'postal_code' => null,
            'street_address' => null,
            'address_number' => null,
            'address_complement' => null,
            'neighborhood' => null,
            'address_city' => null,
            'address_state' => null,
            'preferred_due_day' => null,
            'contribution_amount' => null,
            'contribution_plan_label' => null,
            'preferred_payment_method' => null,
            'billing_email_opt_in' => 0,
            'billing_whatsapp_opt_in' => 0,
            'institutional_role' => null,
            'member_type' => null,
            'member_type_label' => 'Não definido',
            'profile_photo_path' => null,
            'privacy_notice_version' => null,
            'privacy_notice_accepted_at' => null,
            'profile_completed' => 0,
            'role_id' => null,
            'role_key' => null,
            'role_name' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $id;
    }

    public function createPasswordResetToken(
        int $userId,
        string $email,
        string $tokenHash,
        \DateTimeImmutable $expiresAt
    ): bool {
        if (!isset($this->users[$userId])) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        foreach ($this->passwordResets as &$passwordReset) {
            if ((int) ($passwordReset['member_user_id'] ?? 0) !== $userId) {
                continue;
            }

            if (($passwordReset['used_at'] ?? null) === null) {
                $passwordReset['used_at'] = $now;
            }
        }
        unset($passwordReset);

        $resetId = $this->nextPasswordResetId++;
        $this->passwordResets[$resetId] = [
            'id' => $resetId,
            'member_user_id' => $userId,
            'email' => strtolower(trim($email)),
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'used_at' => null,
            'created_at' => $now,
        ];

        return true;
    }

    public function findByEmail(string $email): ?array
    {
        $needle = strtolower(trim($email));

        foreach ($this->users as $user) {
            if ((string) ($user['email'] ?? '') === $needle) {
                return $this->withMemberTypeLabel($user);
            }
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        if (!isset($this->users[$id])) {
            return null;
        }

        return $this->withMemberTypeLabel($this->users[$id]);
    }

    public function findActivePasswordResetByToken(string $tokenHash): ?array
    {
        $now = new \DateTimeImmutable('now');

        foreach ($this->passwordResets as $passwordReset) {
            if ((string) ($passwordReset['token_hash'] ?? '') !== $tokenHash) {
                continue;
            }

            if (($passwordReset['used_at'] ?? null) !== null) {
                continue;
            }

            $expiresAt = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string) ($passwordReset['expires_at'] ?? '')
            );

            if (!$expiresAt instanceof \DateTimeImmutable || $expiresAt < $now) {
                continue;
            }

            $userId = (int) ($passwordReset['member_user_id'] ?? 0);
            $user = $this->findById($userId);

            if ($user === null) {
                return null;
            }

            return array_merge($passwordReset, [
                'user_full_name' => (string) ($user['full_name'] ?? ''),
                'user_email' => (string) ($user['email'] ?? ''),
                'user_status' => (string) ($user['status'] ?? ''),
            ]);
        }

        return null;
    }

    public function findAllRoles(): array
    {
        return $this->roles;
    }

    public function findRoleByKey(string $roleKey): ?array
    {
        foreach ($this->roles as $role) {
            if ((string) ($role['role_key'] ?? '') === $roleKey) {
                return $role;
            }
        }

        return null;
    }

    public function updateProfile(int $id, array $data): bool
    {
        if (!isset($this->users[$id])) {
            return false;
        }

        $this->users[$id]['full_name'] = trim((string) ($data['full_name'] ?? ''));
        $this->users[$id]['phone_mobile'] = $this->nullableText($data['phone_mobile'] ?? null);
        $this->users[$id]['phone_landline'] = $this->nullableText($data['phone_landline'] ?? null);
        $this->users[$id]['birth_date'] = $this->nullableText($data['birth_date'] ?? null);
        $this->users[$id]['birth_place'] = $this->nullableText($data['birth_place'] ?? null);
        $this->users[$id]['cpf'] = $this->nullableText($data['cpf'] ?? null);
        $this->users[$id]['postal_code'] = $this->nullableText($data['postal_code'] ?? null);
        $this->users[$id]['street_address'] = $this->nullableText($data['street_address'] ?? null);
        $this->users[$id]['address_number'] = $this->nullableText($data['address_number'] ?? null);
        $this->users[$id]['address_complement'] = $this->nullableText($data['address_complement'] ?? null);
        $this->users[$id]['neighborhood'] = $this->nullableText($data['neighborhood'] ?? null);
        $this->users[$id]['address_city'] = $this->nullableText($data['address_city'] ?? null);
        $this->users[$id]['address_state'] = $this->nullableText($data['address_state'] ?? null);
        $this->users[$id]['preferred_due_day'] = ($data['preferred_due_day'] ?? null) !== null
            ? (int) ($data['preferred_due_day'] ?? 0)
            : null;
        $this->users[$id]['contribution_amount'] = ($data['contribution_amount'] ?? null) !== null
            ? (string) ($data['contribution_amount'] ?? '')
            : null;
        $this->users[$id]['contribution_plan_label'] = $this->nullableText($data['contribution_plan_label'] ?? null);
        $this->users[$id]['preferred_payment_method'] = $this->nullableText($data['preferred_payment_method'] ?? null);
        $this->users[$id]['billing_email_opt_in'] = (int) ($data['billing_email_opt_in'] ?? 0);
        $this->users[$id]['billing_whatsapp_opt_in'] = (int) ($data['billing_whatsapp_opt_in'] ?? 0);
        $this->users[$id]['profile_photo_path'] = $this->nullableText($data['profile_photo_path'] ?? null);
        $this->users[$id]['privacy_notice_version'] = $this->nullableText($data['privacy_notice_version'] ?? null);
        $this->users[$id]['privacy_notice_accepted_at'] = $this->nullableText($data['privacy_notice_accepted_at'] ?? null);
        $this->users[$id]['profile_completed'] = (int) ($data['profile_completed'] ?? 0);
        $this->users[$id]['updated_at'] = date('Y-m-d H:i:s');

        return true;
    }

    public function consumePasswordResetToken(int $resetId, int $userId, string $passwordHash): bool
    {
        if (!isset($this->users[$userId], $this->passwordResets[$resetId])) {
            return false;
        }

        $passwordReset = $this->passwordResets[$resetId];
        $now = date('Y-m-d H:i:s');

        if ((int) ($passwordReset['member_user_id'] ?? 0) !== $userId) {
            return false;
        }

        if (($passwordReset['used_at'] ?? null) !== null) {
            return false;
        }

        $expiresAt = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) ($passwordReset['expires_at'] ?? '')
        );

        if (!$expiresAt instanceof \DateTimeImmutable || $expiresAt < new \DateTimeImmutable('now')) {
            return false;
        }

        $this->users[$userId]['password_hash'] = $passwordHash;
        $this->users[$userId]['updated_at'] = $now;

        foreach ($this->passwordResets as &$activePasswordReset) {
            if ((int) ($activePasswordReset['member_user_id'] ?? 0) !== $userId) {
                continue;
            }

            if (($activePasswordReset['used_at'] ?? null) === null) {
                $activePasswordReset['used_at'] = $now;
            }
        }
        unset($activePasswordReset);

        return true;
    }

    public function approveAndAssignRole(
        int $id,
        int $roleId,
        ?string $institutionalRole = null,
        ?string $memberType = null
    ): bool {
        if (!isset($this->users[$id])) {
            return false;
        }

        $role = null;

        foreach ($this->roles as $item) {
            if ((int) ($item['id'] ?? 0) === $roleId) {
                $role = $item;
                break;
            }
        }

        if ($role === null) {
            return false;
        }

        $this->users[$id]['role_id'] = $roleId;
        $this->users[$id]['role_key'] = (string) ($role['role_key'] ?? 'member');
        $this->users[$id]['role_name'] = (string) ($role['name'] ?? 'Membro');
        $this->users[$id]['institutional_role'] = $this->nullableText($institutionalRole);
        $this->users[$id]['member_type'] = $this->nullableText($memberType);
        $this->users[$id]['member_type_label'] = $this->resolveMemberTypeLabel((string) ($this->users[$id]['member_type'] ?? ''));
        $this->users[$id]['status'] = 'active';
        $this->users[$id]['updated_at'] = date('Y-m-d H:i:s');

        return true;
    }

    public function hasActiveInstitutionalRole(string $institutionalRole, int $exceptUserId = 0): bool
    {
        $normalizedRole = trim($institutionalRole);

        if ($normalizedRole === '') {
            return false;
        }

        foreach ($this->users as $user) {
            if ((int) ($user['id'] ?? 0) === $exceptUserId) {
                continue;
            }

            if ((string) ($user['status'] ?? '') !== 'active') {
                continue;
            }

            if ((string) ($user['institutional_role'] ?? '') === $normalizedRole) {
                return true;
            }
        }

        return false;
    }

    public function findAllUsersForAdmin(): array
    {
        return array_values(array_map(
            fn (array $user): array => $this->withMemberTypeLabel($user),
            $this->users
        ));
    }

    public function findContributionMembersByCompetence(string $competence): array
    {
        $normalizedCompetence = $this->normalizeCompetence($competence);
        $today = date('Y-m-d');
        $rows = [];

        foreach ($this->users as $user) {
            if ((string) ($user['status'] ?? '') !== 'active') {
                continue;
            }

            if (trim((string) ($user['member_type'] ?? '')) === '') {
                continue;
            }

            $charge = $this->findContributionChargeForMemberAndCompetence(
                (int) ($user['id'] ?? 0),
                $normalizedCompetence
            );

            $overdueChargeCount = 0;
            $oldestOverdueDueDate = null;
            $lastPaidAt = null;

            foreach ($this->contributionCharges as $existingCharge) {
                if ((int) ($existingCharge['member_user_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
                    continue;
                }

                $chargeStatus = (string) ($existingCharge['status'] ?? '');
                $dueDate = (string) ($existingCharge['due_date'] ?? '');

                if ($chargeStatus === 'pending' && $dueDate !== '' && $dueDate < $today) {
                    $overdueChargeCount++;
                    if ($oldestOverdueDueDate === null || $dueDate < $oldestOverdueDueDate) {
                        $oldestOverdueDueDate = $dueDate;
                    }
                }

                $paidAt = trim((string) ($existingCharge['paid_at'] ?? ''));
                if ($chargeStatus === 'paid' && $paidAt !== '' && ($lastPaidAt === null || $paidAt > $lastPaidAt)) {
                    $lastPaidAt = $paidAt;
                }
            }

            $rows[] = array_merge($this->withMemberTypeLabel($user), [
                'charge_id' => $charge['id'] ?? null,
                'charge_competence' => $charge['competence'] ?? $normalizedCompetence,
                'charge_due_date' => $charge['due_date'] ?? null,
                'charge_amount_due' => $charge['amount_due'] ?? null,
                'charge_status' => $charge['status'] ?? null,
                'charge_preferred_payment_method' => $charge['preferred_payment_method'] ?? null,
                'charge_payment_recorded_method' => $charge['payment_recorded_method'] ?? null,
                'charge_paid_at' => $charge['paid_at'] ?? null,
                'charge_exemption_reason' => $charge['exemption_reason'] ?? null,
                'charge_gateway_provider' => $charge['gateway_provider'] ?? null,
                'charge_gateway_customer_id' => $charge['gateway_customer_id'] ?? null,
                'charge_gateway_payment_id' => $charge['gateway_payment_id'] ?? null,
                'charge_gateway_billing_type' => $charge['gateway_billing_type'] ?? null,
                'charge_gateway_status' => $charge['gateway_status'] ?? null,
                'charge_gateway_invoice_url' => $charge['gateway_invoice_url'] ?? null,
                'charge_gateway_bank_slip_url' => $charge['gateway_bank_slip_url'] ?? null,
                'charge_gateway_transaction_receipt_url' => $charge['gateway_transaction_receipt_url'] ?? null,
                'charge_gateway_pix_payload' => $charge['gateway_pix_payload'] ?? null,
                'charge_gateway_pix_encoded_image' => $charge['gateway_pix_encoded_image'] ?? null,
                'charge_gateway_pix_expiration_date' => $charge['gateway_pix_expiration_date'] ?? null,
                'charge_gateway_last_synced_at' => $charge['gateway_last_synced_at'] ?? null,
                'overdue_charge_count' => $overdueChargeCount,
                'oldest_overdue_due_date' => $oldestOverdueDueDate,
                'last_paid_at' => $lastPaidAt,
            ]);
        }

        usort($rows, static function (array $first, array $second): int {
            return strnatcasecmp((string) ($first['full_name'] ?? ''), (string) ($second['full_name'] ?? ''));
        });

        return $rows;
    }

    public function generateContributionCharges(string $competence, ?int $generatedByUserId = null): array
    {
        $normalizedCompetence = $this->normalizeCompetence($competence);
        $created = 0;
        $skippedExisting = 0;
        $skippedIncompleteProfile = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($this->users as $user) {
            if ((string) ($user['status'] ?? '') !== 'active') {
                continue;
            }

            if (trim((string) ($user['member_type'] ?? '')) === '') {
                continue;
            }

            $userId = (int) ($user['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if ($this->findContributionChargeForMemberAndCompetence($userId, $normalizedCompetence) !== null) {
                $skippedExisting++;
                continue;
            }

            $amountDue = is_numeric((string) ($user['contribution_amount'] ?? null))
                ? (float) ($user['contribution_amount'] ?? 0)
                : 0.0;
            $preferredDueDay = (int) ($user['preferred_due_day'] ?? 0);

            if ($amountDue <= 0 || $preferredDueDay < 1 || $preferredDueDay > 28) {
                $skippedIncompleteProfile++;
                continue;
            }

            $chargeId = $this->nextContributionChargeId++;
            $dueDate = sprintf('%s-%02d', $normalizedCompetence, $preferredDueDay);
            $preferredPaymentMethod = $this->resolveContributionPaymentMethod(
                (string) ($user['preferred_payment_method'] ?? '')
            );

            $this->contributionCharges[$chargeId] = [
                'id' => $chargeId,
                'member_user_id' => $userId,
                'competence' => $normalizedCompetence,
                'due_date' => $dueDate,
                'amount_due' => number_format($amountDue, 2, '.', ''),
                'status' => 'pending',
                'preferred_payment_method' => $preferredPaymentMethod,
                'payment_recorded_method' => null,
                'paid_at' => null,
                'exemption_reason' => null,
                'gateway_provider' => null,
                'gateway_customer_id' => null,
                'gateway_payment_id' => null,
                'gateway_billing_type' => null,
                'gateway_status' => null,
                'gateway_invoice_url' => null,
                'gateway_bank_slip_url' => null,
                'gateway_transaction_receipt_url' => null,
                'gateway_pix_payload' => null,
                'gateway_pix_encoded_image' => null,
                'gateway_pix_expiration_date' => null,
                'gateway_last_synced_at' => null,
                'generated_by_user_id' => $generatedByUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $this->appendContributionEvent(
                $chargeId,
                $userId,
                'generated',
                'Cobrança mensal gerada automaticamente.',
                $generatedByUserId
            );

            $created++;
        }

        return [
            'created' => $created,
            'skipped_existing' => $skippedExisting,
            'skipped_incomplete_profile' => $skippedIncompleteProfile,
        ];
    }

    public function findContributionChargeById(int $chargeId): ?array
    {
        $charge = $this->contributionCharges[$chargeId] ?? null;
        if (!is_array($charge)) {
            return null;
        }

        $user = $this->findById((int) ($charge['member_user_id'] ?? 0));
        if ($user === null) {
            return $charge;
        }

        return array_merge($charge, [
            'member_full_name' => (string) ($user['full_name'] ?? ''),
            'member_email' => (string) ($user['email'] ?? ''),
        ]);
    }

    public function markContributionChargeAsPaid(
        int $chargeId,
        string $paymentMethod,
        ?int $actedByUserId = null
    ): bool {
        if (!isset($this->contributionCharges[$chargeId])) {
            return false;
        }

        $charge = $this->contributionCharges[$chargeId];
        if ((string) ($charge['status'] ?? '') !== 'pending') {
            return false;
        }

        $paymentMethod = $this->resolveContributionPaymentMethod($paymentMethod);
        $now = date('Y-m-d H:i:s');

        $this->contributionCharges[$chargeId]['status'] = 'paid';
        $this->contributionCharges[$chargeId]['payment_recorded_method'] = $paymentMethod;
        $this->contributionCharges[$chargeId]['paid_at'] = $now;
        $this->contributionCharges[$chargeId]['updated_at'] = $now;

        $this->appendContributionEvent(
            $chargeId,
            (int) ($charge['member_user_id'] ?? 0),
            'paid',
            'Cobrança baixada manualmente como paga.',
            $actedByUserId,
            ['payment_method' => $paymentMethod]
        );

        return true;
    }

    public function markContributionChargeAsExempt(
        int $chargeId,
        ?string $reason = null,
        ?int $actedByUserId = null
    ): bool {
        if (!isset($this->contributionCharges[$chargeId])) {
            return false;
        }

        $charge = $this->contributionCharges[$chargeId];
        if ((string) ($charge['status'] ?? '') !== 'pending') {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $normalizedReason = $this->nullableText($reason);

        $this->contributionCharges[$chargeId]['status'] = 'exempt';
        $this->contributionCharges[$chargeId]['exemption_reason'] = $normalizedReason;
        $this->contributionCharges[$chargeId]['updated_at'] = $now;

        $this->appendContributionEvent(
            $chargeId,
            (int) ($charge['member_user_id'] ?? 0),
            'exempt',
            'Cobrança marcada como isenta.',
            $actedByUserId,
            ['reason' => $normalizedReason]
        );

        return true;
    }

    public function registerContributionReminderEvent(
        int $chargeId,
        string $channel,
        ?int $actedByUserId = null,
        array $payload = []
    ): bool {
        if (!isset($this->contributionCharges[$chargeId])) {
            return false;
        }

        $charge = $this->contributionCharges[$chargeId];
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
    }

    public function updateContributionGatewayData(int $chargeId, array $data): bool
    {
        if (!isset($this->contributionCharges[$chargeId])) {
            return false;
        }

        $allowedFields = [
            'gateway_provider',
            'gateway_customer_id',
            'gateway_payment_id',
            'gateway_billing_type',
            'gateway_status',
            'gateway_invoice_url',
            'gateway_bank_slip_url',
            'gateway_transaction_receipt_url',
            'gateway_pix_payload',
            'gateway_pix_encoded_image',
            'gateway_pix_expiration_date',
            'gateway_last_synced_at',
        ];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $this->contributionCharges[$chargeId][$field] = $data[$field];
        }

        $this->contributionCharges[$chargeId]['updated_at'] = date('Y-m-d H:i:s');

        return true;
    }

    public function findContributionChargeByGatewayPaymentId(string $gatewayPaymentId): ?array
    {
        $normalizedPaymentId = trim($gatewayPaymentId);
        if ($normalizedPaymentId === '') {
            return null;
        }

        foreach ($this->contributionCharges as $charge) {
            if (trim((string) ($charge['gateway_payment_id'] ?? '')) !== $normalizedPaymentId) {
                continue;
            }

            $chargeId = (int) ($charge['id'] ?? 0);

            return $chargeId > 0 ? $this->findContributionChargeById($chargeId) : $charge;
        }

        return null;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function withMemberTypeLabel(array $user): array
    {
        $user['member_type'] = $user['member_type'] ?? null;
        $user['member_type_label'] = $this->resolveMemberTypeLabel((string) ($user['member_type'] ?? ''));
        $user['privacy_notice_version'] = $user['privacy_notice_version'] ?? null;
        $user['privacy_notice_accepted_at'] = $user['privacy_notice_accepted_at'] ?? null;

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findContributionChargeForMemberAndCompetence(int $userId, string $competence): ?array
    {
        foreach ($this->contributionCharges as $charge) {
            if ((int) ($charge['member_user_id'] ?? 0) !== $userId) {
                continue;
            }

            if ((string) ($charge['competence'] ?? '') !== $competence) {
                continue;
            }

            return $charge;
        }

        return null;
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
        $eventId = $this->nextContributionEventId++;
        $this->contributionEvents[$eventId] = [
            'id' => $eventId,
            'charge_id' => $chargeId,
            'member_user_id' => $memberUserId,
            'event_type' => $eventType,
            'event_description' => $eventDescription,
            'acted_by_user_id' => $actedByUserId,
            'payload_json' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function resolveMemberTypeLabel(string $memberType): string
    {
        return match (strtolower(trim($memberType))) {
            'fundador' => 'Fundador',
            'efetivo' => 'Efetivo',
            default => 'Não definido',
        };
    }
}
