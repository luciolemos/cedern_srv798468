<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Agenda;

use App\Domain\Agenda\AgendaRepository;

class MySqlAgendaRepository implements AgendaRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findUpcomingPublished(int $limit = 20): array
    {
        $sql = <<<SQL
            SELECT
                e.id,
                e.slug,
                e.title,
                e.description,
                e.theme,
                e.location_name,
                e.location_address,
                e.mode,
                e.meeting_url,
                e.audience,
                e.notes,
                e.starts_at,
                e.ends_at,
                e.status,
                e.is_featured,
                c.slug AS category_slug,
                c.name AS category_name,
                c.color AS category_color
            FROM agenda_events e
            INNER JOIN activity_categories c ON c.id = e.category_id
            WHERE e.status = 'published'
              AND c.is_active = 1
            ORDER BY e.starts_at ASC
            LIMIT :limit
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map([$this, 'normalizeEvent'], $statement->fetchAll() ?: []);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
                $sql = $this->buildBaseSelect() . <<<SQL
                        WHERE e.slug = :slug
                            AND e.status = 'published'
                            AND c.is_active = 1
                        LIMIT 1
                SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':slug', $slug, \PDO::PARAM_STR);
        $statement->execute();

        $event = $statement->fetch();

        if (!$event) {
            return null;
        }

        return $this->normalizeEvent($event);
    }

    public function findAllForAdmin(): array
    {
        $sql = $this->buildBaseSelect() . <<<SQL
            ORDER BY e.starts_at ASC
        SQL;

        $statement = $this->pdo->query($sql);

        return array_map([$this, 'normalizeEvent'], $statement->fetchAll() ?: []);
    }

    public function findByIdForAdmin(int $id): ?array
    {
        $sql = $this->buildBaseSelect() . <<<SQL
            WHERE e.id = :id
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $event = $statement->fetch();

        if (!$event) {
            return null;
        }

        return $this->normalizeEvent($event);
    }

    public function findActiveCategories(): array
    {
        $sql = <<<SQL
            SELECT id, slug, name
            FROM activity_categories
            WHERE is_active = 1
            ORDER BY name ASC
        SQL;

        $statement = $this->pdo->query($sql);

        return $statement->fetchAll() ?: [];
    }

    public function findAllCategoriesForAdmin(): array
    {
        $sql = <<<SQL
            SELECT
                id,
                slug,
                name,
                color,
                icon,
                audience_default,
                is_active,
                created_at,
                updated_at
            FROM activity_categories
            ORDER BY name ASC
        SQL;

        $statement = $this->pdo->query($sql);

        return $statement->fetchAll() ?: [];
    }

    public function findCategoryByIdForAdmin(int $id): ?array
    {
        $sql = <<<SQL
            SELECT
                id,
                slug,
                name,
                color,
                icon,
                audience_default,
                is_active
            FROM activity_categories
            WHERE id = :id
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $category = $statement->fetch();

        if (!$category) {
            return null;
        }

        return $category;
    }

    public function createCategory(array $data): int
    {
        $sql = <<<SQL
            INSERT INTO activity_categories (
                slug,
                name,
                color,
                icon,
                audience_default,
                is_active
            ) VALUES (
                :slug,
                :name,
                :color,
                :icon,
                :audience_default,
                :is_active
            )
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($this->buildCategoryWriteParams($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategory(int $id, array $data): bool
    {
        $sql = <<<SQL
            UPDATE activity_categories
            SET
                slug = :slug,
                name = :name,
                color = :color,
                icon = :icon,
                audience_default = :audience_default,
                is_active = :is_active
            WHERE id = :id
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);
        $params = $this->buildCategoryWriteParams($data);
        $params['id'] = $id;

        return $statement->execute($params);
    }

    public function setCategoryActive(int $id, bool $isActive): bool
    {
        $sql = <<<SQL
            UPDATE activity_categories
            SET is_active = :is_active
            WHERE id = :id
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);

        return $statement->execute([
            'id' => $id,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public function createEvent(array $data): int
    {
        $sql = <<<SQL
            INSERT INTO agenda_events (
                category_id,
                slug,
                title,
                description,
                theme,
                location_name,
                location_address,
                mode,
                meeting_url,
                audience,
                notes,
                starts_at,
                ends_at,
                status,
                is_featured
            ) VALUES (
                :category_id,
                :slug,
                :title,
                :description,
                :theme,
                :location_name,
                :location_address,
                :mode,
                :meeting_url,
                :audience,
                :notes,
                :starts_at,
                :ends_at,
                :status,
                :is_featured
            )
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($this->buildWriteParams($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function updateEvent(int $id, array $data): bool
    {
        $sql = <<<SQL
            UPDATE agenda_events
            SET
                category_id = :category_id,
                slug = :slug,
                title = :title,
                description = :description,
                theme = :theme,
                location_name = :location_name,
                location_address = :location_address,
                mode = :mode,
                meeting_url = :meeting_url,
                audience = :audience,
                notes = :notes,
                starts_at = :starts_at,
                ends_at = :ends_at,
                status = :status,
                is_featured = :is_featured
            WHERE id = :id
            LIMIT 1
        SQL;

        $statement = $this->pdo->prepare($sql);
        $params = $this->buildWriteParams($data);
        $params['id'] = $id;

        return $statement->execute($params);
    }

    public function deleteEvent(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM agenda_events WHERE id = :id LIMIT 1');

        return $statement->execute(['id' => $id]);
    }

    private function buildBaseSelect(): string
    {
        return <<<SQL
            SELECT
                e.id,
                e.category_id,
                e.slug,
                e.title,
                e.description,
                e.theme,
                e.location_name,
                e.location_address,
                e.mode,
                e.meeting_url,
                e.audience,
                e.notes,
                e.starts_at,
                e.ends_at,
                e.status,
                e.is_featured,
                c.slug AS category_slug,
                c.name AS category_name,
                c.color AS category_color
            FROM agenda_events e
            INNER JOIN activity_categories c ON c.id = e.category_id
        SQL;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildWriteParams(array $data): array
    {
        return [
            'category_id' => (int) ($data['category_id'] ?? 0),
            'slug' => (string) ($data['slug'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'description' => $this->nullableText($data['description'] ?? null),
            'theme' => $this->nullableText($data['theme'] ?? null),
            'location_name' => $this->nullableText($data['location_name'] ?? null),
            'location_address' => $this->nullableText($data['location_address'] ?? null),
            'mode' => (string) ($data['mode'] ?? 'presencial'),
            'meeting_url' => $this->nullableText($data['meeting_url'] ?? null),
            'audience' => $this->nullableText($data['audience'] ?? null),
            'notes' => $this->nullableText($data['notes'] ?? null),
            'starts_at' => (string) ($data['starts_at'] ?? ''),
            'ends_at' => $this->nullableText($data['ends_at'] ?? null),
            'status' => (string) ($data['status'] ?? 'draft'),
            'is_featured' => (int) ($data['is_featured'] ?? 0),
        ];
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
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildCategoryWriteParams(array $data): array
    {
        return [
            'slug' => (string) ($data['slug'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'color' => $this->nullableText($data['color'] ?? null),
            'icon' => $this->nullableText($data['icon'] ?? null),
            'audience_default' => $this->nullableText($data['audience_default'] ?? null),
            'is_active' => (int) ($data['is_active'] ?? 1),
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function normalizeEvent(array $event): array
    {
        $startsAt = (string) ($event['starts_at'] ?? '');
        $endsAt = (string) ($event['ends_at'] ?? '');

        return array_merge($event, [
            'starts_at_label' => $this->formatDateTimeLabel($startsAt),
            'ends_at_label' => $this->formatDateTimeLabel($endsAt),
            'mode_label' => $this->formatModeLabel((string) ($event['mode'] ?? 'presencial')),
        ]);
    }

    private function formatDateTimeLabel(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $dateTime = new \DateTimeImmutable($value);
            return $dateTime->format('d/m/Y H:i');
        } catch (\Throwable $exception) {
            return $value;
        }
    }

    private function formatModeLabel(string $mode): string
    {
        $map = [
            'presencial' => 'Presencial',
            'online' => 'Online',
            'hibrido' => 'Híbrido',
        ];

        return $map[$mode] ?? ucfirst($mode);
    }
}
