<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ContactPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'pages/contact.twig', [
            'page_title' => 'Contato | CEDE',
            'page_url' => 'https://cedern.org/contato',
            'page_description' => 'Veja o endereço, mapa e canais de contato do CEDE.',
        ]);
    }
}
