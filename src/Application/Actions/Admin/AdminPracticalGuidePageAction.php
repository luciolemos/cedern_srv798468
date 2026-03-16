<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPracticalGuidePageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'pages/admin-practical-guide.twig', [
            'page_title' => 'Guia Prático | Painel CEDE',
            'page_url' => 'https://cedern.org/painel/guia-pratico',
            'page_description' => 'Guia operacional para uso da agenda e rotinas administrativas no painel CEDE.',
        ]);
    }
}
