<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Agenda\AgendaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AgendaDetailPageAction extends AbstractPageAction
{
    private AgendaRepository $agendaRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, AgendaRepository $agendaRepository)
    {
        parent::__construct($logger, $twig);
        $this->agendaRepository = $agendaRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $slug = (string) ($request->getAttribute('slug') ?? '');

        if ($slug === '') {
            return $response->withHeader('Location', '/agenda')->withStatus(302);
        }

        $agendaEvent = null;

        try {
            $agendaEvent = $this->agendaRepository->findPublishedBySlug($slug);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar detalhe da agenda no banco.', [
                'slug' => $slug,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($agendaEvent === null) {
            return $response->withHeader('Location', '/agenda')->withStatus(302);
        }

        $title = (string) ($agendaEvent['title'] ?? 'Atividade do CEDE');
        $pageDescription = (string) ($agendaEvent['description'] ?? 'Detalhes da atividade da agenda do CEDE.');

        return $this->renderPage($response, 'pages/agenda-detail.twig', [
            'agenda_event' => $agendaEvent,
            'page_title' => $title . ' | CEDE',
            'page_url' => 'https://cedern.org/agenda/' . $slug,
            'page_description' => $pageDescription,
        ]);
    }
}
