<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopReportsPageAction extends AbstractAdminBookshopAction
{
    private const LOCAL_TIMEZONE = 'America/Fortaleza';

    private const STORAGE_TIMEZONE = 'UTC';

    private const SUMMARY_LIMIT = 6;

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];
        $sales = [];
        $categories = [];
        $genres = [];
        $collections = [];

        try {
            $books = $this->bookshopRepository->findAllBooksForAdmin();
            $sales = $this->bookshopRepository->findAllSalesForAdmin();
            $categories = $this->bookshopRepository->findAllCategoriesForAdmin();
            $genres = $this->bookshopRepository->findAllGenresForAdmin();
            $collections = $this->bookshopRepository->findAllCollectionsForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar relatórios da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone(self::LOCAL_TIMEZONE));
        $monthStart = $today->modify('first day of this month')->setTime(0, 0);
        $publishers = [];
        $stockAlerts = [];
        $categorySummary = [];
        $genreSummary = [];
        $collectionSummary = [];
        $paymentSummary = [];
        $operatorSummary = [];
        $recentCancelledSales = [];

        $metrics = [
            'total_titles' => count($books),
            'active_titles' => 0,
            'inactive_titles' => 0,
            'total_units' => 0,
            'inventory_value' => 0.0,
            'potential_revenue' => 0.0,
            'low_stock_titles' => 0,
            'out_of_stock_titles' => 0,
            'completed_sales_count' => 0,
            'completed_sales_total' => 0.0,
            'average_ticket' => 0.0,
            'sales_today_count' => 0,
            'sales_today_total' => 0.0,
            'sales_month_count' => 0,
            'sales_month_total' => 0.0,
            'cancelled_sales_count' => 0,
            'categories_count' => count($categories),
            'genres_count' => count($genres),
            'collections_count' => count($collections),
            'publishers_count' => 0,
        ];

        foreach ($books as $book) {
            $stockQuantity = (int) ($book['stock_quantity'] ?? 0);
            $metrics['total_units'] += $stockQuantity;
            $metrics['inventory_value'] += (float) ($book['inventory_value'] ?? 0);
            $metrics['potential_revenue'] += (float) ($book['potential_revenue_value'] ?? 0);

            if ((string) ($book['status'] ?? 'active') === 'active') {
                $metrics['active_titles']++;
            } else {
                $metrics['inactive_titles']++;
            }

            $stockState = (string) ($book['stock_state'] ?? '');
            if ($stockState === 'low') {
                $metrics['low_stock_titles']++;
            }

            if ($stockState === 'out') {
                $metrics['out_of_stock_titles']++;
            }

            $publisherName = trim((string) ($book['publisher_name'] ?? ''));
            if ($publisherName !== '') {
                $publishers[$publisherName] = true;
            }

            $categoryLabel = trim((string) ($book['category_name'] ?? ''));
            $this->incrementNamedCounter($categorySummary, $categoryLabel !== '' ? $categoryLabel : 'Sem categoria');

            $genreLabel = trim((string) ($book['genre_name'] ?? ''));
            $this->incrementNamedCounter($genreSummary, $genreLabel !== '' ? $genreLabel : 'Sem gênero');

            $collectionLabel = trim((string) ($book['collection_name'] ?? ''));
            if ($collectionLabel !== '') {
                $this->incrementNamedCounter($collectionSummary, $collectionLabel);
            }

            if (in_array($stockState, ['low', 'out'], true)) {
                $stockAlerts[] = [
                    'title' => (string) ($book['title'] ?? 'Item'),
                    'sku' => (string) ($book['sku'] ?? ''),
                    'stock_quantity' => $stockQuantity,
                    'stock_minimum' => (int) ($book['stock_minimum'] ?? 0),
                    'stock_state' => $stockState,
                    'stock_state_label' => (string) ($book['stock_state_label'] ?? 'Estoque'),
                    'location_label' => (string) ($book['location_label'] ?? ''),
                ];
            }
        }

        foreach ($sales as $sale) {
            $status = (string) ($sale['status'] ?? 'completed');
            $saleTotal = (float) ($sale['total_amount'] ?? 0);
            $soldAt = $this->parseStoredSaleDateTime($sale['sold_at'] ?? null);

            if ($status === 'cancelled') {
                $metrics['cancelled_sales_count']++;

                $recentCancelledSales[] = [
                    'sale_code' => (string) ($sale['sale_code'] ?? ''),
                    'cancelled_at' => (string) ($sale['cancelled_at'] ?? ''),
                    'cancelled_at_label' => (string) ($sale['cancelled_at_label'] ?? ''),
                    'customer_name' => (string) ($sale['customer_name'] ?? ''),
                    'cancelled_by_name' => (string) ($sale['cancelled_by_name'] ?? ''),
                ];

                continue;
            }

            if ($status !== 'completed') {
                continue;
            }

            $metrics['completed_sales_count']++;
            $metrics['completed_sales_total'] += $saleTotal;

            if ($soldAt !== null && $soldAt >= $today) {
                $metrics['sales_today_count']++;
                $metrics['sales_today_total'] += $saleTotal;
            }

            if ($soldAt !== null && $soldAt >= $monthStart) {
                $metrics['sales_month_count']++;
                $metrics['sales_month_total'] += $saleTotal;
            }

            $paymentLabel = trim((string) ($sale['payment_method_label'] ?? ''));
            $paymentKey = $paymentLabel !== '' ? $paymentLabel : 'Outro';

            if (!isset($paymentSummary[$paymentKey])) {
                $paymentSummary[$paymentKey] = [
                    'label' => $paymentKey,
                    'count' => 0,
                    'total' => 0.0,
                ];
            }

            $paymentSummary[$paymentKey]['count']++;
            $paymentSummary[$paymentKey]['total'] += $saleTotal;

            $operatorLabel = trim((string) ($sale['created_by_name'] ?? ''));
            $operatorKey = $operatorLabel !== '' ? $operatorLabel : 'Não informado';

            if (!isset($operatorSummary[$operatorKey])) {
                $operatorSummary[$operatorKey] = [
                    'label' => $operatorKey,
                    'count' => 0,
                    'total' => 0.0,
                    'last_sale_label' => '',
                ];
            }

            $operatorSummary[$operatorKey]['count']++;
            $operatorSummary[$operatorKey]['total'] += $saleTotal;
            $operatorSummary[$operatorKey]['last_sale_label'] = (string) ($sale['sold_at_label'] ?? '');
        }

        $metrics['publishers_count'] = count($publishers);
        $metrics['average_ticket'] = $metrics['completed_sales_count'] > 0
            ? $metrics['completed_sales_total'] / $metrics['completed_sales_count']
            : 0.0;
        $metrics['inventory_value_label'] = $this->formatMoney($metrics['inventory_value']);
        $metrics['potential_revenue_label'] = $this->formatMoney($metrics['potential_revenue']);
        $metrics['completed_sales_total_label'] = $this->formatMoney($metrics['completed_sales_total']);
        $metrics['average_ticket_label'] = $this->formatMoney($metrics['average_ticket']);
        $metrics['sales_today_total_label'] = $this->formatMoney($metrics['sales_today_total']);
        $metrics['sales_month_total_label'] = $this->formatMoney($metrics['sales_month_total']);

        usort($stockAlerts, static function (array $first, array $second): int {
            $priority = ['out' => 0, 'low' => 1];
            $firstPriority = $priority[$first['stock_state']] ?? 9;
            $secondPriority = $priority[$second['stock_state']] ?? 9;

            if ($firstPriority !== $secondPriority) {
                return $firstPriority <=> $secondPriority;
            }

            $quantityComparison = $first['stock_quantity'] <=> $second['stock_quantity'];
            if ($quantityComparison !== 0) {
                return $quantityComparison;
            }

            return strnatcasecmp($first['title'], $second['title']);
        });

        usort($recentCancelledSales, function (array $first, array $second): int {
            $firstDate = $this->parseStoredSaleDateTime($first['cancelled_at']);
            $secondDate = $this->parseStoredSaleDateTime($second['cancelled_at']);

            if ($firstDate === null && $secondDate === null) {
                return 0;
            }

            if ($firstDate === null) {
                return 1;
            }

            if ($secondDate === null) {
                return -1;
            }

            return $secondDate <=> $firstDate;
        });

        $paymentRows = $this->summarizeTotals($paymentSummary);
        $operatorRows = $this->summarizeTotals($operatorSummary);

        return $this->renderPage($response, 'pages/admin-bookshop-reports.twig', [
            'bookshop_report_metrics' => $metrics,
            'bookshop_report_payments' => $paymentRows,
            'bookshop_report_operators' => $operatorRows,
            'bookshop_report_stock_alerts' => array_slice($stockAlerts, 0, self::SUMMARY_LIMIT),
            'bookshop_report_recent_cancelled' => array_slice($recentCancelledSales, 0, self::SUMMARY_LIMIT),
            'bookshop_report_category_summary' => $this->summarizeCounts($categorySummary),
            'bookshop_report_genre_summary' => $this->summarizeCounts($genreSummary),
            'bookshop_report_collection_summary' => $this->summarizeCounts($collectionSummary),
            'page_title' => 'Relatórios da Livraria | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/relatorios',
            'page_description' => 'Relatórios operacionais da livraria física do CEDE.',
        ]);
    }

    /**
     * @param array<string, int> $bucket
     */
    private function incrementNamedCounter(array &$bucket, string $label): void
    {
        $normalizedLabel = trim($label);
        if ($normalizedLabel === '') {
            return;
        }

        if (!isset($bucket[$normalizedLabel])) {
            $bucket[$normalizedLabel] = 0;
        }

        $bucket[$normalizedLabel]++;
    }

    /**
     * @param array<string, int> $counts
     * @return array<int, array{label: string, count: int}>
     */
    private function summarizeCounts(array $counts): array
    {
        $rows = [];

        foreach ($counts as $label => $count) {
            if ($count <= 0) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'count' => $count,
            ];
        }

        usort($rows, static function (array $first, array $second): int {
            $countComparison = $second['count'] <=> $first['count'];
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strnatcasecmp($first['label'], $second['label']);
        });

        return array_slice($rows, 0, self::SUMMARY_LIMIT);
    }

    /**
     * @param array<string, array{label: string, count: int, total: float, last_sale_label?: string}> $totals
     * @return array<int, array{label: string, count: int, total: float, total_label: string, last_sale_label: string}>
     */
    private function summarizeTotals(array $totals): array
    {
        $rows = [];

        foreach ($totals as $row) {
            $total = $row['total'];
            $rows[] = [
                'label' => $row['label'],
                'count' => $row['count'],
                'total' => $total,
                'total_label' => $this->formatMoney($total),
                'last_sale_label' => (string) ($row['last_sale_label'] ?? ''),
            ];
        }

        usort($rows, static function (array $first, array $second): int {
            $totalComparison = $second['total'] <=> $first['total'];
            if ($totalComparison !== 0) {
                return $totalComparison;
            }

            $countComparison = $second['count'] <=> $first['count'];
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strnatcasecmp($first['label'], $second['label']);
        });

        return array_slice($rows, 0, self::SUMMARY_LIMIT);
    }

    private function parseStoredSaleDateTime(mixed $value): ?\DateTimeImmutable
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($normalized, new \DateTimeZone(self::STORAGE_TIMEZONE)))
                ->setTimezone(new \DateTimeZone(self::LOCAL_TIMEZONE));
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function formatMoney(float $amount): string
    {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }
}
