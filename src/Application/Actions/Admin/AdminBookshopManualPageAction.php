<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopManualPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'pages/admin-bookshop-manual.twig', [
            'page_title' => 'Guia Operacional da Livraria | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/manual',
            'page_description' => 'Manual operacional da livraria física e do PDV administrativo do CEDE.',
        ]);
    }
}
