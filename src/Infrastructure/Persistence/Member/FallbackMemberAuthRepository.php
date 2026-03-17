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
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $users = [];

    private int $nextId = 1;

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
            'institutional_role' => null,
            'profile_photo_path' => null,
            'profile_completed' => 0,
            'role_id' => null,
            'role_key' => null,
            'role_name' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $id;
    }

    public function findByEmail(string $email): ?array
    {
        $needle = strtolower(trim($email));

        foreach ($this->users as $user) {
            if ((string) ($user['email'] ?? '') === $needle) {
                return $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        return $this->users[$id] ?? null;
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
        $this->users[$id]['profile_photo_path'] = $this->nullableText($data['profile_photo_path'] ?? null);
        $this->users[$id]['profile_completed'] = (int) ($data['profile_completed'] ?? 0);
        $this->users[$id]['updated_at'] = date('Y-m-d H:i:s');

        return true;
    }

    public function approveAndAssignRole(int $id, int $roleId, ?string $institutionalRole = null): bool
    {
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
        return array_values($this->users);
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
