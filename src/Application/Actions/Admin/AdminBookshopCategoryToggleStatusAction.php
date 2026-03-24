<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopCategoryToggleStatusAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminBookshopCategoryListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/categorias')->withStatus(303);
        }

        try {
            $category = $this->bookshopRepository->findCategoryByIdForAdmin($id);

            if ($category === null) {
                $this->storeSessionFlash(AdminBookshopCategoryListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/livraria/categorias')->withStatus(303);
            }

            $newIsActive = ((int) ($category['is_active'] ?? 0)) !== 1;
            $this->bookshopRepository->setCategoryActive($id, $newIsActive);

            $this->storeSessionFlash(AdminBookshopCategoryListPageAction::FLASH_KEY, [
                'status' => $newIsActive ? 'enabled' : 'disabled',
            ]);

            return $response->withHeader('Location', '/painel/livraria/categorias')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao alternar status da categoria da livraria.', [
                'category_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(AdminBookshopCategoryListPageAction::FLASH_KEY, [
                'status' => 'toggle-error',
            ]);

            return $response->withHeader('Location', '/painel/livraria/categorias')->withStatus(303);
        }
    }
}
