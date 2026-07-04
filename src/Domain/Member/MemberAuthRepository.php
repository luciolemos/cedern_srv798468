<?php

declare(strict_types=1);

namespace App\Domain\Member;

interface MemberAuthRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function createPendingUser(array $data): int;

    public function createPasswordResetToken(
        int $userId,
        string $email,
        string $tokenHash,
        \DateTimeImmutable $expiresAt
    ): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByCpf(string $cpf, int $exceptUserId = 0): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findActivePasswordResetByToken(string $tokenHash): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllRoles(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findRoleByKey(string $roleKey): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function updateProfile(int $id, array $data): bool;

    public function consumePasswordResetToken(int $resetId, int $userId, string $passwordHash): bool;

    public function approveAndAssignRole(
        int $id,
        int $roleId,
        ?string $institutionalRole = null,
        ?string $memberType = null,
        ?string $associationStatus = null,
        ?bool $isContributor = null,
        ?string $accountStatus = null,
        ?int $actedByUserId = null
    ): bool;

    public function hasActiveInstitutionalRole(string $institutionalRole, int $exceptUserId = 0): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllUsersForAdmin(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findUserAdministrationHistory(int $userId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findContributionMembersByCompetence(string $competence): array;

    /**
     * @return array{created: int, skipped_existing: int, skipped_incomplete_profile: int}
     */
    public function generateContributionCharges(string $competence, ?int $generatedByUserId = null): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findContributionChargeById(int $chargeId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findContributionChargesByMember(int $memberUserId, int $limit = 12): array;

    public function markContributionChargeAsPaid(
        int $chargeId,
        string $paymentMethod,
        ?int $actedByUserId = null
    ): bool;

    public function markContributionChargeAsExempt(
        int $chargeId,
        ?string $reason = null,
        ?int $actedByUserId = null
    ): bool;

    /**
     * @param array<string, mixed> $payload
     */
    public function registerContributionReminderEvent(
        int $chargeId,
        string $channel,
        ?int $actedByUserId = null,
        array $payload = []
    ): bool;

    /**
     * @param array<string, mixed> $data
     */
    public function updateContributionGatewayData(int $chargeId, array $data): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function findContributionChargeByGatewayPaymentId(string $gatewayPaymentId): ?array;
}
