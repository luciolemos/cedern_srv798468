<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AgendaGospelStudyPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $agenda = $homeContent['agendaPages']['estudo-do-evangelho'] ?? [];

        return $this->renderPage($response, 'pages/agenda-detail.twig', [
            'agenda' => $agenda,
            'page_title' => 'Estudo do Evangelho | CEDE',
            'page_url' => 'https://cedern.org/agenda/estudo-do-evangelho',
            'page_description' => 'Detalhes do Estudo do Evangelho na agenda semanal do CEDE.',
        ]);
    }
}
