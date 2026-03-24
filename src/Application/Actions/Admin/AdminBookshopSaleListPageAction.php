<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopSaleListPageAction extends AbstractAdminBookshopAction
{
    public const FLASH_KEY = 'admin_bookshop_sale_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 25, 50, 100];

    private const SORT_FIELDS = [
        'sold_at',
        'sale_code',
        'customer_name',
        'created_by_name',
        'payment_method',
        'item_count',
        'total_amount',
        'status',
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $sales = [];

        try {
            $sales = $this->bookshopRepository->findAllSalesForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao listar vendas da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $statusFilter = trim((string) ($queryParams['status_filter'] ?? 'all'));

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $sales = array_values(array_filter(
                $sales,
                static function (array $sale) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($sale['sale_code'] ?? ''),
                        (string) ($sale['customer_name'] ?? ''),
                        (string) ($sale['payment_method_label'] ?? ''),
                        (string) ($sale['created_by_name'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        if (in_array($statusFilter, ['completed', 'cancelled'], true)) {
            $sales = array_values(array_filter(
                $sales,
                static fn (array $sale): bool => (string) ($sale['status'] ?? '') === $statusFilter
            ));
        } else {
            $statusFilter = 'all';
        }

        $sortBy = (string) ($queryParams['sort'] ?? 'sold_at');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'sold_at';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($sales, static function (array $firstSale, array $secondSale) use ($sortBy, $sortMultiplier): int {
            $firstValue = $firstSale[$sortBy] ?? '';
            $secondValue = $secondSale[$sortBy] ?? '';

            if (in_array($sortBy, ['item_count', 'total_amount'], true)) {
                $comparison = (float) $firstValue <=> (float) $secondValue;

                return $comparison * $sortMultiplier;
            }

            $comparison = strnatcasecmp((string) $firstValue, (string) $secondValue);

            return $comparison * $sortMultiplier;
        });

        $requestedPageSize = (int) ($queryParams['per_page'] ?? self::DEFAULT_PAGE_SIZE);
        $pageSize = in_array($requestedPageSize, self::PAGE_SIZE_OPTIONS, true)
            ? $requestedPageSize
            : self::DEFAULT_PAGE_SIZE;

        $totalItems = count($sales);
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $sales = array_slice($sales, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($sales), $totalItems) : 0;

        $basePath = '/painel/livraria/vendas';
        $baseQuery = [
            'per_page' => $pageSize,
            'sort' => $sortBy,
            'dir' => $sortDirection,
            'q' => $searchTerm,
            'status_filter' => $statusFilter,
        ];

        $sortLinks = [];
        foreach (self::SORT_FIELDS as $field) {
            $nextDirection = $sortBy === $field && $sortDirection === 'asc' ? 'desc' : 'asc';
            $indicator = '↕';

            if ($sortBy === $field) {
                $indicator = $sortDirection === 'asc' ? '↑' : '↓';
            }

            $sortLinks[$field] = [
                'url' => $basePath . '?' . http_build_query($baseQuery + [
                    'page' => 1,
                    'sort' => $field,
                    'dir' => $nextDirection,
                ]),
                'indicator' => $indicator,
                'active' => $sortBy === $field,
            ];
        }

        $paginationLinks = [];
        for ($page = 1; $page <= $totalPages; $page++) {
            $paginationLinks[] = [
                'number' => $page,
                'active' => $page === $currentPage,
                'url' => $basePath . '?' . http_build_query($baseQuery + ['page' => $page]),
            ];
        }

        $previousPageUrl = $currentPage > 1
            ? $basePath . '?' . http_build_query($baseQuery + ['page' => $currentPage - 1])
            : null;
        $nextPageUrl = $currentPage < $totalPages
            ? $basePath . '?' . http_build_query($baseQuery + ['page' => $currentPage + 1])
            : null;

        $pageSizeOptions = array_map(static fn (int $option): array => [
            'value' => $option,
            'selected' => $option === $pageSize,
        ], self::PAGE_SIZE_OPTIONS);

        return $this->renderPage($response, 'pages/admin-bookshop-sales.twig', [
            'bookshop_sales' => $sales,
            'admin_status' => $status,
            'bookshop_sales_sort_links' => $sortLinks,
            'bookshop_sales_search' => $searchTerm,
            'bookshop_sales_filters' => [
                'status_filter' => $statusFilter,
            ],
            'bookshop_sales_pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'page_size' => $pageSize,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'links' => $paginationLinks,
                'previous_url' => $previousPageUrl,
                'next_url' => $nextPageUrl,
                'page_size_options' => $pageSizeOptions,
            ],
            'page_title' => 'Vendas PDV | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/vendas',
            'page_description' => 'Histórico administrativo de vendas de balcão da livraria.',
        ]);
    }
}
