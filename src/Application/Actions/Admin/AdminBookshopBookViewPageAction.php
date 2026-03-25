<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopBookViewPageAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
        }

        $book = $this->bookshopRepository->findBookByIdForAdmin($id);

        if ($book === null) {
            $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
        }

        return $this->renderPage($response, 'pages/admin-bookshop-book-view.twig', [
            'bookshop_book' => $book,
            'page_title' => ($book['title'] ?? 'Item') . ' | Acervo | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/acervo/' . $id,
            'page_description' => 'Visualização do item do acervo da livraria.',
        ]);
    }
}
