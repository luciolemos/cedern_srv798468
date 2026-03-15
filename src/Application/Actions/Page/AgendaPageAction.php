<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Agenda\AgendaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AgendaPageAction extends AbstractPageAction
{
    private AgendaRepository $agendaRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, AgendaRepository $agendaRepository)
    {
        parent::__construct($logger, $twig);
        $this->agendaRepository = $agendaRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $events = [];
        $featuredEvents = [];
        $regularEvents = [];

        try {
            $events = $this->agendaRepository->findUpcomingPublished(30);
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
            'page_title' => 'Agenda | CEDE',
            'page_url' => 'https://cedern.org/agenda',
            'page_description' => 'Confira o cronograma semanal de atividades e reuniões públicas do CEDE.',
        ]);
    }
}
