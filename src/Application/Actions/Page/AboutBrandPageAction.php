<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutBrandPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'pages/about-brand.twig', [
            'page_title' => 'Nossa Marca | CEDE',
            'page_url' => 'https://cedern.org/quem-somos/nossa-marca',
            'page_description' => 'Descrição simbólica da marca do CEDE e dos elementos '
                . 'visuais que expressam sua missão institucional.',
        ]);
    }
}
