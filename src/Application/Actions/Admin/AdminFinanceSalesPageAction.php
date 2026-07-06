<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceSalesPageAction extends AbstractAdminBookshopAction
{
    public const FLASH_KEY = 'admin_finance_sales_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private const ALL_PAGE_SIZE = 'all';

    private const PERIOD_FIELDS = ['sold_at', 'cancelled_at'];

    private const SORT_FIELDS = [
        'sold_at',
        'sale_code',
        'customer_name',
        'items_summary',
        'created_by_name',
        'payment_method',
        'total_amount',
        'status',
        'cancelled_at',
    ];

    private const CSV_HEADERS = [
        'data_venda',
        'codigo_venda',
        'cliente',
        'cpf',
        'telefone',
        'email',
        'itens',
        'quantidade_itens',
        'pagamento',
        'valor_total',
        'valor_recebido',
        'troco',
        'vendedor',
        'status',
        'cancelada_em',
        'cancelada_por',
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $sales = [];

        try {
            $sales = $this->bookshopRepository->findAllSalesForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar a visão financeira da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $statusFilter = trim((string) ($queryParams['status_filter'] ?? 'all'));
        $paymentFilter = trim((string) ($queryParams['payment_filter'] ?? 'all'));
        $sellerFilter = trim((string) ($queryParams['seller_filter'] ?? 'all'));
        $periodField = trim((string) ($queryParams['period_field'] ?? 'sold_at'));
        $dateFrom = $this->normalizeDateInput($queryParams['date_from'] ?? null);
        $dateTo = $this->normalizeDateInput($queryParams['date_to'] ?? null);
        $amountMin = $this->normalizeAmountFilter($queryParams['amount_min'] ?? null);
        $amountMax = $this->normalizeAmountFilter($queryParams['amount_max'] ?? null);
        $exportFormat = strtolower(trim((string) ($queryParams['export'] ?? '')));

        $paymentOptions = $this->buildPaymentOptions($sales);
        $sellerOptions = $this->buildSellerOptions($sales);

        if (!in_array($statusFilter, ['all', 'completed', 'cancelled'], true)) {
            $statusFilter = 'all';
        }

        if (!array_key_exists($paymentFilter, $paymentOptions)) {
            $paymentFilter = 'all';
        }

        if (!array_key_exists($sellerFilter, $sellerOptions)) {
            $sellerFilter = 'all';
        }

        if (!in_array($periodField, self::PERIOD_FIELDS, true)) {
            $periodField = 'sold_at';
        }

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if ($statusFilter !== 'all') {
            $sales = array_values(array_filter(
                $sales,
                static fn (array $sale): bool => (string) ($sale['status'] ?? '') === $statusFilter
            ));
        }

        if ($paymentFilter !== 'all') {
            $sales = array_values(array_filter(
                $sales,
                static fn (array $sale): bool => (string) ($sale['payment_method'] ?? '') === $paymentFilter
            ));
        }

        if ($sellerFilter !== 'all') {
            $sales = array_values(array_filter(
                $sales,
                static fn (array $sale): bool => trim((string) ($sale['created_by_name'] ?? '')) === $sellerFilter
            ));
        }

        if ($dateFrom !== null || $dateTo !== null) {
            $sales = array_values(array_filter(
                $sales,
                fn (array $sale): bool => $this->matchesDateRange($sale, $periodField, $dateFrom, $dateTo)
            ));
        }

        if ($amountMin !== null || $amountMax !== null) {
            $sales = array_values(array_filter(
                $sales,
                static function (array $sale) use ($amountMin, $amountMax): bool {
                    $totalAmount = (float) ($sale['total_amount'] ?? 0);

                    if ($amountMin !== null && $totalAmount < $amountMin) {
                        return false;
                    }

                    if ($amountMax !== null && $totalAmount > $amountMax) {
                        return false;
                    }

                    return true;
                }
            ));
        }

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $sales = array_values(array_filter(
                $sales,
                static function (array $sale) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($sale['sale_code'] ?? ''),
                        (string) ($sale['customer_name_display'] ?? $sale['customer_name'] ?? ''),
                        (string) ($sale['customer_phone_display'] ?? ''),
                        (string) ($sale['customer_email'] ?? ''),
                        (string) ($sale['customer_cpf_display'] ?? ''),
                        (string) ($sale['items_summary'] ?? ''),
                        (string) ($sale['payment_method_label'] ?? ''),
                        (string) ($sale['created_by_name'] ?? ''),
                        (string) ($sale['cancelled_by_name'] ?? ''),
                        (string) ($sale['status_label'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        $summary = $this->buildSummary($sales);
        $filterSummary = $this->buildFilterSummary(
            $summary,
            $searchTerm,
            $statusFilter,
            $paymentFilter,
            $sellerFilter,
            $periodField,
            $dateFrom,
            $dateTo,
            $amountMin,
            $amountMax,
            $paymentOptions
        );

        $sortBy = (string) ($queryParams['sort'] ?? 'sold_at');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'sold_at';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($sales, static function (array $firstSale, array $secondSale) use ($sortBy, $sortMultiplier): int {
            $firstValue = $firstSale[$sortBy] ?? '';
            $secondValue = $secondSale[$sortBy] ?? '';

            if ($sortBy === 'total_amount') {
                return (((float) $firstValue) <=> ((float) $secondValue)) * $sortMultiplier;
            }

            return strnatcasecmp((string) $firstValue, (string) $secondValue) * $sortMultiplier;
        });

        if ($exportFormat === 'csv') {
            return $this->renderCsvExport($response, $sales);
        }

        $totalItems = count($sales);
        $requestedPageSize = trim((string) ($queryParams['per_page'] ?? (string) self::DEFAULT_PAGE_SIZE));
        $showAllItems = $requestedPageSize === self::ALL_PAGE_SIZE;
        $pageSize = self::DEFAULT_PAGE_SIZE;

        if (!$showAllItems) {
            $requestedPageSizeNumber = (int) $requestedPageSize;
            $pageSize = in_array($requestedPageSizeNumber, self::PAGE_SIZE_OPTIONS, true)
                ? $requestedPageSizeNumber
                : self::DEFAULT_PAGE_SIZE;
        } else {
            $pageSize = max($totalItems, 1);
        }

        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $sales = array_slice($sales, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($sales), $totalItems) : 0;

        $pageSizeQueryValue = $showAllItems ? self::ALL_PAGE_SIZE : (string) $pageSize;
        $basePath = '/painel/financas';
        $baseQuery = [
            'per_page' => $pageSizeQueryValue,
            'sort' => $sortBy,
            'dir' => $sortDirection,
            'q' => $searchTerm,
            'status_filter' => $statusFilter,
            'payment_filter' => $paymentFilter,
            'seller_filter' => $sellerFilter,
            'period_field' => $periodField,
            'date_from' => $dateFrom ?? '',
            'date_to' => $dateTo ?? '',
            'amount_min' => $this->formatAmountFilterValue($amountMin),
            'amount_max' => $this->formatAmountFilterValue($amountMax),
        ];

        $sortLinks = [];
        foreach (self::SORT_FIELDS as $field) {
            $nextDirection = $sortBy === $field && $sortDirection === 'asc' ? 'desc' : 'asc';
            $indicator = '↕';

            if ($sortBy === $field) {
                $indicator = $sortDirection === 'asc' ? '↑' : '↓';
            }

            $sortLinks[$field] = [
                'url' => $basePath . '?' . http_build_query(array_merge($baseQuery, [
                    'page' => 1,
                    'sort' => $field,
                    'dir' => $nextDirection,
                ])),
                'indicator' => $indicator,
                'active' => $sortBy === $field,
            ];
        }

        $paginationLinks = [];
        for ($page = 1; $page <= $totalPages; $page++) {
            $paginationLinks[] = [
                'number' => $page,
                'active' => $page === $currentPage,
                'url' => $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $page])),
            ];
        }

        $previousPageUrl = $currentPage > 1
            ? $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $currentPage - 1]))
            : null;
        $nextPageUrl = $currentPage < $totalPages
            ? $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $currentPage + 1]))
            : null;

        $pageSizeOptions = array_map(static fn (int $option): array => [
            'value' => (string) $option,
            'label' => (string) $option,
            'selected' => !$showAllItems && $option === $pageSize,
        ], self::PAGE_SIZE_OPTIONS);
        $pageSizeOptions[] = [
            'value' => self::ALL_PAGE_SIZE,
            'label' => 'Todos',
            'selected' => $showAllItems,
        ];

        return $this->renderPage($response, 'pages/admin-finance-sales.twig', [
            'finance_sales' => $sales,
            'finance_sales_summary' => $summary,
            'finance_sales_filter_summary' => $filterSummary,
            'finance_sales_sort_links' => $sortLinks,
            'finance_sales_search' => $searchTerm,
            'finance_sales_filters' => [
                'status_filter' => $statusFilter,
                'payment_filter' => $paymentFilter,
                'seller_filter' => $sellerFilter,
                'period_field' => $periodField,
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'amount_min' => $this->formatAmountFilterValue($amountMin),
                'amount_max' => $this->formatAmountFilterValue($amountMax),
            ],
            'finance_sales_filter_options' => [
                'payment_methods' => $paymentOptions,
                'sellers' => $sellerOptions,
            ],
            'finance_sales_export_csv_url' => $basePath . '?' . http_build_query([
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'q' => $searchTerm,
                'status_filter' => $statusFilter,
                'payment_filter' => $paymentFilter,
                'seller_filter' => $sellerFilter,
                'period_field' => $periodField,
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'amount_min' => $this->formatAmountFilterValue($amountMin),
                'amount_max' => $this->formatAmountFilterValue($amountMax),
                'export' => 'csv',
            ]),
            'finance_sales_pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'page_size' => $pageSizeQueryValue,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'links' => $paginationLinks,
                'previous_url' => $previousPageUrl,
                'next_url' => $nextPageUrl,
                'page_size_options' => $pageSizeOptions,
            ],
            'admin_status' => $status,
            'page_title' => 'Finanças | Dashboard',
            'page_url' => 'https://cedern.org/painel/financas',
            'page_description' => 'Consulta financeira de vendas e cancelamentos da livraria do CEDE.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     */
    private function renderCsvExport(Response $response, array $sales): Response
    {
        $handle = fopen('php://temp', 'w+');
        if (!is_resource($handle)) {
            $response->getBody()->write('Falha ao preparar exportacao.');

            return $response->withStatus(500);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::CSV_HEADERS, ';');

        foreach ($sales as $sale) {
            fputcsv($handle, [
                (string) ($sale['sold_at_label'] ?? $sale['sold_at'] ?? ''),
                (string) ($sale['sale_code'] ?? ''),
                (string) ($sale['customer_name_display'] ?? $sale['customer_name'] ?? ''),
                (string) ($sale['customer_cpf_display'] ?? ''),
                (string) ($sale['customer_phone_display'] ?? ''),
                (string) ($sale['customer_email'] ?? ''),
                (string) ($sale['items_summary'] ?? ''),
                (string) ($sale['item_count'] ?? ''),
                (string) ($sale['payment_method_label'] ?? ''),
                (string) ($sale['total_amount_label'] ?? ''),
                (string) ($sale['received_amount_label'] ?? ''),
                (string) ($sale['change_amount_label'] ?? ''),
                (string) ($sale['created_by_name'] ?? ''),
                (string) ($sale['status_label'] ?? ''),
                (string) ($sale['cancelled_at_label'] ?? ''),
                (string) ($sale['cancelled_by_name'] ?? ''),
            ], ';');
        }

        rewind($handle);
        $body = stream_get_contents($handle) ?: '';
        fclose($handle);

        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza')))->format('Ymd-His');
        $filename = 'financeiro-livraria-' . $timestamp . '.csv';

        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @return array<string, string>
     */
    private function buildPaymentOptions(array $sales): array
    {
        $options = ['all' => 'Todos os meios'];

        foreach ($sales as $sale) {
            $paymentMethod = trim((string) ($sale['payment_method'] ?? ''));
            $paymentLabel = trim((string) ($sale['payment_method_label'] ?? ''));

            if ($paymentMethod === '' || isset($options[$paymentMethod])) {
                continue;
            }

            $options[$paymentMethod] = $paymentLabel !== '' ? $paymentLabel : ucfirst($paymentMethod);
        }

        return $options;
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @return array<string, string>
     */
    private function buildSellerOptions(array $sales): array
    {
        $options = ['all' => 'Todos os vendedores'];

        foreach ($sales as $sale) {
            $seller = trim((string) ($sale['created_by_name'] ?? ''));

            if ($seller === '' || isset($options[$seller])) {
                continue;
            }

            $options[$seller] = $seller;
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return ['all' => 'Todos os vendedores'] + array_diff_key($options, ['all' => true]);
    }

    /**
     * @param array<string, mixed> $sale
     */
    private function matchesDateRange(array $sale, string $periodField, ?string $dateFrom, ?string $dateTo): bool
    {
        $saleDate = $this->resolveSaleFilterDate($sale, $periodField);
        if ($saleDate === null) {
            return false;
        }

        if ($dateFrom !== null && $saleDate < $dateFrom) {
            return false;
        }

        if ($dateTo !== null && $saleDate > $dateTo) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $sale
     */
    private function resolveSaleFilterDate(array $sale, string $periodField): ?string
    {
        $field = $periodField === 'cancelled_at' ? 'cancelled_at' : 'sold_at';
        $rawValue = trim((string) ($sale[$field] ?? ''));
        if ($rawValue === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($rawValue, new DateTimeZone('UTC'));

            return $date->setTimezone(new DateTimeZone('America/Fortaleza'))->format('Y-m-d');
        } catch (\Throwable) {
            return preg_match('/^\d{4}-\d{2}-\d{2}/', $rawValue) === 1
                ? substr($rawValue, 0, 10)
                : null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     * @return array<string, mixed>
     */
    private function buildSummary(array $sales): array
    {
        $completedCount = 0;
        $cancelledCount = 0;
        $completedTotal = 0.0;
        $cancelledTotal = 0.0;

        foreach ($sales as $sale) {
            $status = (string) ($sale['status'] ?? '');
            $totalAmount = (float) ($sale['total_amount'] ?? 0);

            if ($status === 'cancelled') {
                $cancelledCount++;
                $cancelledTotal += $totalAmount;

                continue;
            }

            $completedCount++;
            $completedTotal += $totalAmount;
        }

        $averageTicket = $completedCount > 0 ? ($completedTotal / $completedCount) : 0.0;

        return [
            'completed_count' => $completedCount,
            'completed_total_label' => $this->formatMoney($completedTotal),
            'cancelled_count' => $cancelledCount,
            'cancelled_total_label' => $this->formatMoney($cancelledTotal),
            'recognized_total_label' => $this->formatMoney($completedTotal),
            'average_ticket_label' => $this->formatMoney($averageTicket),
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, string> $paymentOptions
     * @return array<string, mixed>|null
     */
    private function buildFilterSummary(
        array $summary,
        string $searchTerm,
        string $statusFilter,
        string $paymentFilter,
        string $sellerFilter,
        string $periodField,
        ?string $dateFrom,
        ?string $dateTo,
        ?float $amountMin,
        ?float $amountMax,
        array $paymentOptions
    ): ?array {
        $contextLabels = [];

        if ($searchTerm !== '') {
            $contextLabels[] = 'Busca: ' . $searchTerm;
        }

        if ($statusFilter === 'completed') {
            $contextLabels[] = 'Status: somente concluídas';
        } elseif ($statusFilter === 'cancelled') {
            $contextLabels[] = 'Status: somente canceladas';
        }

        if ($paymentFilter !== 'all' && isset($paymentOptions[$paymentFilter])) {
            $contextLabels[] = 'Pagamento: ' . $paymentOptions[$paymentFilter];
        }

        if ($sellerFilter !== 'all') {
            $contextLabels[] = 'Vendedor: ' . $sellerFilter;
        }

        if ($dateFrom !== null || $dateTo !== null) {
            $periodLabel = $periodField === 'cancelled_at' ? 'Período do cancelamento' : 'Período da venda';
            $contextLabels[] = $periodLabel . ': ' . $this->formatPeriodRangeLabel($dateFrom, $dateTo);
        }

        $amountRangeLabel = $this->formatAmountRangeLabel($amountMin, $amountMax);
        if ($amountRangeLabel !== null) {
            $contextLabels[] = $amountRangeLabel;
        }

        if ($contextLabels === []) {
            return null;
        }

        $completedCount = (int) ($summary['completed_count'] ?? 0);

        return [
            'context_labels' => $contextLabels,
            'completed_count' => $completedCount,
            'completed_count_label' => sprintf(
                '%d venda%s concluída%s',
                $completedCount,
                $completedCount === 1 ? '' : 's',
                $completedCount === 1 ? '' : 's'
            ),
            'recognized_total_label' => (string) ($summary['recognized_total_label'] ?? 'R$ 0,00'),
        ];
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        $normalizedValue = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizedValue) === 1
            ? $normalizedValue
            : null;
    }

    private function normalizeAmountFilter(mixed $value): ?float
    {
        $normalizedValue = trim((string) $value);
        if ($normalizedValue === '' || preg_match('/\d/', $normalizedValue) !== 1) {
            return null;
        }

        return (float) $this->normalizeMoneyInput($normalizedValue);
    }

    private function formatAmountFilterValue(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return number_format($value, 2, '.', '');
    }

    private function formatMoney(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function formatAmountRangeLabel(?float $amountMin, ?float $amountMax): ?string
    {
        if ($amountMin !== null && $amountMax !== null) {
            return 'Valor: ' . $this->formatMoney($amountMin) . ' a ' . $this->formatMoney($amountMax);
        }

        if ($amountMin !== null) {
            return 'Valor: a partir de ' . $this->formatMoney($amountMin);
        }

        if ($amountMax !== null) {
            return 'Valor: até ' . $this->formatMoney($amountMax);
        }

        return null;
    }

    private function formatPeriodRangeLabel(?string $dateFrom, ?string $dateTo): string
    {
        if ($dateFrom !== null && $dateTo !== null) {
            return $this->formatDateLabel($dateFrom) . ' a ' . $this->formatDateLabel($dateTo);
        }

        if ($dateFrom !== null) {
            return 'A partir de ' . $this->formatDateLabel($dateFrom);
        }

        if ($dateTo !== null) {
            return 'Até ' . $this->formatDateLabel($dateTo);
        }

        return '';
    }

    private function formatDateLabel(string $value): string
    {
        try {
            return (new DateTimeImmutable($value))->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}
