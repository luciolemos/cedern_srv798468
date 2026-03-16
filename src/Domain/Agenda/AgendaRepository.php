<?php

declare(strict_types=1);

namespace App\Domain\Agenda;

interface AgendaRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function findUpcomingPublished(int $limit = 20): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findUpcomingPublishedPage(int $limit, int $offset): array;

    public function countUpcomingPublished(): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findInterestedUpcomingByMember(int $memberId, int $limit = 10): array;

    /**
     * @return array<int, int>
     */
    public function listInterestedEventIdsByMember(int $memberId): array;

    public function setMemberEventInterest(int $memberId, int $eventId, bool $interested): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedBySlug(string $slug): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForAdmin(int $id): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveCategories(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllCategoriesForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findCategoryByIdForAdmin(int $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function createCategory(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function updateCategory(int $id, array $data): bool;

    public function setCategoryActive(int $id, bool $isActive): bool;

    /**
     * @param array<string, mixed> $data
     */
    public function createEvent(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function updateEvent(int $id, array $data): bool;

    public function deleteEvent(int $id): bool;
}
