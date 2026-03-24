<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopBookListPageAction extends AbstractAdminBookshopAction
{
    public const FLASH_KEY = 'admin_bookshop_book_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 25, 50, 100];

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

        try {
            $books = $this->bookshopRepository->findAllBooksForAdmin();
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

        $sortBy = (string) ($queryParams['sort'] ?? 'title');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'title';
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

        $requestedPageSize = (int) ($queryParams['per_page'] ?? self::DEFAULT_PAGE_SIZE);
        $pageSize = in_array($requestedPageSize, self::PAGE_SIZE_OPTIONS, true)
            ? $requestedPageSize
            : self::DEFAULT_PAGE_SIZE;

        $totalItems = count($books);
        $totalUnits = array_reduce(
            $books,
            static fn (int $carry, array $book): int => $carry + (int) ($book['stock_quantity'] ?? 0),
            0
        );
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $books = array_slice($books, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($books), $totalItems) : 0;

        $basePath = '/painel/livraria/acervo';
        $baseQuery = [
            'per_page' => $pageSize,
            'sort' => $sortBy,
            'dir' => $sortDirection,
            'q' => $searchTerm,
            'status_filter' => $statusFilter,
            'stock_filter' => $stockFilter,
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

        return $this->renderPage($response, 'pages/admin-bookshop-books.twig', [
            'bookshop_books' => $books,
            'admin_status' => $status,
            'bookshop_books_sort_links' => $sortLinks,
            'bookshop_books_search' => $searchTerm,
            'bookshop_books_filters' => [
                'status_filter' => $statusFilter,
                'stock_filter' => $stockFilter,
            ],
            'bookshop_books_pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'total_units' => $totalUnits,
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
            'page_title' => 'Acervo da Livraria | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/acervo',
            'page_description' => 'Gestão administrativa do acervo da livraria do CEDE.',
        ]);
    }
}
