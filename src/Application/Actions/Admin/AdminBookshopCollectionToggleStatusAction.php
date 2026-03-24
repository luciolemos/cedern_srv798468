<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopCollectionToggleStatusAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminBookshopCollectionListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/colecoes')->withStatus(303);
        }

        try {
            $collection = $this->bookshopRepository->findCollectionByIdForAdmin($id);

            if ($collection === null) {
                $this->storeSessionFlash(AdminBookshopCollectionListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/livraria/colecoes')->withStatus(303);
            }

            $newIsActive = ((int) ($collection['is_active'] ?? 0)) !== 1;
            $this->bookshopRepository->setCollectionActive($id, $newIsActive);

            $this->storeSessionFlash(AdminBookshopCollectionListPageAction::FLASH_KEY, [
                'status' => $newIsActive ? 'enabled' : 'disabled',
            ]);

            return $response->withHeader('Location', '/painel/livraria/colecoes')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao alternar status da coleção da livraria.', [
                'collection_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(AdminBookshopCollectionListPageAction::FLASH_KEY, [
                'status' => 'toggle-error',
            ]);

            return $response->withHeader('Location', '/painel/livraria/colecoes')->withStatus(303);
        }
    }
}
