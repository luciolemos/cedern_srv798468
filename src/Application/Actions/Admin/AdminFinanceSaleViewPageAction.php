<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceSaleViewPageAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        if ($id <= 0) {
            $this->storeSessionFlash(AdminFinanceSalesPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/financas')->withStatus(303);
        }

        $sale = $this->bookshopRepository->findSaleByIdForAdmin($id);
        if ($sale === null) {
            $this->storeSessionFlash(AdminFinanceSalesPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/financas')->withStatus(303);
        }

        return $this->renderPage($response, 'pages/admin-bookshop-sale-view.twig', [
            'bookshop_sale' => $sale,
            'admin_status' => '',
            'bookshop_sale_dashboard_kicker' => 'Finanças',
            'bookshop_sale_dashboard_title' => 'Resumo financeiro da venda',
            'bookshop_sale_dashboard_lead' => 'Consulta consolidada do cliente, dos itens, do pagamento e do status da operação.',
            'bookshop_sale_back_url' => '/painel',
            'bookshop_sale_back_label' => 'Voltar ao painel',
            'bookshop_sale_list_url' => '/painel/financas',
            'bookshop_sale_list_label' => 'Vendas e cancelamentos',
            'bookshop_sale_show_inventory_link' => false,
            'bookshop_sale_show_cancel_button' => false,
            'bookshop_sale_show_create_button' => false,
            'page_title' => 'Venda ' . (string) ($sale['sale_code'] ?? '') . ' | Finanças',
            'page_url' => 'https://cedern.org/painel/financas/vendas/' . $id,
            'page_description' => 'Resumo financeiro de uma venda da livraria do CEDE.',
        ]);
    }
}
