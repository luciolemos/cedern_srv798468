<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopSaleCancelAction extends AbstractAdminBookshopAction
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

        $actor = $this->resolveAdminActor();

        try {
            $cancelled = $this->bookshopRepository->cancelSale($id, $actor['member_id'], $actor['member_name']);

            $this->storeSessionFlash(AdminBookshopSaleFormPageAction::viewFlashKey($id), [
                'status' => $cancelled ? 'cancelled' : 'cancel-error',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao cancelar venda da livraria.', [
                'error' => $exception->getMessage(),
                'sale_id' => $id,
            ]);

            $this->storeSessionFlash(AdminBookshopSaleFormPageAction::viewFlashKey($id), [
                'status' => 'cancel-error',
            ]);
        }

        return $response->withHeader('Location', '/painel/livraria/vendas/' . $id)->withStatus(303);
    }
}
