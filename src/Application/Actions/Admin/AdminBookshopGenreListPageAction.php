<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopGenreListPageAction extends AbstractAdminBookshopAction
{
    public const FLASH_KEY = 'admin_bookshop_genre_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private const ALL_PAGE_SIZE = 'all';

    private const SORT_FIELDS = ['id', 'name', 'slug', 'is_active'];

    public function __invoke(Request $request, Response $response): Response
    {
        $genres = [];
        $genreBookCounts = [];

        try {
            $genres = $this->bookshopRepository->findAllGenresForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao listar gêneros literários da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $genreBookCounts = $this->bookshopRepository->findGenreBookCounts();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar contagem de títulos por gênero.', [
                'error' => $exception->getMessage(),
            ]);
        }

        if ($genres !== []) {
            foreach ($genres as &$genre) {
                $genreId = (int) ($genre['id'] ?? 0);
                $genre['book_count'] = $genreBookCounts[$genreId] ?? 0;
            }
            unset($genre);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $genres = array_values(array_filter(
                $genres,
                static function (array $genre) use ($normalizedSearch): bool {
                    $activeLabel = ((int) ($genre['is_active'] ?? 0) === 1) ? 'ativo' : 'inativo';
                    $haystack = implode(' ', [
                        (string) ($genre['name'] ?? ''),
                        (string) ($genre['slug'] ?? ''),
                        (string) ($genre['description'] ?? ''),
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

        usort($genres, static function (array $firstGenre, array $secondGenre) use ($sortBy, $sortMultiplier): int {
            $firstValue = (string) ($firstGenre[$sortBy] ?? '');
            $secondValue = (string) ($secondGenre[$sortBy] ?? '');

            if ($sortBy === 'id' || $sortBy === 'is_active') {
                $comparison = (int) $firstValue <=> (int) $secondValue;

                return $comparison * $sortMultiplier;
            }

            $comparison = strnatcasecmp($firstValue, $secondValue);

            return $comparison * $sortMultiplier;
        });

        $totalItems = count($genres);
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
        $genres = array_slice($genres, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($genres), $totalItems) : 0;

        $pageSizeQueryValue = $showAllItems ? self::ALL_PAGE_SIZE : (string) $pageSize;
        $basePath = '/painel/livraria/generos';
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

        return $this->renderPage($response, 'pages/admin-bookshop-genres.twig', [
            'bookshop_genres' => $genres,
            'admin_status' => $status,
            'bookshop_genres_sort_links' => $sortLinks,
            'bookshop_genres_search' => $searchTerm,
            'bookshop_genres_pagination' => [
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
            'page_title' => 'Gêneros Literários da Livraria | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/generos',
            'page_description' => 'Painel para gestão de gêneros literários do acervo da livraria do CEDE.',
        ]);
    }
}
