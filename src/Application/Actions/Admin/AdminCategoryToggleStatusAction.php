<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminCategoryToggleStatusAction extends AbstractAdminAgendaAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminCategoryListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/categorias')->withStatus(303);
        }

        try {
            $category = $this->agendaRepository->findCategoryByIdForAdmin($id);

            if ($category === null) {
                $this->storeSessionFlash(AdminCategoryListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/categorias')->withStatus(303);
            }

            $currentIsActive = ((int) ($category['is_active'] ?? 0)) === 1;
            $newIsActive = !$currentIsActive;

            $this->agendaRepository->setCategoryActive($id, $newIsActive);

            $this->storeSessionFlash(AdminCategoryListPageAction::FLASH_KEY, [
                'status' => $newIsActive ? 'enabled' : 'disabled',
            ]);

            return $response->withHeader('Location', '/painel/categorias')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao alternar status da categoria no admin.', [
                'category_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(AdminCategoryListPageAction::FLASH_KEY, [
                'status' => 'toggle-error',
            ]);

            return $response->withHeader('Location', '/painel/categorias')->withStatus(303);
        }
    }
}
