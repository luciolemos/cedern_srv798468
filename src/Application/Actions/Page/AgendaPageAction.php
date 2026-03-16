<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Agenda\AgendaRepository;
use App\Application\Settings\SettingsInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AgendaPageAction extends AbstractPageAction
{
    private const PAGE_SIZE_OPTIONS = [5, 10, 12, 25, 50];

    private const SORT_FIELDS = ['starts_at', 'title', 'location_name', 'mode_label'];

    private AgendaRepository $agendaRepository;
    private int $publicUpcomingLimit;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        AgendaRepository $agendaRepository,
        SettingsInterface $settings
    ) {
        parent::__construct($logger, $twig);
        $this->agendaRepository = $agendaRepository;

        $agendaSettings = (array) $settings->get('agenda');
        $publicUpcomingLimit = (int) ($agendaSettings['public_upcoming_limit'] ?? 30);
        $this->publicUpcomingLimit = max(1, $publicUpcomingLimit);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $sortBy = (string) ($queryParams['sort'] ?? 'starts_at');

        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'starts_at';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $requestedPageSize = (int) ($queryParams['per_page'] ?? $this->publicUpcomingLimit);
        $pageSize = in_array($requestedPageSize, self::PAGE_SIZE_OPTIONS, true)
            ? $requestedPageSize
            : $this->publicUpcomingLimit;

        $totalEvents = 0;
        $totalPages = 1;
        $offset = 0;
        $events = [];
        $featuredEvents = [];
        $regularEvents = [];
        $allUpcomingEvents = [];

        try {
            $totalUpcomingEvents = $this->agendaRepository->countUpcomingPublished();
            if ($totalUpcomingEvents > 0) {
                $allUpcomingEvents = $this->agendaRepository->findUpcomingPublished($totalUpcomingEvents);
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Agenda dinâmica indisponível.', [
                'error' => $exception->getMessage(),
            ]);
        }

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $allUpcomingEvents = array_values(array_filter(
                $allUpcomingEvents,
                static function (array $event) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($event['title'] ?? ''),
                        (string) ($event['description'] ?? ''),
                        (string) ($event['location_name'] ?? ''),
                        (string) ($event['mode_label'] ?? ''),
                        (string) ($event['starts_at_label'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($allUpcomingEvents, static function (array $firstEvent, array $secondEvent) use ($sortBy, $sortMultiplier): int {
            $firstValue = (string) ($firstEvent[$sortBy] ?? '');
            $secondValue = (string) ($secondEvent[$sortBy] ?? '');

            if ($sortBy === 'starts_at') {
                $comparison = strtotime($firstValue) <=> strtotime($secondValue);

                return $comparison * $sortMultiplier;
            }

            $comparison = strnatcasecmp($firstValue, $secondValue);

            return $comparison * $sortMultiplier;
        });

        $totalEvents = count($allUpcomingEvents);
        $totalPages = max(1, (int) ceil($totalEvents / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $events = array_slice($allUpcomingEvents, $offset, $pageSize);

        foreach ($events as $event) {
            if ((int) ($event['is_featured'] ?? 0) === 1) {
                $featuredEvents[] = $event;
                continue;
            }

            $regularEvents[] = $event;
        }

        $startItem = $totalEvents > 0 ? $offset + 1 : 0;
        $endItem = $totalEvents > 0 ? min($offset + count($events), $totalEvents) : 0;

        $basePath = '/agenda';
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
        for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
            $paginationLinks[] = [
                'number' => $pageNumber,
                'active' => $pageNumber === $page,
                'url' => $basePath . '?' . http_build_query($baseQuery + ['page' => $pageNumber]),
            ];
        }

        $previousPageUrl = $page > 1
            ? $basePath . '?' . http_build_query($baseQuery + ['page' => $page - 1])
            : null;
        $nextPageUrl = $page < $totalPages
            ? $basePath . '?' . http_build_query($baseQuery + ['page' => $page + 1])
            : null;

        $pageSizeOptions = array_map(static fn (int $option): array => [
            'value' => $option,
            'selected' => $option === $pageSize,
        ], self::PAGE_SIZE_OPTIONS);

        $tableEvents = count($regularEvents) > 0 ? $regularEvents : $events;

        return $this->renderPage($response, 'pages/agenda.twig', [
            'agenda_events' => $events,
            'agenda_featured_events' => $featuredEvents,
            'agenda_regular_events' => $regularEvents,
            'agenda_table_events' => $tableEvents,
            'agenda_search' => $searchTerm,
            'agenda_sort_links' => $sortLinks,
            'agenda_pagination' => [
                'current_page' => $page,
                'per_page' => $pageSize,
                'total_items' => $totalEvents,
                'total_pages' => $totalPages,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'links' => $paginationLinks,
                'previous_url' => $previousPageUrl,
                'next_url' => $nextPageUrl,
                'page_size_options' => $pageSizeOptions,
            ],
            'page_title' => 'Agenda | CEDE',
            'page_url' => 'https://cedern.org/agenda',
            'page_description' => 'Confira o cronograma semanal de atividades e reuniões públicas do CEDE.',
        ]);
    }
}
