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
            return $this->redirect($response, '/painel/categorias?status=invalid-id');
        }

        try {
            $category = $this->agendaRepository->findCategoryByIdForAdmin($id);

            if ($category === null) {
                return $this->redirect($response, '/painel/categorias?status=not-found');
            }

            $currentIsActive = ((int) ($category['is_active'] ?? 0)) === 1;
            $newIsActive = !$currentIsActive;

            $this->agendaRepository->setCategoryActive($id, $newIsActive);

            return $this->redirect(
                $response,
                '/painel/categorias?status=' . ($newIsActive ? 'enabled' : 'disabled')
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao alternar status da categoria no admin.', [
                'category_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->redirect($response, '/painel/categorias?status=toggle-error');
        }
    }
}
