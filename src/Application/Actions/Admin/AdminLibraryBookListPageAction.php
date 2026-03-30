<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminLibraryBookListPageAction extends AbstractAdminLibraryAction
{
    public const FLASH_KEY = 'admin_library_book_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private const ALL_PAGE_SIZE = 'all';

    private const SORT_FIELDS = ['title', 'author_name', 'publisher_name', 'publication_year', 'category_name', 'status'];

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];

        try {
            $books = $this->libraryRepository->findAllBooksForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao listar livros da biblioteca no admin.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $books = array_values(array_filter(
                $books,
                static function (array $book) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($book['title'] ?? ''),
                        (string) ($book['subtitle'] ?? ''),
                        (string) ($book['author_name'] ?? ''),
                        (string) ($book['publisher_name'] ?? ''),
                        (string) ($book['isbn'] ?? ''),
                        (string) ($book['category_name'] ?? ''),
                        (string) ($book['status_label'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
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

            if ($sortBy === 'publication_year') {
                $comparison = (int) $firstValue <=> (int) $secondValue;

                return $comparison * $sortMultiplier;
            }

            $comparison = strnatcasecmp((string) $firstValue, (string) $secondValue);

            return $comparison * $sortMultiplier;
        });

        $totalItems = count($books);
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

        $pageSizeQueryValue = $showAllItems ? self::ALL_PAGE_SIZE : (string) $pageSize;
        $basePath = '/painel/biblioteca/livros';
        $baseQuery = [
            'per_page' => $pageSizeQueryValue,
            'sort' => $sortBy,
            'dir' => $sortDirection,
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
                    'per_page' => $pageSizeQueryValue,
                    'sort' => $field,
                    'dir' => $nextDirection,
                    'q' => $searchTerm,
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
            'url' => $basePath . '?' . http_build_query([
                'page' => 1,
                'per_page' => $option,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'q' => $searchTerm,
            ]),
        ], self::PAGE_SIZE_OPTIONS);
        $pageSizeOptions[] = [
            'value' => self::ALL_PAGE_SIZE,
            'label' => 'Todos',
            'selected' => $showAllItems,
            'url' => $basePath . '?' . http_build_query([
                'page' => 1,
                'per_page' => self::ALL_PAGE_SIZE,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'q' => $searchTerm,
            ]),
        ];

        return $this->renderPage($response, 'pages/admin-library-books.twig', [
            'library_books' => $books,
            'admin_status' => $status,
            'library_books_sort_links' => $sortLinks,
            'library_books_search' => $searchTerm,
            'library_books_pagination' => [
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
            'page_title' => 'Conteúdos da Central de conteúdo | Dashboard',
            'page_url' => 'https://cedern.org/painel/biblioteca/livros',
            'page_description' => 'Painel para gestão da central de conteúdo do CEDE.',
        ]);
    }
}
