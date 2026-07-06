<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceSectionPlaceholderPageAction extends AbstractPageAction
{
    /**
     * @var array<string, array{label: string, page_url: string, page_description: string}>
     */
    private const SECTIONS = [
        'cantina' => [
            'label' => 'Cantina',
            'page_url' => 'https://cedern.org/painel/financas/cantina',
            'page_description' => 'Área financeira da cantina em construção no painel do CEDE.',
        ],
        'bazar' => [
            'label' => 'Bazar',
            'page_url' => 'https://cedern.org/painel/financas/bazar',
            'page_description' => 'Área financeira do bazar em construção no painel do CEDE.',
        ],
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $sectionKey = strtolower(trim((string) $request->getAttribute('section', '')));
        $section = self::SECTIONS[$sectionKey] ?? null;

        if ($section === null) {
            return $response->withHeader('Location', '/painel/financas')->withStatus(302);
        }

        return $this->renderPage($response, 'pages/admin-finance-section-placeholder.twig', [
            'finance_section_key' => $sectionKey,
            'finance_section_label' => $section['label'],
            'page_title' => $section['label'] . ' | Gestão Financeira | CEDE',
            'page_url' => $section['page_url'],
            'page_description' => $section['page_description'],
        ]);
    }
}
