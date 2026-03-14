<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StudiesPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {

        return $this->renderPage($response, 'pages/studies.twig', [
            'page_title' => 'Estudos | CEDE',
            'page_url' => 'https://cedern.org/estudos',
            'page_description' => 'Participe dos estudos, palestras e atendimento fraterno do CEDE.',
        ]);
    }
}
