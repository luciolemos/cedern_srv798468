<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopDashboardPageAction extends AbstractAdminBookshopAction
{
    private const LOCAL_TIMEZONE = 'America/Fortaleza';

    private const STORAGE_TIMEZONE = 'UTC';

    private const SUMMARY_LIMIT = 5;

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];
        $sales = [];
        $movements = [];

        try {
            $books = $this->bookshopRepository->findAllBooksForAdmin();
            $sales = $this->bookshopRepository->findAllSalesForAdmin();
            $movements = $this->bookshopRepository->findAllStockMovementsForAdmin();
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
            'sales_month_count' => 0,
            'sales_month_total' => 0.0,
            'average_ticket' => 0.0,
            'entries_today_count' => 0,
            'entries_today_units' => 0,
            'adjustments_today_count' => 0,
            'adjustments_today_units' => 0,
            'cancelled_sales_month_count' => 0,
            'cancelled_sales_month_total' => 0.0,
            'titles_without_lot' => 0,
            'titles_incomplete' => 0,
            'titles_without_cover' => 0,
            'titles_without_category' => 0,
            'titles_without_genre' => 0,
            'titles_without_barcode' => 0,
            'titles_without_isbn' => 0,
            'titles_without_location' => 0,
            'titles_without_sale_price' => 0,
            'recent_sales' => array_slice($sales, 0, 5),
        ];

        $paymentSummary = [];

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

            $missingCover = trim((string) ($book['cover_image_url'] ?? '')) === '';
            $missingCategory = trim((string) ($book['category_name'] ?? '')) === '';
            $missingGenre = trim((string) ($book['genre_name'] ?? '')) === '';
            $missingBarcode = trim((string) ($book['barcode'] ?? '')) === '';
            $missingIsbn = trim((string) ($book['isbn'] ?? '')) === '';
            $missingLocation = trim((string) ($book['location_label'] ?? '')) === '';
            $missingSalePrice = (float) ($book['sale_price'] ?? 0) <= 0;
            $missingLot = (int) ($book['stock_lot_count'] ?? 0) <= 0;

            if ($missingCover) {
                $metrics['titles_without_cover']++;
            }

            if ($missingCategory) {
                $metrics['titles_without_category']++;
            }

            if ($missingGenre) {
                $metrics['titles_without_genre']++;
            }

            if ($missingBarcode) {
                $metrics['titles_without_barcode']++;
            }

            if ($missingIsbn) {
                $metrics['titles_without_isbn']++;
            }

            if ($missingLocation) {
                $metrics['titles_without_location']++;
            }

            if ($missingSalePrice) {
                $metrics['titles_without_sale_price']++;
            }

            if ($missingLot) {
                $metrics['titles_without_lot']++;
            }

            if ($missingCover || $missingCategory || $missingGenre || $missingBarcode || $missingIsbn || $missingLocation || $missingSalePrice || $missingLot) {
                $metrics['titles_incomplete']++;
            }
        }

        foreach ($sales as $sale) {
            $status = (string) ($sale['status'] ?? 'completed');
            $saleTotal = (float) ($sale['total_amount'] ?? 0);

            if ($status === 'cancelled') {
                $cancelledAt = $this->parseStoredDateTime($sale['cancelled_at'] ?? null);
                if ($cancelledAt !== null && $cancelledAt >= $monthStart) {
                    $metrics['cancelled_sales_month_count']++;
                    $metrics['cancelled_sales_month_total'] += $saleTotal;
                }

                continue;
            }

            if ($status !== 'completed') {
                continue;
            }

            $soldAtRaw = trim((string) ($sale['sold_at'] ?? ''));
            if ($soldAtRaw === '') {
                continue;
            }

            $soldAt = $this->parseStoredDateTime($soldAtRaw);
            if ($soldAt === null) {
                continue;
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

            if ($soldAt >= $today) {
                $metrics['sales_today_count']++;
                $metrics['sales_today_total'] += $saleTotal;
            }

            if ($soldAt >= $monthStart) {
                $metrics['sales_month_count']++;
                $metrics['sales_month_total'] += $saleTotal;
                $paymentSummary[$paymentKey]['count']++;
                $paymentSummary[$paymentKey]['total'] += $saleTotal;
            }
        }

        foreach ($movements as $movement) {
            $occurredAt = $this->parseStoredDateTime($movement['occurred_at'] ?? null);
            if ($occurredAt === null || $occurredAt < $today) {
                continue;
            }

            $movementType = (string) ($movement['movement_type'] ?? '');
            $quantity = max(0, (int) ($movement['quantity'] ?? 0));
            $stockDelta = abs((int) ($movement['stock_delta'] ?? 0));

            if (in_array($movementType, ['entry', 'donation'], true)) {
                $metrics['entries_today_count']++;
                $metrics['entries_today_units'] += $quantity;
                continue;
            }

            $metrics['adjustments_today_count']++;
            $metrics['adjustments_today_units'] += $stockDelta;
        }

        $metrics['average_ticket'] = $metrics['sales_month_count'] > 0
            ? $metrics['sales_month_total'] / $metrics['sales_month_count']
            : 0.0;
        $metrics['inventory_value_label'] = $this->formatMoney($metrics['inventory_value']);
        $metrics['potential_revenue_label'] = $this->formatMoney($metrics['potential_revenue']);
        $metrics['sales_today_total_label'] = $this->formatMoney($metrics['sales_today_total']);
        $metrics['sales_month_total_label'] = $this->formatMoney($metrics['sales_month_total']);
        $metrics['average_ticket_label'] = $this->formatMoney($metrics['average_ticket']);
        $metrics['cancelled_sales_month_total_label'] = $this->formatMoney($metrics['cancelled_sales_month_total']);

        $attentionRows = [
            [
                'label' => 'Estoque zerado',
                'count' => $metrics['out_of_stock_titles'],
                'copy' => 'Títulos que já não têm nenhuma unidade disponível no saldo.',
                'href' => '/painel/livraria/acervo?stock_filter=out',
            ],
            [
                'label' => 'Estoque baixo',
                'count' => $metrics['low_stock_titles'],
                'copy' => 'Títulos no limite do estoque mínimo cadastrado.',
                'href' => '/painel/livraria/acervo?stock_filter=low',
            ],
            [
                'label' => 'Sem lote operacional',
                'count' => $metrics['titles_without_lot'],
                'copy' => 'Itens cadastrados que ainda exigem Nova entrada para formar o estoque.',
                'href' => '/painel/livraria/acervo',
            ],
            [
                'label' => 'Cadastros incompletos',
                'count' => $metrics['titles_incomplete'],
                'copy' => 'Títulos com pendências de capa, classificação, preço, localização ou identificação.',
                'href' => '/painel/livraria/acervo',
            ],
            [
                'label' => 'Cancelamentos no mês',
                'count' => $metrics['cancelled_sales_month_count'],
                'copy' => 'Vendas estornadas no período, com devolução automática ao estoque.',
                'href' => '/painel/livraria/vendas?status_filter=cancelled',
            ],
        ];

        $coverageRows = [
            [
                'label' => 'Sem capa',
                'count' => $metrics['titles_without_cover'],
                'copy' => 'Afeta a apresentação do acervo e da vitrine pública.',
            ],
            [
                'label' => 'Sem categoria doutrinária',
                'count' => $metrics['titles_without_category'],
                'copy' => 'Dificulta a organização do estudo e dos filtros.',
            ],
            [
                'label' => 'Sem gênero literário',
                'count' => $metrics['titles_without_genre'],
                'copy' => 'Enfraquece a classificação editorial do título.',
            ],
            [
                'label' => 'Sem código de barras',
                'count' => $metrics['titles_without_barcode'],
                'copy' => 'Prejudica localização rápida e conferência operacional.',
            ],
            [
                'label' => 'Sem ISBN',
                'count' => $metrics['titles_without_isbn'],
                'copy' => 'Fica sem identificação bibliográfica da edição.',
            ],
            [
                'label' => 'Sem localização',
                'count' => $metrics['titles_without_location'],
                'copy' => 'Dificulta a busca física no balcão e no estoque.',
            ],
            [
                'label' => 'Sem preço de venda',
                'count' => $metrics['titles_without_sale_price'],
                'copy' => 'Impede precificação correta na vitrine e no PDV.',
            ],
        ];

        return $this->renderPage($response, 'pages/admin-bookshop-dashboard.twig', [
            'bookshop_metrics' => $metrics,
            'bookshop_dashboard_attention' => $attentionRows,
            'bookshop_dashboard_coverage' => $coverageRows,
            'bookshop_dashboard_recent_movements' => array_slice($movements, 0, self::SUMMARY_LIMIT),
            'bookshop_dashboard_payment_summary' => $this->summarizeTotals($paymentSummary),
            'page_title' => 'Livraria | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria',
            'page_description' => 'Operação administrativa da livraria e PDV do CEDE.',
        ]);
    }

    private function formatMoney(float $amount): string
    {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }

    /**
     * @param array<string, array{label: string, count: int, total: float}> $totals
     * @return array<int, array{label: string, count: int, total: float, total_label: string}>
     */
    private function summarizeTotals(array $totals): array
    {
        $rows = [];

        foreach ($totals as $row) {
            $total = (float) $row['total'];
            $rows[] = [
                'label' => $row['label'],
                'count' => $row['count'],
                'total' => $total,
                'total_label' => $this->formatMoney($total),
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

    private function parseStoredDateTime(mixed $value): ?\DateTimeImmutable
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
}
