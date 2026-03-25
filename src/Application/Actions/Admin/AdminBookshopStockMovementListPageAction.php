<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopStockMovementListPageAction extends AbstractAdminBookshopAction
{
    public const FLASH_KEY = 'admin_bookshop_stock_movement_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

    private const SORT_FIELDS = ['id', 'occurred_at', 'movement_type', 'title_snapshot', 'stock_delta', 'created_by_name'];

    public function __invoke(Request $request, Response $response): Response
    {
        $movements = [];

        try {
            $movements = $this->bookshopRepository->findAllStockMovementsForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao listar movimentações de estoque da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $movementId = (int) ($flash['movement_id'] ?? 0);
        $flashBookTitle = trim((string) ($flash['book_title'] ?? ''));
        $flashStockQuantity = (int) ($flash['stock_quantity'] ?? 0);
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $typeFilter = trim((string) ($queryParams['type_filter'] ?? 'all'));
        $validTypeFilters = ['all', 'entry', 'donation', 'adjustment_add', 'adjustment_remove', 'loss'];

        if (!in_array($typeFilter, $validTypeFilters, true)) {
            $typeFilter = 'all';
        }

        if ($typeFilter !== 'all') {
            $movements = array_values(array_filter(
                $movements,
                static fn (array $movement): bool => (string) ($movement['movement_type'] ?? '') === $typeFilter
            ));
        }

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $movements = array_values(array_filter(
                $movements,
                static function (array $movement) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($movement['movement_code'] ?? ''),
                        (string) ($movement['stock_lot_code_snapshot'] ?? ''),
                        (string) ($movement['title_snapshot'] ?? ''),
                        (string) ($movement['author_snapshot'] ?? ''),
                        (string) ($movement['sku_snapshot'] ?? ''),
                        (string) ($movement['movement_type_label'] ?? ''),
                        (string) ($movement['created_by_name'] ?? ''),
                        (string) ($movement['notes'] ?? ''),
                        (string) ($movement['occurred_at_label'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        $sortBy = (string) ($queryParams['sort'] ?? 'occurred_at');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'occurred_at';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($movements, static function (array $firstMovement, array $secondMovement) use ($sortBy, $sortMultiplier): int {
            $firstValue = $firstMovement[$sortBy] ?? '';
            $secondValue = $secondMovement[$sortBy] ?? '';

            if (in_array($sortBy, ['id', 'stock_delta'], true)) {
                return (((int) $firstValue) <=> ((int) $secondValue)) * $sortMultiplier;
            }

            return strnatcasecmp((string) $firstValue, (string) $secondValue) * $sortMultiplier;
        });

        $requestedPageSize = (int) ($queryParams['per_page'] ?? self::DEFAULT_PAGE_SIZE);
        $pageSize = in_array($requestedPageSize, self::PAGE_SIZE_OPTIONS, true)
            ? $requestedPageSize
            : self::DEFAULT_PAGE_SIZE;

        $totalItems = count($movements);
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $movements = array_slice($movements, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($movements), $totalItems) : 0;

        $basePath = '/painel/livraria/movimentacoes';
        $baseQuery = [
            'per_page' => $pageSize,
            'sort' => $sortBy,
            'dir' => $sortDirection,
            'type_filter' => $typeFilter,
        ];

        if ($searchTerm !== '') {
            $baseQuery['q'] = $searchTerm;
        }

        $sortLinks = [];
        foreach (self::SORT_FIELDS as $field) {
            $nextDirection = $sortBy === $field && $sortDirection === 'asc' ? 'desc' : 'asc';
            $indicator = '↕';

            if ($sortBy === $field) {
                $indicator = $sortDirection === 'asc' ? '↑' : '↓';
            }

            $sortLinks[$field] = [
                'url' => $basePath . '?' . http_build_query([
                    'page' => 1,
                    'per_page' => $pageSize,
                    'sort' => $field,
                    'dir' => $nextDirection,
                    'q' => $searchTerm,
                    'type_filter' => $typeFilter,
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

        return $this->renderPage($response, 'pages/admin-bookshop-stock-movements.twig', [
            'bookshop_stock_movements' => $movements,
            'bookshop_stock_movements_search' => $searchTerm,
            'bookshop_stock_movements_filters' => [
                'type_filter' => $typeFilter,
            ],
            'bookshop_stock_movements_sort_links' => $sortLinks,
            'bookshop_stock_movements_pagination' => [
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
            'admin_status' => $status,
            'admin_stock_movement_feedback' => [
                'movement_id' => $movementId,
                'book_title' => $flashBookTitle,
                'stock_quantity' => $flashStockQuantity,
            ],
            'page_title' => 'Movimentações de estoque | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/movimentacoes',
            'page_description' => 'Histórico de entradas e ajustes de estoque da livraria do CEDE.',
        ]);
    }
}
