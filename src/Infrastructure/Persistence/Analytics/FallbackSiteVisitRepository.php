<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Analytics;

use App\Domain\Analytics\SiteVisitRepository;

class FallbackSiteVisitRepository implements SiteVisitRepository
{
    /**
     * @var array<string, array{page_views: int, unique_visitors: int}>
     */
    private array $dailyTotals = [];

    /**
     * @var array<string, bool>
     */
    private array $dailyUniqueKeys = [];

    private ?string $baselineStartedAt = null;

    public function registerPageVisit(string $pageKey, string $visitorTokenHash, \DateTimeImmutable $visitedAt): void
    {
        $normalizedPageKey = $this->normalizePageKey($pageKey);
        if ($normalizedPageKey === '') {
            return;
        }

        $visitDate = $visitedAt->format('Y-m-d');
        $dailyKey = $visitDate . '|' . $normalizedPageKey;

        if (!isset($this->dailyTotals[$dailyKey])) {
            $this->dailyTotals[$dailyKey] = [
                'page_views' => 0,
                'unique_visitors' => 0,
            ];
        }

        $this->dailyTotals[$dailyKey]['page_views']++;

        $uniqueKey = $visitDate . '|' . $normalizedPageKey . '|' . trim($visitorTokenHash);
        if ($visitorTokenHash !== '' && !isset($this->dailyUniqueKeys[$uniqueKey])) {
            $this->dailyUniqueKeys[$uniqueKey] = true;
            $this->dailyTotals[$dailyKey]['unique_visitors']++;
        }
    }

    public function getDashboardSummary(): array
    {
        $today = new \DateTimeImmutable('today');
        $todayKey = $today->format('Y-m-d');
        $last7Start = $today->modify('-6 days')->format('Y-m-d');
        $baselineDate = $this->baselineStartedAt !== null
            ? (new \DateTimeImmutable($this->baselineStartedAt))->format('Y-m-d')
            : null;

        $summary = [
            'baseline_started_at' => $this->baselineStartedAt,
            'total_views' => 0,
            'total_unique_visitors' => 0,
            'today_views' => 0,
            'today_unique_visitors' => 0,
            'last_7_days_views' => 0,
            'last_7_days_unique_visitors' => 0,
            'top_pages' => [],
        ];

        /** @var array<string, array{page_views: int, unique_visitors: int}> $topPages */
        $topPages = [];

        foreach ($this->dailyTotals as $compoundKey => $totals) {
            [$visitDate, $pageKey] = explode('|', $compoundKey, 2);

            if ($visitDate === $todayKey) {
                $summary['today_views'] += $totals['page_views'];
                $summary['today_unique_visitors'] += $totals['unique_visitors'];
            }

            if ($visitDate >= $last7Start) {
                $summary['last_7_days_views'] += $totals['page_views'];
                $summary['last_7_days_unique_visitors'] += $totals['unique_visitors'];
            }

            if ($baselineDate !== null && $visitDate < $baselineDate) {
                continue;
            }

            $summary['total_views'] += $totals['page_views'];
            $summary['total_unique_visitors'] += $totals['unique_visitors'];

            if (!isset($topPages[$pageKey])) {
                $topPages[$pageKey] = [
                    'page_views' => 0,
                    'unique_visitors' => 0,
                ];
            }

            $topPages[$pageKey]['page_views'] += $totals['page_views'];
            $topPages[$pageKey]['unique_visitors'] += $totals['unique_visitors'];
        }

        uasort($topPages, static function (array $left, array $right): int {
            $viewsComparison = $right['page_views'] <=> $left['page_views'];
            if ($viewsComparison !== 0) {
                return $viewsComparison;
            }

            return $right['unique_visitors'] <=> $left['unique_visitors'];
        });

        $summary['top_pages'] = array_map(
            static fn (string $pageKey, array $totals): array => [
                'page_key' => $pageKey,
                'page_views' => $totals['page_views'],
                'unique_visitors' => $totals['unique_visitors'],
            ],
            array_keys(array_slice($topPages, 0, 5, true)),
            array_values(array_slice($topPages, 0, 5, true))
        );

        return $summary;
    }

    public function startNewCountingPeriod(?int $memberId = null, ?\DateTimeImmutable $startedAt = null): void
    {
        $this->baselineStartedAt = ($startedAt ?? new \DateTimeImmutable('today'))->format('Y-m-d H:i:s');
    }

    private function normalizePageKey(string $pageKey): string
    {
        $normalized = trim($pageKey);

        return $normalized === '' ? '' : $normalized;
    }
}
