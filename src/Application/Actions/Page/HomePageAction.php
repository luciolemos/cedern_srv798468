<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomePageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'home.twig', [
            'page_title' => 'CEDE | Centro de Estudos da Doutrina Espírita',
            'page_url' => 'https://cedern.org/',
        ]);
    }
}
