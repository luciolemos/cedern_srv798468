<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FaqParticipationPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $this->renderPage($response, 'pages/faq-category.twig', [
            'faq_category_slug' => 'participacao',
            'page_title' => 'FAQ Participação | CEDE',
            'page_url' => 'https://cedern.org/faq/participacao',
            'page_description' => 'Perguntas frequentes sobre participação e integração nas atividades do CEDE.',
        ]);
    }
}
