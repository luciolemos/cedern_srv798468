<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BookshopAutaDeSousaPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $autaPage = $homeContent['bookshopPages']['auta-de-sousa'] ?? [];
        $autaPhoto = trim((string) ($autaPage['photo'] ?? ''));

        if ($autaPhoto !== '' && !str_starts_with($autaPhoto, '/')) {
            $autaPage['photo'] = '/' . ltrim($autaPhoto, '/');
        }

        $autaName = trim((string) ($autaPage['name'] ?? ''));
        $pageDescription = trim((string) ($autaPage['lead'] ?? ''));

        return $this->renderPage($response, 'pages/bookshop-auta-de-sousa.twig', [
            'bookshop_auta' => $autaPage,
            'page_title' => $autaName !== ''
                ? $autaName . ' | Livraria | CEDE'
                : 'Livraria Auta de Sousa | CEDE',
            'page_url' => 'https://cedern.org/loja/livraria-auta-de-sousa',
            'page_description' => $pageDescription !== ''
                ? $pageDescription
                : 'Conheça a inspiração que dá nome à Livraria Auta de Sousa.',
        ]);
    }
}
