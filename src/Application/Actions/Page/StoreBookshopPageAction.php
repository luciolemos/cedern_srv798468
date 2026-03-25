<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Bookshop\BookshopRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class StoreBookshopPageAction extends AbstractPageAction
{
    private const PAGE_SIZE_OPTIONS = [12, 24, 48];

    private const PAGINATION_VISIBLE_RADIUS = 1;

    /**
     * @var array<string, array{field: string, direction: string, label: string}>
     */
    private const SORT_OPTIONS = [
        'title_asc' => ['field' => 'title', 'direction' => 'asc', 'label' => 'Título (A-Z)'],
        'author_asc' => ['field' => 'author_name', 'direction' => 'asc', 'label' => 'Autor (A-Z)'],
        'price_asc' => ['field' => 'sale_price', 'direction' => 'asc', 'label' => 'Preço (menor primeiro)'],
        'price_desc' => ['field' => 'sale_price', 'direction' => 'desc', 'label' => 'Preço (maior primeiro)'],
        'category_asc' => ['field' => 'category_name', 'direction' => 'asc', 'label' => 'Categoria (A-Z)'],
        'genre_asc' => ['field' => 'genre_name', 'direction' => 'asc', 'label' => 'Gênero (A-Z)'],
    ];

    private BookshopRepository $bookshopRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, BookshopRepository $bookshopRepository)
    {
        parent::__construct($logger, $twig);
        $this->bookshopRepository = $bookshopRepository;
    }

    protected function getTemplate(): string
    {
        return 'pages/store-bookshop.twig';
    }

    protected function getFallbackBasePath(): string
    {
        return '/loja/livraria';
    }

    protected function getPageTitle(): string
    {
        return 'Livraria | Loja | CEDE';
    }

    protected function getPageDescription(): string
    {
        return 'Consulte o catalogo publico da Livraria do CEDE. '
            . 'A venda dos livros e presencial, diretamente no balcao da casa.';
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];
        $categories = [];
        $genres = [];
        $basePath = rtrim((string) $request->getUri()->getPath(), '/');
        $basePath = $basePath !== '' ? $basePath : $this->getFallbackBasePath();
        $pageUrlBase = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'), '/');

        try {
            $books = $this->bookshopRepository->findCatalogBooks();
            $categories = $this->bookshopRepository->findCatalogCategories();
            $genres = $this->bookshopRepository->findCatalogGenres();
        } catch (\Throwable $exception) {
            $this->logger->warning('Catalogo publico da livraria indisponivel.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $selectedCategory = trim((string) ($queryParams['category'] ?? ''));
        $selectedGenre = trim((string) ($queryParams['genre'] ?? ''));
        $selectedSort = (string) ($queryParams['sort'] ?? 'title_asc');
        if (!array_key_exists($selectedSort, self::SORT_OPTIONS)) {
            $selectedSort = 'title_asc';
        }

        $requestedPageSize = (int) ($queryParams['per_page'] ?? 12);
        $pageSize = in_array($requestedPageSize, self::PAGE_SIZE_OPTIONS, true)
            ? $requestedPageSize
            : 12;

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
                        (string) ($book['barcode'] ?? ''),
                        (string) ($book['language'] ?? ''),
                        (string) ($book['category_name'] ?? ''),
                        (string) ($book['genre_name'] ?? ''),
                        (string) ($book['collection_name'] ?? ''),
                        (string) ($book['volume_number'] ?? ''),
                        (string) ($book['volume_label'] ?? ''),
                        (string) ($book['page_count'] ?? ''),
                        (string) ($book['description'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        if ($selectedCategory !== '') {
            $books = array_values(array_filter(
                $books,
                static fn (array $book): bool => (string) ($book['category_slug'] ?? '') === $selectedCategory
            ));
        }

        if ($selectedGenre !== '') {
            $books = array_values(array_filter(
                $books,
                static fn (array $book): bool => (string) ($book['genre_slug'] ?? '') === $selectedGenre
            ));
        }

        $availableTitles = count(array_filter(
            $books,
            static fn (array $book): bool => (int) ($book['stock_quantity'] ?? 0) > 0
        ));

        $books = array_map(static function (array $book): array {
            $stockState = (string) ($book['stock_state'] ?? 'ok');
            $availabilityLabel = 'Disponivel no balcao';
            $availabilityClass = 'is-live';

            if ($stockState === 'low') {
                $availabilityLabel = 'Ultimos exemplares';
                $availabilityClass = 'is-warning';
            } elseif ($stockState === 'out') {
                $availabilityLabel = 'Sob consulta';
                $availabilityClass = 'is-neutral';
            }

            return array_merge($book, [
                'store_availability_label' => $availabilityLabel,
                'store_availability_class' => $availabilityClass,
                'has_reference_price' => (float) ($book['sale_price'] ?? 0) > 0,
            ]);
        }, $books);

        $sortConfig = self::SORT_OPTIONS[$selectedSort];
        $sortField = $sortConfig['field'];
        $sortDirection = $sortConfig['direction'];
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($books, static function (array $firstBook, array $secondBook) use ($sortField, $sortMultiplier): int {
            $firstValue = $firstBook[$sortField] ?? '';
            $secondValue = $secondBook[$sortField] ?? '';

            if ($sortField === 'sale_price') {
                $comparison = (float) $firstValue <=> (float) $secondValue;

                return $comparison * $sortMultiplier;
            }

            $comparison = strnatcasecmp((string) $firstValue, (string) $secondValue);

            return $comparison * $sortMultiplier;
        });

        $totalBooks = count($books);
        $totalPages = max(1, (int) ceil($totalBooks / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $pageSize;
        $books = array_slice($books, $offset, $pageSize);

        $startItem = $totalBooks > 0 ? $offset + 1 : 0;
        $endItem = $totalBooks > 0 ? min($offset + count($books), $totalBooks) : 0;

        $baseQuery = [
            'q' => $searchTerm,
            'category' => $selectedCategory,
            'genre' => $selectedGenre,
            'sort' => $selectedSort,
            'per_page' => $pageSize,
        ];

        $paginationLinks = $this->buildCompactPaginationLinks($currentPage, $totalPages, static function (int $page) use ($basePath, $baseQuery): string {
            return $basePath . '?' . http_build_query($baseQuery + ['page' => $page]);
        });

        $previousPageUrl = $currentPage > 1
            ? $basePath . '?' . http_build_query($baseQuery + ['page' => $currentPage - 1])
            : null;
        $nextPageUrl = $currentPage < $totalPages
            ? $basePath . '?' . http_build_query($baseQuery + ['page' => $currentPage + 1])
            : null;

        $sortOptions = array_map(
            static fn (string $value, array $option): array => [
                'value' => $value,
                'label' => $option['label'],
                'selected' => $value === $selectedSort,
            ],
            array_keys(self::SORT_OPTIONS),
            self::SORT_OPTIONS
        );

        $pageSizeOptions = array_map(static fn (int $option): array => [
            'value' => $option,
            'selected' => $option === $pageSize,
        ], self::PAGE_SIZE_OPTIONS);

        $categoryOptions = array_map(static fn (array $category): array => [
            'value' => (string) ($category['slug'] ?? ''),
            'label' => (string) ($category['name'] ?? 'Categoria'),
            'selected' => (string) ($category['slug'] ?? '') === $selectedCategory,
        ], $categories);

        $genreOptions = array_map(static fn (array $genre): array => [
            'value' => (string) ($genre['slug'] ?? ''),
            'label' => (string) ($genre['name'] ?? 'Gênero'),
            'selected' => (string) ($genre['slug'] ?? '') === $selectedGenre,
        ], $genres);

        return $this->renderPage($response, $this->getTemplate(), [
            'bookshop_books' => $books,
            'bookshop_categories' => $categoryOptions,
            'bookshop_genres' => $genreOptions,
            'bookshop_filters' => [
                'search' => $searchTerm,
                'category' => $selectedCategory,
                'genre' => $selectedGenre,
                'sort' => $selectedSort,
                'page_size' => $pageSize,
                'base_path' => $basePath,
                'sort_options' => $sortOptions,
                'page_size_options' => $pageSizeOptions,
            ],
            'bookshop_pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalBooks,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'links' => $paginationLinks,
                'previous_url' => $previousPageUrl,
                'next_url' => $nextPageUrl,
            ],
            'bookshop_catalog_stats' => [
                'available_titles' => $availableTitles,
            ],
            'page_title' => $this->getPageTitle(),
            'page_url' => $pageUrlBase . $basePath,
            'page_description' => $this->getPageDescription(),
        ]);
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
