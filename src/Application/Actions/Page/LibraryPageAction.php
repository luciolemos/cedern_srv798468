<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Library\LibraryRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class LibraryPageAction extends AbstractPageAction
{
    private const PAGE_SIZE_OPTIONS = [6, 12, 24, 48];

    /**
     * @var array<string, array{field: string, direction: string, label: string}>
     */
    private const SORT_OPTIONS = [
        'title_asc' => ['field' => 'title', 'direction' => 'asc', 'label' => 'Título (A-Z)'],
        'title_desc' => ['field' => 'title', 'direction' => 'desc', 'label' => 'Título (Z-A)'],
        'author_asc' => ['field' => 'author_name', 'direction' => 'asc', 'label' => 'Autor (A-Z)'],
        'author_desc' => ['field' => 'author_name', 'direction' => 'desc', 'label' => 'Autor (Z-A)'],
        'year_desc' => ['field' => 'publication_year', 'direction' => 'desc', 'label' => 'Ano (mais recente)'],
        'year_asc' => ['field' => 'publication_year', 'direction' => 'asc', 'label' => 'Ano (mais antigo)'],
        'category_asc' => ['field' => 'category_name', 'direction' => 'asc', 'label' => 'Categoria (A-Z)'],
        'publisher_asc' => ['field' => 'publisher_name', 'direction' => 'asc', 'label' => 'Editora (A-Z)'],
    ];

    private LibraryRepository $libraryRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, LibraryRepository $libraryRepository)
    {
        parent::__construct($logger, $twig);
        $this->libraryRepository = $libraryRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];
        $categories = [];
        $basePath = rtrim((string) $request->getUri()->getPath(), '/');
        $basePath = $basePath !== '' ? $basePath : '/quem-somos/base-de-conhecimento';
        $pageUrlBase = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'), '/');

        try {
            $books = $this->libraryRepository->findPublishedBooks();
            $categories = $this->libraryRepository->findActiveCategories();
        } catch (\Throwable $exception) {
            $this->logger->warning('Base de conhecimento dinâmica indisponível.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $selectedCategory = trim((string) ($queryParams['category'] ?? ''));
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
                        (string) ($book['organizer_name'] ?? ''),
                        (string) ($book['translator_name'] ?? ''),
                        (string) ($book['publisher_name'] ?? ''),
                        (string) ($book['publication_city'] ?? ''),
                        (string) ($book['isbn'] ?? ''),
                        (string) ($book['language'] ?? ''),
                        (string) ($book['category_name'] ?? ''),
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

        $sortConfig = self::SORT_OPTIONS[$selectedSort];
        $sortField = $sortConfig['field'];
        $sortDirection = $sortConfig['direction'];
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($books, static function (array $firstBook, array $secondBook) use ($sortField, $sortMultiplier): int {
            $firstValue = $firstBook[$sortField] ?? '';
            $secondValue = $secondBook[$sortField] ?? '';

            if ($sortField === 'publication_year') {
                $comparison = (int) $firstValue <=> (int) $secondValue;

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
            'sort' => $selectedSort,
            'per_page' => $pageSize,
        ];

        $paginationLinks = [];
        for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
            $paginationLinks[] = [
                'number' => $pageNumber,
                'active' => $pageNumber === $currentPage,
                'url' => $basePath . '?' . http_build_query($baseQuery + ['page' => $pageNumber]),
            ];
        }

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

        return $this->renderPage($response, 'pages/library.twig', [
            'library_books' => $books,
            'library_categories' => $categoryOptions,
            'library_filters' => [
                'search' => $searchTerm,
                'category' => $selectedCategory,
                'sort' => $selectedSort,
                'page_size' => $pageSize,
                'base_path' => $basePath,
                'sort_options' => $sortOptions,
                'page_size_options' => $pageSizeOptions,
            ],
            'library_pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalBooks,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'links' => $paginationLinks,
                'previous_url' => $previousPageUrl,
                'next_url' => $nextPageUrl,
            ],
            'page_title' => 'Base de conhecimento | CEDE',
            'page_url' => $pageUrlBase . $basePath,
            'page_description' =>
                'Consulte a base de conhecimento do CEDE '
                . 'e acesse conteúdos em PDF para estudo, pesquisa e aprofundamento doutrinário.',
        ]);
    }
}
