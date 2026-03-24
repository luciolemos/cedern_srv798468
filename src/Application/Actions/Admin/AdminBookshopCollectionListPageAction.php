<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopCollectionListPageAction extends AbstractAdminBookshopAction
{
    public const FLASH_KEY = 'admin_bookshop_collection_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 25, 50, 100];

    private const SORT_FIELDS = ['id', 'name', 'slug', 'is_active'];

    public function __invoke(Request $request, Response $response): Response
    {
        $collections = [];

        try {
            $collections = $this->bookshopRepository->findAllCollectionsForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao listar coleções da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $collections = array_values(array_filter(
                $collections,
                static function (array $collection) use ($normalizedSearch): bool {
                    $activeLabel = ((int) ($collection['is_active'] ?? 0) === 1) ? 'ativa' : 'inativa';
                    $haystack = implode(' ', [
                        (string) ($collection['name'] ?? ''),
                        (string) ($collection['slug'] ?? ''),
                        (string) ($collection['description'] ?? ''),
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

        usort($collections, static function (array $firstCollection, array $secondCollection) use ($sortBy, $sortMultiplier): int {
            $firstValue = (string) ($firstCollection[$sortBy] ?? '');
            $secondValue = (string) ($secondCollection[$sortBy] ?? '');

            if ($sortBy === 'id' || $sortBy === 'is_active') {
                $comparison = (int) $firstValue <=> (int) $secondValue;

                return $comparison * $sortMultiplier;
            }

            $comparison = strnatcasecmp($firstValue, $secondValue);

            return $comparison * $sortMultiplier;
        });

        $requestedPageSize = (int) ($queryParams['per_page'] ?? self::DEFAULT_PAGE_SIZE);
        $pageSize = in_array($requestedPageSize, self::PAGE_SIZE_OPTIONS, true)
            ? $requestedPageSize
            : self::DEFAULT_PAGE_SIZE;

        $totalItems = count($collections);
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $collections = array_slice($collections, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($collections), $totalItems) : 0;

        $basePath = '/painel/livraria/colecoes';
        $baseQuery = [
            'per_page' => $pageSize,
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
                    'per_page' => $pageSize,
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
            'url' => $basePath . '?' . http_build_query([
                'page' => 1,
                'per_page' => $option,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'q' => $searchTerm,
            ]),
        ], self::PAGE_SIZE_OPTIONS);

        return $this->renderPage($response, 'pages/admin-bookshop-collections.twig', [
            'bookshop_collections' => $collections,
            'admin_status' => $status,
            'bookshop_collections_sort_links' => $sortLinks,
            'bookshop_collections_search' => $searchTerm,
            'bookshop_collections_pagination' => [
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
            'page_title' => 'Coleções da Livraria | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/colecoes',
            'page_description' => 'Painel para gestão de coleções e séries do acervo da livraria do CEDE.',
        ]);
    }
}
