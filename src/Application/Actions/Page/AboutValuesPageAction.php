<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutValuesPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $about = $homeContent['aboutPages']['valores'] ?? [];

        return $this->renderPage($response, 'pages/about-detail.twig', [
            'about' => $about,
            'page_title' => 'Valores | CEDE',
            'page_url' => 'https://cedern.org/quem-somos/valores',
            'page_description' => 'Conheça os valores que orientam as atividades do CEDE.',
        ]);
    }
}
