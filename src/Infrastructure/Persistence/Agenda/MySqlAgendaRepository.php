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

    public function findUpcomingPublishedPage(int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);

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
            LIMIT :limit OFFSET :offset
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return array_map([$this, 'normalizeEvent'], $statement->fetchAll() ?: []);
    }

    public function countUpcomingPublished(): int
    {
        $sql = <<<SQL
            SELECT COUNT(*)
            FROM agenda_events e
            INNER JOIN activity_categories c ON c.id = e.category_id
            WHERE e.status = 'published'
              AND c.is_active = 1
        SQL;

        $statement = $this->pdo->query($sql);
        $total = $statement !== false ? $statement->fetchColumn() : 0;

        return max(0, (int) $total);
    }

    public function findInterestedUpcomingByMember(int $memberId, int $limit = 10): array
    {
        if ($memberId <= 0) {
            return [];
        }

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
            FROM member_event_interests i
            INNER JOIN agenda_events e ON e.id = i.event_id
            INNER JOIN activity_categories c ON c.id = e.category_id
            WHERE i.member_user_id = :member_id
              AND e.status = 'published'
              AND c.is_active = 1
            ORDER BY e.starts_at ASC
            LIMIT :limit
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':member_id', $memberId, \PDO::PARAM_INT);
            $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $statement->execute();

            return array_map([$this, 'normalizeEvent'], $statement->fetchAll() ?: []);
        } catch (\Throwable $exception) {
            $this->ensureMemberInterestSchemaCompatibility();

            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':member_id', $memberId, \PDO::PARAM_INT);
            $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $statement->execute();

            return array_map([$this, 'normalizeEvent'], $statement->fetchAll() ?: []);
        }
    }

    public function listInterestedEventIdsByMember(int $memberId): array
    {
        if ($memberId <= 0) {
            return [];
        }

        $sql = <<<SQL
            SELECT event_id
            FROM member_event_interests
            WHERE member_user_id = :member_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':member_id', $memberId, \PDO::PARAM_INT);
            $statement->execute();

            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $exception) {
            $this->ensureMemberInterestSchemaCompatibility();

            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':member_id', $memberId, \PDO::PARAM_INT);
            $statement->execute();

            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        return array_values(array_map(
            static fn (array $row): int => (int) ($row['event_id'] ?? 0),
            $rows
        ));
    }

    public function setMemberEventInterest(int $memberId, int $eventId, bool $interested): bool
    {
        if ($memberId <= 0 || $eventId <= 0) {
            return false;
        }

        $write = function () use ($memberId, $eventId, $interested): bool {
            if ($interested) {
                $sql = <<<SQL
                    INSERT INTO member_event_interests (member_user_id, event_id)
                    VALUES (:member_id, :event_id)
                    ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
                SQL;

                $statement = $this->pdo->prepare($sql);

                return $statement->execute([
                    'member_id' => $memberId,
                    'event_id' => $eventId,
                ]);
            }

            $sql = <<<SQL
                DELETE FROM member_event_interests
                WHERE member_user_id = :member_id
                  AND event_id = :event_id
                LIMIT 1
            SQL;

            $statement = $this->pdo->prepare($sql);

            return $statement->execute([
                'member_id' => $memberId,
                'event_id' => $eventId,
            ]);
        };

        try {
            return $write();
        } catch (\Throwable $exception) {
            $this->ensureMemberInterestSchemaCompatibility();

            return $write();
        }
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
            'google_calendar_url' => $this->buildGoogleCalendarUrl($event),
        ]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function buildGoogleCalendarUrl(array $event): string
    {
        $title = trim((string) ($event['title'] ?? 'Atividade do CEDE'));
        $details = trim((string) ($event['description'] ?? ''));
        $location = trim((string) ($event['location_name'] ?? ''));

        $startsAt = $this->parseDateTime((string) ($event['starts_at'] ?? ''));
        $endsAt = $this->parseDateTime((string) ($event['ends_at'] ?? ''));

        if ($startsAt === null) {
            return '';
        }

        if ($endsAt === null) {
            $endsAt = $startsAt->modify('+90 minutes');
        }

        $dates = $startsAt->format('Ymd\\THis') . '/' . $endsAt->format('Ymd\\THis');

        $query = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $dates,
            'details' => $details,
            'location' => $location,
        ]);

        return 'https://calendar.google.com/calendar/render?' . $query;
    }

    private function parseDateTime(string $value): ?\DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $exception) {
            return null;
        }
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

    private function ensureMemberInterestSchemaCompatibility(): void
    {
        $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS member_event_interests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_user_id BIGINT UNSIGNED NOT NULL,
                event_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_member_event (member_user_id, event_id),
                KEY idx_member_user_id (member_user_id),
                KEY idx_event_id (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL;

        $this->pdo->exec($sql);
    }
}
