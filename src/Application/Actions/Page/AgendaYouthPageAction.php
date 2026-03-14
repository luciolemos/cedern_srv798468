<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AgendaYouthPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $agenda = $homeContent['agendaPages']['juventude-espirita'] ?? [];

        return $this->renderPage($response, 'pages/agenda-detail.twig', [
            'agenda' => $agenda,
            'page_title' => 'Juventude Espírita | CEDE',
            'page_url' => 'https://cedern.org/agenda/juventude-espirita',
            'page_description' => 'Detalhes da atividade Juventude Espírita do CEDE.',
        ]);
    }
}
