<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EsdePageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $study = $homeContent['studiesPages']['esde'] ?? [];

        return $this->renderPage($response, 'pages/study-detail.twig', [
            'study' => $study,
            'page_title' => 'ESDE | CEDE',
            'page_url' => 'https://cedern.org/estudos/esde',
            'page_description' => 'Conheça o Estudo Sistematizado da Doutrina Espírita (ESDE) no CEDE.',
        ]);
    }
}
