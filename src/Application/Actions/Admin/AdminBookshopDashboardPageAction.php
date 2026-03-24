<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopDashboardPageAction extends AbstractAdminBookshopAction
{
    private const LOCAL_TIMEZONE = 'America/Fortaleza';

    private const STORAGE_TIMEZONE = 'UTC';

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];
        $sales = [];

        try {
            $books = $this->bookshopRepository->findAllBooksForAdmin();
            $sales = $this->bookshopRepository->findAllSalesForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar dashboard da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone(self::LOCAL_TIMEZONE));
        $monthStart = $today->modify('first day of this month')->setTime(0, 0);

        $metrics = [
            'total_titles' => count($books),
            'active_titles' => 0,
            'inactive_titles' => 0,
            'low_stock_titles' => 0,
            'out_of_stock_titles' => 0,
            'total_units' => 0,
            'inventory_value' => 0.0,
            'potential_revenue' => 0.0,
            'sales_today_count' => 0,
            'sales_today_total' => 0.0,
            'sales_month_total' => 0.0,
            'recent_sales' => array_slice($sales, 0, 5),
        ];

        foreach ($books as $book) {
            $metrics['total_units'] += (int) ($book['stock_quantity'] ?? 0);
            $metrics['inventory_value'] += (float) ($book['inventory_value'] ?? 0);
            $metrics['potential_revenue'] += (float) ($book['potential_revenue_value'] ?? 0);

            if ((string) ($book['status'] ?? 'active') === 'active') {
                $metrics['active_titles']++;
            } else {
                $metrics['inactive_titles']++;
            }

            if ((string) ($book['stock_state'] ?? '') === 'low') {
                $metrics['low_stock_titles']++;
            }

            if ((string) ($book['stock_state'] ?? '') === 'out') {
                $metrics['out_of_stock_titles']++;
            }
        }

        foreach ($sales as $sale) {
            if ((string) ($sale['status'] ?? 'completed') !== 'completed') {
                continue;
            }

            $soldAtRaw = trim((string) ($sale['sold_at'] ?? ''));
            if ($soldAtRaw === '') {
                continue;
            }

            $soldAt = $this->parseStoredSaleDateTime($soldAtRaw);
            if ($soldAt === null) {
                continue;
            }

            $saleTotal = (float) ($sale['total_amount'] ?? 0);

            if ($soldAt >= $today) {
                $metrics['sales_today_count']++;
                $metrics['sales_today_total'] += $saleTotal;
            }

            if ($soldAt >= $monthStart) {
                $metrics['sales_month_total'] += $saleTotal;
            }
        }

        $metrics['inventory_value_label'] = $this->formatMoney($metrics['inventory_value']);
        $metrics['potential_revenue_label'] = $this->formatMoney($metrics['potential_revenue']);
        $metrics['sales_today_total_label'] = $this->formatMoney($metrics['sales_today_total']);
        $metrics['sales_month_total_label'] = $this->formatMoney($metrics['sales_month_total']);

        return $this->renderPage($response, 'pages/admin-bookshop-dashboard.twig', [
            'bookshop_metrics' => $metrics,
            'page_title' => 'Livraria | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria',
            'page_description' => 'Operação administrativa da livraria e PDV do CEDE.',
        ]);
    }

    private function formatMoney(float $amount): string
    {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }

    private function parseStoredSaleDateTime(string $value): ?\DateTimeImmutable
    {
        $normalized = trim($value);
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
}
