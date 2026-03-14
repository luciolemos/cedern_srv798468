<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'pages/about.twig', [
            'page_title' => 'Quem Somos | CEDE',
            'page_url' => 'https://cedern.org/quem-somos',
            'page_description' => 'Conheça o CEDE, sua missão, valores e frentes de atuação '
                . 'no estudo e prática da Doutrina Espírita.',
        ]);
    }
}
