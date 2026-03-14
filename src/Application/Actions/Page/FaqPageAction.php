<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FaqPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'pages/faq.twig', [
            'page_title' => 'FAQ | CEDE',
            'page_url' => 'https://cedern.org/faq',
            'page_description' => 'Dúvidas frequentes sobre o Espiritismo e as atividades do CEDE.',
        ]);
    }
}
