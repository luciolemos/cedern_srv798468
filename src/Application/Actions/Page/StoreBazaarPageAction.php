<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StoreBazaarPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $response = $this->renderPage($response, 'pages/store-bazaar.twig', [
            'page_title' => 'Bazar | Loja | CEDE',
            'page_url' => 'https://cedern.org/loja/bazar',
            'page_description' => 'O Bazar do CEDE está em preparação e ainda não foi disponibilizado no site.',
        ]);

        return $response->withStatus(404);
    }
}
