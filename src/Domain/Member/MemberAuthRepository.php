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
        ?string $memberType = null
    ): bool;

    public function hasActiveInstitutionalRole(string $institutionalRole, int $exceptUserId = 0): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllUsersForAdmin(): array;
}
