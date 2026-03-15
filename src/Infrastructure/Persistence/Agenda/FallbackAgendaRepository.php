<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Agenda;

use App\Domain\Agenda\AgendaRepository;

class FallbackAgendaRepository implements AgendaRepository
{
    public function findUpcomingPublished(int $limit = 20): array
    {
        return [];
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return null;
    }

    public function findAllForAdmin(): array
    {
        return [];
    }

    public function findByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function findActiveCategories(): array
    {
        return [];
    }

    public function findAllCategoriesForAdmin(): array
    {
        return [];
    }

    public function findCategoryByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function createCategory(array $data): int
    {
        return 0;
    }

    public function updateCategory(int $id, array $data): bool
    {
        return false;
    }

    public function setCategoryActive(int $id, bool $isActive): bool
    {
        return false;
    }

    public function createEvent(array $data): int
    {
        return 0;
    }

    public function updateEvent(int $id, array $data): bool
    {
        return false;
    }

    public function deleteEvent(int $id): bool
    {
        return false;
    }
}
