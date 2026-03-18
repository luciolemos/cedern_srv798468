<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

interface SiteVisitRepository
{
    public function registerPageVisit(string $pageKey, string $visitorTokenHash, \DateTimeImmutable $visitedAt): void;

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
    public function getDashboardSummary(): array;

    public function startNewCountingPeriod(?int $memberId = null, ?\DateTimeImmutable $startedAt = null): void;
}
