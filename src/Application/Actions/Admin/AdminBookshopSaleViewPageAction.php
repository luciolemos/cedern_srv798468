<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopSaleViewPageAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            $this->storeSessionFlash(AdminBookshopSaleListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/vendas')->withStatus(303);
        }

        $sale = $this->bookshopRepository->findSaleByIdForAdmin($id);
        if ($sale === null) {
            $this->storeSessionFlash(AdminBookshopSaleListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/vendas')->withStatus(303);
        }

        $flash = $this->consumeSessionFlash(AdminBookshopSaleFormPageAction::viewFlashKey($id));

        return $this->renderPage($response, 'pages/admin-bookshop-sale-view.twig', [
            'bookshop_sale' => $sale,
            'admin_status' => trim((string) ($flash['status'] ?? '')),
            'page_title' => 'Venda ' . (string) ($sale['sale_code'] ?? '') . ' | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/vendas/' . $id,
            'page_description' => 'Resumo administrativo da venda de balcão da livraria.',
        ]);
    }
}
