<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopBookListPageAction extends AbstractAdminBookshopAction
{
    public const FLASH_KEY = 'admin_bookshop_book_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private const ALL_PAGE_SIZE = 'all';

    private const PAGINATION_VISIBLE_RADIUS = 1;

    private const SORT_FIELDS = [
        'title',
        'author_name',
        'sku',
        'genre_name',
        'category_name',
        'isbn',
        'barcode',
        'stock_quantity',
        'sale_price',
        'status',
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];
        $categories = [];
        $genres = [];

        try {
            $books = $this->bookshopRepository->findAllBooksForAdmin();
            $categories = $this->bookshopRepository->findAllCategoriesForAdmin();
            $genres = $this->bookshopRepository->findAllGenresForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao listar acervo da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $statusFilter = trim((string) ($queryParams['status_filter'] ?? 'all'));
        $stockFilter = trim((string) ($queryParams['stock_filter'] ?? 'all'));
        $genreFilter = trim((string) ($queryParams['genre_filter'] ?? 'all'));
        $categoryFilter = trim((string) ($queryParams['category_filter'] ?? 'all'));
        $shelfFilter = strtoupper(trim((string) ($queryParams['shelf_filter'] ?? 'all')));
        $levelFilter = trim((string) ($queryParams['level_filter'] ?? 'all'));

        $genreOptions = $this->buildNamedFilterOptions($genres);
        $categoryOptions = $this->buildNamedFilterOptions($categories);
        $genreFilter = $this->resolveNamedFilterValue($genreFilter, $genreOptions);
        $categoryFilter = $this->resolveNamedFilterValue($categoryFilter, $categoryOptions);
        $shelfOptions = $this->buildShelfFilterOptions();
        $levelOptions = $this->buildLevelFilterOptions();
        $shelfFilter = $this->resolveSimpleFilterValue($shelfFilter, $shelfOptions);
        $levelFilter = $this->resolveSimpleFilterValue($levelFilter, $levelOptions);

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $books = array_values(array_filter(
                $books,
                static function (array $book) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($book['sku'] ?? ''),
                        (string) ($book['title'] ?? ''),
                        (string) ($book['subtitle'] ?? ''),
                        (string) ($book['author_name'] ?? ''),
                        (string) ($book['publisher_name'] ?? ''),
                        (string) ($book['isbn'] ?? ''),
                        (string) ($book['barcode'] ?? ''),
                        (string) ($book['category_name'] ?? ''),
                        (string) ($book['genre_name'] ?? ''),
                        (string) ($book['collection_name'] ?? ''),
                        (string) ($book['volume_number'] ?? ''),
                        (string) ($book['volume_label'] ?? ''),
                        (string) ($book['location_label'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        if (in_array($statusFilter, ['active', 'inactive'], true)) {
            $books = array_values(array_filter(
                $books,
                static fn (array $book): bool => (string) ($book['status'] ?? '') === $statusFilter
            ));
        } else {
            $statusFilter = 'all';
        }

        if (in_array($stockFilter, ['ok', 'low', 'out'], true)) {
            $books = array_values(array_filter(
                $books,
                static fn (array $book): bool => (string) ($book['stock_state'] ?? '') === $stockFilter
            ));
        } else {
            $stockFilter = 'all';
        }

        if ($genreFilter !== 'all') {
            $normalizedGenreFilter = strtolower($genreFilter);
            $books = array_values(array_filter(
                $books,
                static fn (array $book): bool => strtolower(trim((string) ($book['genre_name'] ?? ''))) === $normalizedGenreFilter
            ));
        }

        if ($categoryFilter !== 'all') {
            $normalizedCategoryFilter = strtolower($categoryFilter);
            $books = array_values(array_filter(
                $books,
                static fn (array $book): bool => strtolower(trim((string) ($book['category_name'] ?? ''))) === $normalizedCategoryFilter
            ));
        }

        if ($shelfFilter !== 'all' || $levelFilter !== 'all') {
            $books = array_values(array_filter(
                $books,
                function (array $book) use ($shelfFilter, $levelFilter): bool {
                    $location = $this->parseLocationLabel((string) ($book['location_label'] ?? ''));

                    if ($shelfFilter !== 'all' && $location['shelf'] !== $shelfFilter) {
                        return false;
                    }

                    if ($levelFilter !== 'all' && $location['level'] !== $levelFilter) {
                        return false;
                    }

                    return true;
                }
            ));
        }

        $sortBy = (string) ($queryParams['sort'] ?? 'sku');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'sku';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($books, static function (array $firstBook, array $secondBook) use ($sortBy, $sortMultiplier): int {
            $firstValue = $firstBook[$sortBy] ?? '';
            $secondValue = $secondBook[$sortBy] ?? '';

            if (in_array($sortBy, ['stock_quantity', 'sale_price'], true)) {
                $comparison = (float) $firstValue <=> (float) $secondValue;

                return $comparison * $sortMultiplier;
            }

            $comparison = strnatcasecmp((string) $firstValue, (string) $secondValue);

            return $comparison * $sortMultiplier;
        });

        $totalItems = count($books);
        $totalUnits = array_reduce(
            $books,
            static fn (int $carry, array $book): int => $carry + (int) ($book['stock_quantity'] ?? 0),
            0
        );
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
        $books = array_slice($books, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($books), $totalItems) : 0;

        $basePath = '/painel/livraria/acervo';
        $baseQuery = [
            'per_page' => $showAllItems ? self::ALL_PAGE_SIZE : $pageSize,
            'sort' => $sortBy,
            'dir' => $sortDirection,
            'q' => $searchTerm,
            'status_filter' => $statusFilter,
            'stock_filter' => $stockFilter,
            'genre_filter' => $genreFilter,
            'category_filter' => $categoryFilter,
            'shelf_filter' => $shelfFilter,
            'level_filter' => $levelFilter,
        ];
        $exportQuery = $baseQuery;
        unset($exportQuery['per_page']);
        $exportUrl = '/painel/livraria/acervo/exportar';
        if ($exportQuery !== []) {
            $exportUrl .= '?' . http_build_query($exportQuery);
        }

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

        $paginationLinks = $this->buildCompactPaginationLinks($currentPage, $totalPages, static function (int $page) use ($basePath, $baseQuery): string {
            return $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $page]));
        });

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

        return $this->renderPage($response, 'pages/admin-bookshop-books.twig', [
            'bookshop_books' => $books,
            'admin_status' => $status,
            'bookshop_books_sort_links' => $sortLinks,
            'bookshop_books_search' => $searchTerm,
            'bookshop_books_filters' => [
                'status_filter' => $statusFilter,
                'stock_filter' => $stockFilter,
                'genre_filter' => $genreFilter,
                'category_filter' => $categoryFilter,
                'shelf_filter' => $shelfFilter,
                'level_filter' => $levelFilter,
            ],
            'bookshop_books_filter_options' => [
                'genres' => $genreOptions,
                'categories' => $categoryOptions,
                'shelves' => $shelfOptions,
                'levels' => $levelOptions,
            ],
            'bookshop_books_export_url' => $exportUrl,
            'bookshop_books_pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'total_units' => $totalUnits,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'page_size' => $showAllItems ? self::ALL_PAGE_SIZE : (string) $pageSize,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'links' => $paginationLinks,
                'previous_url' => $previousPageUrl,
                'next_url' => $nextPageUrl,
                'page_size_options' => $pageSizeOptions,
            ],
            'page_title' => 'Acervo da Livraria | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/acervo',
            'page_description' => 'Gestão administrativa do acervo da livraria do CEDE.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{label: string, value: string}>
     */
    private function buildNamedFilterOptions(array $rows): array
    {
        $labels = [];

        foreach ($rows as $row) {
            $label = trim((string) ($row['name'] ?? ''));
            if ($label === '') {
                continue;
            }

            $labels[strtolower($label)] = $label;
        }

        natcasesort($labels);

        return array_map(static fn (string $label): array => [
            'label' => $label,
            'value' => $label,
        ], array_values($labels));
    }

    /**
     * @param array<int, array{label: string, value: string}> $options
     */
    private function resolveNamedFilterValue(string $value, array $options): string
    {
        if ($value === '' || $value === 'all') {
            return 'all';
        }

        $normalized = strtolower(trim($value));
        foreach ($options as $option) {
            if (strtolower(trim($option['value'])) === $normalized) {
                return $option['value'];
            }
        }

        return 'all';
    }

    /**
     * @param array<int, array{label: string, value: string}> $options
     */
    private function resolveSimpleFilterValue(string $value, array $options): string
    {
        if ($value === '' || $value === 'all') {
            return 'all';
        }

        foreach ($options as $option) {
            if (strtoupper($option['value']) === strtoupper($value)) {
                return (string) $option['value'];
            }
        }

        return 'all';
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function buildShelfFilterOptions(): array
    {
        $options = [];

        foreach (range('A', 'Z') as $letter) {
            $options[] = [
                'label' => $letter,
                'value' => $letter,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function buildLevelFilterOptions(): array
    {
        $options = [];

        foreach (range(1, 10) as $level) {
            $options[] = [
                'label' => (string) $level,
                'value' => (string) $level,
            ];
        }

        return $options;
    }

    /**
     * @return array{shelf: string, level: string}
     */
    private function parseLocationLabel(string $label): array
    {
        $normalized = trim($label);
        if ($normalized === '') {
            return ['shelf' => '', 'level' => ''];
        }

        if (preg_match('/^([a-z])\s*[-\/]?\s*([0-9]{1,2})$/i', $normalized, $matches) === 1) {
            return [
                'shelf' => strtoupper($matches[1]),
                'level' => ltrim($matches[2], '0') !== '' ? ltrim($matches[2], '0') : '0',
            ];
        }

        $shelf = '';
        $level = '';

        if (preg_match('/estante\s*([a-z0-9]+)/i', $normalized, $shelfMatch) === 1) {
            $shelf = strtoupper($shelfMatch[1]);
        }

        if (preg_match('/prateleira\s*([0-9]{1,2})/i', $normalized, $levelMatch) === 1) {
            $level = ltrim($levelMatch[1], '0') !== '' ? ltrim($levelMatch[1], '0') : '0';
        }

        return [
            'shelf' => $shelf,
            'level' => $level,
        ];
    }

    /**
     * @param callable(int): string $buildUrl
     * @return array<int, array<string, mixed>>
     */
    private function buildCompactPaginationLinks(int $currentPage, int $totalPages, callable $buildUrl): array
    {
        if ($totalPages <= 0) {
            return [];
        }

        $pages = [1, $totalPages];

        for ($page = $currentPage - self::PAGINATION_VISIBLE_RADIUS; $page <= $currentPage + self::PAGINATION_VISIBLE_RADIUS; $page++) {
            if ($page >= 1 && $page <= $totalPages) {
                $pages[] = $page;
            }
        }

        if ($currentPage <= 3) {
            $pages[] = 2;
            $pages[] = 3;
        }

        if ($currentPage >= $totalPages - 2) {
            $pages[] = $totalPages - 1;
            $pages[] = $totalPages - 2;
        }

        $pages = array_values(array_unique(array_filter($pages, static fn (int $page): bool => $page >= 1 && $page <= $totalPages)));
        sort($pages);

        $links = [];
        $previousPage = null;

        foreach ($pages as $page) {
            if ($previousPage !== null && $page - $previousPage > 1) {
                $links[] = [
                    'number' => '…',
                    'active' => false,
                    'url' => '',
                    'is_gap' => true,
                ];
            }

            $links[] = [
                'number' => $page,
                'active' => $page === $currentPage,
                'url' => $buildUrl($page),
                'is_gap' => false,
            ];

            $previousPage = $page;
        }

        return $links;
    }
}
