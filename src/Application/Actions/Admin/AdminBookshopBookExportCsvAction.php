<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopBookExportCsvAction extends AbstractAdminBookshopAction
{
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

    private const EXPORT_HEADERS = [
        'sku',
        'slug',
        'title',
        'subtitle',
        'author_name',
        'collection_name',
        'volume_number',
        'volume_label',
        'category_name',
        'genre_name',
        'publisher_name',
        'isbn',
        'barcode',
        'edition_label',
        'publication_year',
        'page_count',
        'language',
        'description',
        'sale_price',
        'stock_minimum',
        'stock_quantity',
        'status',
        'location_label',
        'cover_image_path',
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
            $this->logger->warning('Falha ao exportar o acervo da livraria.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
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
        }

        if (in_array($stockFilter, ['ok', 'low', 'out'], true)) {
            $books = array_values(array_filter(
                $books,
                static fn (array $book): bool => (string) ($book['stock_state'] ?? '') === $stockFilter
            ));
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

        $handle = fopen('php://temp', 'w+');
        if (!is_resource($handle)) {
            $response->getBody()->write('Falha ao preparar exportacao.');

            return $response->withStatus(500);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::EXPORT_HEADERS, ';');

        foreach ($books as $book) {
            $row = [
                (string) ($book['sku'] ?? ''),
                (string) ($book['slug'] ?? ''),
                (string) ($book['title'] ?? ''),
                (string) ($book['subtitle'] ?? ''),
                (string) ($book['author_name'] ?? ''),
                (string) ($book['collection_name'] ?? ''),
                $this->normalizeIntegerField($book['volume_number'] ?? null),
                (string) ($book['volume_label'] ?? ''),
                (string) ($book['category_name'] ?? ''),
                (string) ($book['genre_name'] ?? ''),
                (string) ($book['publisher_name'] ?? ''),
                (string) ($book['isbn'] ?? ''),
                (string) ($book['barcode'] ?? ''),
                (string) ($book['edition_label'] ?? ''),
                $this->normalizeIntegerField($book['publication_year'] ?? null),
                $this->normalizeIntegerField($book['page_count'] ?? null),
                (string) ($book['language'] ?? ''),
                (string) ($book['description'] ?? ''),
                $this->normalizeDecimalField($book['sale_price'] ?? null),
                $this->normalizeIntegerField($book['stock_minimum'] ?? null),
                $this->normalizeIntegerField($book['stock_quantity'] ?? null),
                (string) ($book['status'] ?? ''),
                (string) ($book['location_label'] ?? ''),
                (string) ($book['cover_image_path'] ?? ''),
            ];

            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $body = stream_get_contents($handle) ?: '';
        fclose($handle);

        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('America/Fortaleza')))->format('Ymd-His');
        $filename = 'acervo-livraria-' . $timestamp . '.csv';

        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
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

    private function normalizeDecimalField(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

        return number_format($numeric, 2, '.', '');
    }

    private function normalizeIntegerField(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) (int) $value;
    }
}
