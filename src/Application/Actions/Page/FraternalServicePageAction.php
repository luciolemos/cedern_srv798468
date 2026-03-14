<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FraternalServicePageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $study = $homeContent['studiesPages']['atendimento-fraterno'] ?? [];

        return $this->renderPage($response, 'pages/study-detail.twig', [
            'study' => $study,
            'page_title' => 'Atendimento Fraterno | CEDE',
            'page_url' => 'https://cedern.org/estudos/atendimento-fraterno',
            'page_description' => 'Entenda como funciona o atendimento fraterno no CEDE.',
        ]);
    }
}
