<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AgendaPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {

        return $this->renderPage($response, 'pages/agenda.twig', [
            'page_title' => 'Agenda | CEDE',
            'page_url' => 'https://cedern.org/agenda',
            'page_description' => 'Confira o cronograma semanal de atividades e reuniões públicas do CEDE.',
        ]);
    }
}
