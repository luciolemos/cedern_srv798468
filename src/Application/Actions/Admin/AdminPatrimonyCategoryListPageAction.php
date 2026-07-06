<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPatrimonyCategoryListPageAction extends AbstractAdminPatrimonyAction
{
    public const FLASH_KEY = 'admin_patrimony_category_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private const ALL_PAGE_SIZE = 'all';

    private const SORT_FIELDS = ['id', 'name', 'slug', 'is_active'];

    public function __invoke(Request $request, Response $response): Response
    {
        $categories = [];

        try {
            $categories = $this->patrimonyRepository->findAllCategoriesForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao listar categorias patrimoniais.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);
            $categories = array_values(array_filter(
                $categories,
                static function (array $category) use ($normalizedSearch): bool {
                    $activeLabel = ((int) ($category['is_active'] ?? 0)) === 1 ? 'ativa' : 'inativa';
                    $haystack = implode(' ', [
                        (string) ($category['name'] ?? ''),
                        (string) ($category['slug'] ?? ''),
                        (string) ($category['description'] ?? ''),
                        $activeLabel,
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        $sortBy = (string) ($queryParams['sort'] ?? 'name');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'name';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($categories, static function (array $firstCategory, array $secondCategory) use ($sortBy, $sortMultiplier): int {
            $firstValue = (string) ($firstCategory[$sortBy] ?? '');
            $secondValue = (string) ($secondCategory[$sortBy] ?? '');

            if ($sortBy === 'id' || $sortBy === 'is_active') {
                return (((int) $firstValue <=> (int) $secondValue) * $sortMultiplier);
            }

            return strnatcasecmp($firstValue, $secondValue) * $sortMultiplier;
        });

        $pagination = $this->buildPagination(
            $categories,
            $queryParams,
            $this->withBasePath($request, $this->categoryListPath()),
            $sortBy,
            $sortDirection
        );
        $categories = $pagination['items'];

        return $this->renderPage($response, 'pages/admin-patrimony-categories.twig', [
            'patrimony_categories' => $categories,
            'admin_status' => $status,
            'patrimony_categories_sort_links' => $pagination['sort_links'],
            'patrimony_categories_search' => $searchTerm,
            'patrimony_categories_pagination' => $pagination['meta'],
            'page_title' => 'Categorias patrimoniais | Dashboard',
            'page_url' => $this->absoluteUrl($request, $this->categoryListPath()),
            'page_description' => 'Painel para gestão das categorias do controle patrimonial do CEDE.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $queryParams
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   sort_links: array<string, array<string, mixed>>,
     *   meta: array<string, mixed>
     * }
     */
    private function buildPagination(
        array $items,
        array $queryParams,
        string $basePath,
        string $sortBy,
        string $sortDirection
    ): array {
        $totalItems = count($items);
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
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
        $pagedItems = array_slice($items, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($pagedItems), $totalItems) : 0;
        $pageSizeQueryValue = $showAllItems ? self::ALL_PAGE_SIZE : (string) $pageSize;

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

        return [
            'items' => $pagedItems,
            'sort_links' => $sortLinks,
            'meta' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'page_size' => $pageSizeQueryValue,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'links' => $paginationLinks,
                'previous_url' => $currentPage > 1 ? $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $currentPage - 1])) : null,
                'next_url' => $currentPage < $totalPages ? $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $currentPage + 1])) : null,
                'page_size_options' => $pageSizeOptions,
            ],
        ];
    }
}
