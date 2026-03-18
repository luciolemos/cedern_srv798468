<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminAgendaListPageAction extends AbstractAdminAgendaAction
{
    public const FLASH_KEY = 'admin_agenda_list';

    private const DEFAULT_PAGE_SIZE = 5;

    private const PAGE_SIZE_OPTIONS = [5, 10, 25, 50, 100];

    private const SORT_FIELDS = ['id', 'title', 'category_name', 'starts_at', 'status', 'is_featured', 'slug'];

    public function __invoke(Request $request, Response $response): Response
    {
        $events = [];

        try {
            $events = $this->agendaRepository->findAllForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao listar agenda no admin.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);
            $statusSearchTokens = [
                'draft' => ['draft', 'rascunho'],
                'published' => ['published', 'publicado'],
                'cancelled' => ['cancelled', 'cancelado'],
            ];

            $events = array_values(array_filter(
                $events,
                static function (array $event) use ($normalizedSearch, $statusSearchTokens): bool {
                    $featuredLabel = ((int) ($event['is_featured'] ?? 0) === 1) ? 'sim' : 'não';
                    $eventStatus = (string) ($event['status'] ?? '');
                    $statusTerms = $statusSearchTokens[$eventStatus] ?? [$eventStatus];
                    $haystack = implode(' ', [
                        (string) ($event['title'] ?? ''),
                        (string) ($event['category_name'] ?? ''),
                        (string) ($event['slug'] ?? ''),
                        (string) ($event['status'] ?? ''),
                        implode(' ', $statusTerms),
                        (string) ($event['starts_at_label'] ?? ''),
                        (string) ($event['starts_at'] ?? ''),
                        $featuredLabel,
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        $sortBy = (string) ($queryParams['sort'] ?? 'starts_at');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'starts_at';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($events, static function (array $firstEvent, array $secondEvent) use ($sortBy, $sortMultiplier): int {
            $firstValue = (string) ($firstEvent[$sortBy] ?? '');
            $secondValue = (string) ($secondEvent[$sortBy] ?? '');

            if ($sortBy === 'id' || $sortBy === 'is_featured') {
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

        $totalItems = count($events);
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $events = array_slice($events, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($events), $totalItems) : 0;

        $basePath = '/painel/eventos';
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

        return $this->renderPage($response, 'pages/admin-agenda.twig', [
            'agenda_events' => $events,
            'admin_status' => $status,
            'agenda_sort_links' => $sortLinks,
            'agenda_search' => $searchTerm,
            'agenda_pagination' => [
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
            'page_title' => 'Dashboard Agenda | CEDE',
            'page_url' => 'https://cedern.org/painel/eventos',
            'page_description' => 'Painel do dashboard para gestão de eventos da agenda.',
        ]);
    }
}
