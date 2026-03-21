<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutFounderPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $founder = $homeContent['aboutPages']['fundador'] ?? [];
        $founderPhoto = trim((string) ($founder['photo'] ?? ''));

        if ($founderPhoto !== '' && !str_starts_with($founderPhoto, '/')) {
            $founder['photo'] = '/' . ltrim($founderPhoto, '/');
        }

        $founderName = trim((string) ($founder['name'] ?? ''));
        $pageDescription = trim((string) ($founder['lead'] ?? ''));

        return $this->renderPage($response, 'pages/about-founder.twig', [
            'founder' => $founder,
            'page_title' => $founderName !== ''
                ? $founderName . ' | Nosso Fundador | CEDE'
                : 'Nosso Fundador | CEDE',
            'page_url' => 'https://cedern.org/quem-somos/fundador',
            'page_description' => $pageDescription !== ''
                ? $pageDescription
                : 'Conheça o legado espiritual e institucional que inspirou a origem do CEDE.',
        ]);
    }
}
