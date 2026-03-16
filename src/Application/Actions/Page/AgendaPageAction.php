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
        $totalEvents = 0;
        $totalPages = 1;
        $offset = 0;
        $events = [];
        $featuredEvents = [];
        $regularEvents = [];

        try {
            $totalEvents = $this->agendaRepository->countUpcomingPublished();
            $totalPages = max(1, (int) ceil($totalEvents / $this->publicUpcomingLimit));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $this->publicUpcomingLimit;
            $events = $this->agendaRepository->findUpcomingPublishedPage($this->publicUpcomingLimit, $offset);
        } catch (\Throwable $exception) {
            $this->logger->warning('Agenda dinâmica indisponível.', [
                'error' => $exception->getMessage(),
            ]);
        }

        foreach ($events as $event) {
            if ((int) ($event['is_featured'] ?? 0) === 1) {
                $featuredEvents[] = $event;
                continue;
            }

            $regularEvents[] = $event;
        }

        return $this->renderPage($response, 'pages/agenda.twig', [
            'agenda_events' => $events,
            'agenda_featured_events' => $featuredEvents,
            'agenda_regular_events' => $regularEvents,
            'agenda_pagination' => [
                'current_page' => $page,
                'per_page' => $this->publicUpcomingLimit,
                'total_items' => $totalEvents,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous_page' => max(1, $page - 1),
                'next_page' => min($totalPages, $page + 1),
            ],
            'page_title' => 'Agenda | CEDE',
            'page_url' => 'https://cedern.org/agenda',
            'page_description' => 'Confira o cronograma semanal de atividades e reuniões públicas do CEDE.',
        ]);
    }
}
