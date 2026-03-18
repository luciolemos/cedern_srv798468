<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Analytics;

use App\Domain\Analytics\SiteVisitRepository;

class MySqlSiteVisitRepository implements SiteVisitRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function registerPageVisit(string $pageKey, string $visitorTokenHash, \DateTimeImmutable $visitedAt): void
    {
        $normalizedPageKey = $this->normalizePageKey($pageKey);
        $normalizedVisitorTokenHash = trim($visitorTokenHash);

        if ($normalizedPageKey === '' || $normalizedVisitorTokenHash === '') {
            return;
        }

        $visitDate = $visitedAt->format('Y-m-d');

        try {
            $this->persistPageVisit($normalizedPageKey, $normalizedVisitorTokenHash, $visitDate);
        } catch (\Throwable $exception) {
            $this->ensureSchemaCompatibility();
            $this->persistPageVisit($normalizedPageKey, $normalizedVisitorTokenHash, $visitDate);
        }
    }

    public function getDashboardSummary(): array
    {
        try {
            return $this->loadDashboardSummary();
        } catch (\Throwable $exception) {
            $this->ensureSchemaCompatibility();

            return $this->loadDashboardSummary();
        }
    }

    public function startNewCountingPeriod(?int $memberId = null, ?\DateTimeImmutable $startedAt = null): void
    {
        $resolvedStartedAt = ($startedAt ?? new \DateTimeImmutable('today'))->format('Y-m-d H:i:s');

        try {
            $this->persistBaseline($memberId, $resolvedStartedAt);
        } catch (\Throwable $exception) {
            $this->ensureSchemaCompatibility();
            $this->persistBaseline($memberId, $resolvedStartedAt);
        }
    }

    private function persistPageVisit(string $pageKey, string $visitorTokenHash, string $visitDate): void
    {
        $this->pdo->beginTransaction();

        try {
            $totalsStatement = $this->pdo->prepare(<<<SQL
                INSERT INTO page_visit_daily (
                    page_key,
                    visit_date,
                    page_views,
                    unique_visitors
                ) VALUES (
                    :page_key,
                    :visit_date,
                    1,
                    0
                )
                ON DUPLICATE KEY UPDATE
                    page_views = page_views + 1,
                    updated_at = CURRENT_TIMESTAMP
            SQL);
            $totalsStatement->execute([
                'page_key' => $pageKey,
                'visit_date' => $visitDate,
            ]);

            $uniqueStatement = $this->pdo->prepare(<<<SQL
                INSERT IGNORE INTO page_visit_uniques (
                    page_key,
                    visit_date,
                    visitor_token_hash
                ) VALUES (
                    :page_key,
                    :visit_date,
                    :visitor_token_hash
                )
            SQL);
            $uniqueStatement->execute([
                'page_key' => $pageKey,
                'visit_date' => $visitDate,
                'visitor_token_hash' => $visitorTokenHash,
            ]);

            if ($uniqueStatement->rowCount() > 0) {
                $incrementUniqueStatement = $this->pdo->prepare(<<<SQL
                    UPDATE page_visit_daily
                    SET unique_visitors = unique_visitors + 1
                    WHERE page_key = :page_key
                      AND visit_date = :visit_date
                    LIMIT 1
                SQL);
                $incrementUniqueStatement->execute([
                    'page_key' => $pageKey,
                    'visit_date' => $visitDate,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array{
     *     baseline_started_at: string|null,
     *     total_views: int,
     *     total_unique_visitors: int,
     *     today_views: int,
     *     today_unique_visitors: int,
     *     last_7_days_views: int,
     *     last_7_days_unique_visitors: int,
     *     top_pages: array<int, array{
     *         page_key: string,
     *         page_views: int,
     *         unique_visitors: int
     *     }>
     * }
     */
    private function loadDashboardSummary(): array
    {
        $today = new \DateTimeImmutable('today');
        $todayDate = $today->format('Y-m-d');
        $last7StartDate = $today->modify('-6 days')->format('Y-m-d');
        $baselineStartedAt = $this->getCurrentBaselineStartedAt();
        $baselineDate = $baselineStartedAt !== null
            ? (new \DateTimeImmutable($baselineStartedAt))->format('Y-m-d')
            : null;

        $todayTotals = $this->fetchAggregateTotals(
            'visit_date = :visit_date',
            ['visit_date' => $todayDate]
        );
        $last7Totals = $this->fetchAggregateTotals(
            'visit_date >= :visit_date_start',
            ['visit_date_start' => $last7StartDate]
        );

        $totalWhere = '1 = 1';
        $totalParams = [];
        if ($baselineDate !== null) {
            $totalWhere = 'visit_date >= :baseline_date';
            $totalParams['baseline_date'] = $baselineDate;
        }

        $totalTotals = $this->fetchAggregateTotals($totalWhere, $totalParams);

        $topPagesStatement = $this->pdo->prepare(<<<SQL
            SELECT
                page_key,
                SUM(page_views) AS page_views,
                SUM(unique_visitors) AS unique_visitors
            FROM page_visit_daily
            WHERE {$totalWhere}
            GROUP BY page_key
            ORDER BY page_views DESC, unique_visitors DESC, page_key ASC
            LIMIT 5
        SQL);
        $topPagesStatement->execute($totalParams);
        $topPageRows = $topPagesStatement->fetchAll() ?: [];

        $topPages = array_map(
            static fn (array $row): array => [
                'page_key' => (string) ($row['page_key'] ?? '/'),
                'page_views' => (int) ($row['page_views'] ?? 0),
                'unique_visitors' => (int) ($row['unique_visitors'] ?? 0),
            ],
            $topPageRows
        );

        return [
            'baseline_started_at' => $baselineStartedAt,
            'total_views' => $totalTotals['page_views'],
            'total_unique_visitors' => $totalTotals['unique_visitors'],
            'today_views' => $todayTotals['page_views'],
            'today_unique_visitors' => $todayTotals['unique_visitors'],
            'last_7_days_views' => $last7Totals['page_views'],
            'last_7_days_unique_visitors' => $last7Totals['unique_visitors'],
            'top_pages' => $topPages,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{page_views: int, unique_visitors: int}
     */
    private function fetchAggregateTotals(string $whereSql, array $params): array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT
                COALESCE(SUM(page_views), 0) AS page_views,
                COALESCE(SUM(unique_visitors), 0) AS unique_visitors
            FROM page_visit_daily
            WHERE {$whereSql}
        SQL);
        $statement->execute($params);
        $row = $statement->fetch();

        return [
            'page_views' => (int) ($row['page_views'] ?? 0),
            'unique_visitors' => (int) ($row['unique_visitors'] ?? 0),
        ];
    }

    private function getCurrentBaselineStartedAt(): ?string
    {
        $statement = $this->pdo->query(<<<SQL
            SELECT started_at
            FROM page_visit_baselines
            ORDER BY started_at DESC, id DESC
            LIMIT 1
        SQL);
        $row = $statement->fetch();
        $value = trim((string) ($row['started_at'] ?? ''));

        return $value === '' ? null : $value;
    }

    private function persistBaseline(?int $memberId, string $startedAt): void
    {
        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO page_visit_baselines (
                started_at,
                created_by_member_id
            ) VALUES (
                :started_at,
                :created_by_member_id
            )
        SQL);
        $statement->execute([
            'started_at' => $startedAt,
            'created_by_member_id' => $memberId > 0 ? $memberId : null,
        ]);
    }

    private function normalizePageKey(string $pageKey): string
    {
        $normalized = trim($pageKey);

        return $normalized === '' ? '' : $normalized;
    }

    private function ensureSchemaCompatibility(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS page_visit_daily (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                page_key VARCHAR(190) NOT NULL,
                visit_date DATE NOT NULL,
                page_views INT UNSIGNED NOT NULL DEFAULT 0,
                unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_page_visit_daily (page_key, visit_date),
                KEY idx_page_visit_daily_date (visit_date),
                KEY idx_page_visit_daily_page (page_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS page_visit_uniques (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                page_key VARCHAR(190) NOT NULL,
                visit_date DATE NOT NULL,
                visitor_token_hash CHAR(64) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_page_visit_uniques (page_key, visit_date, visitor_token_hash),
                KEY idx_page_visit_uniques_date (visit_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS page_visit_baselines (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                started_at DATETIME NOT NULL,
                created_by_member_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_page_visit_baselines_started_at (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }
}
