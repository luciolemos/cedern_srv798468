<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AgendaPublicLecturePageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $agenda = $homeContent['agendaPages']['palestra-publica'] ?? [];

        return $this->renderPage($response, 'pages/agenda-detail.twig', [
            'agenda' => $agenda,
            'page_title' => 'Palestra Pública | CEDE',
            'page_url' => 'https://cedern.org/agenda/palestra-publica',
            'page_description' => 'Detalhes da Palestra Pública na agenda semanal do CEDE.',
        ]);
    }
}
