<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EadePageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $study = $homeContent['studiesPages']['eade'] ?? [];

        return $this->renderPage($response, 'pages/study-detail.twig', [
            'study' => $study,
            'page_title' => 'EADE | CEDE',
            'page_url' => 'https://cedern.org/estudos/eade',
            'page_description' => 'Conheça o Estudo Aprofundado da Doutrina Espírita (EADE) no CEDE.',
        ]);
    }
}
