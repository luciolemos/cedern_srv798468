<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PublicLecturesPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $study = $homeContent['studiesPages']['palestras'] ?? [];

        return $this->renderPage($response, 'pages/study-detail.twig', [
            'study' => $study,
            'page_title' => 'Palestras Públicas | CEDE',
            'page_url' => 'https://cedern.org/estudos/palestras',
            'page_description' => 'Saiba como funcionam as palestras públicas do CEDE e como participar.',
        ]);
    }
}
