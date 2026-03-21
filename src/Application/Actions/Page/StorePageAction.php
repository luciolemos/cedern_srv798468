<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StorePageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'pages/store.twig', [
            'page_title' => 'Loja | CEDE',
            'page_url' => 'https://cedern.org/loja',
            'page_description' =>
                'Acesse a área de loja do CEDE '
                . 'e conheça as seções de bazar e livraria em preparação.',
        ]);
    }
}
