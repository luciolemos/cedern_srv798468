<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPatrimonyCategoryToggleStatusAction extends AbstractAdminPatrimonyAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminPatrimonyCategoryListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $this->redirectTo($request, $response, $this->categoryListPath());
        }

        try {
            $category = $this->patrimonyRepository->findCategoryByIdForAdmin($id);
            if ($category === null) {
                $this->storeSessionFlash(AdminPatrimonyCategoryListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $this->redirectTo($request, $response, $this->categoryListPath());
            }

            $currentIsActive = ((int) ($category['is_active'] ?? 0)) === 1;
            $newIsActive = !$currentIsActive;

            $this->patrimonyRepository->setCategoryActive($id, $newIsActive);

            $this->storeSessionFlash(AdminPatrimonyCategoryListPageAction::FLASH_KEY, [
                'status' => $newIsActive ? 'enabled' : 'disabled',
            ]);

            return $this->redirectTo($request, $response, $this->categoryListPath());
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao alternar status da categoria patrimonial.', [
                'category_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(AdminPatrimonyCategoryListPageAction::FLASH_KEY, [
                'status' => 'toggle-error',
            ]);

            return $this->redirectTo($request, $response, $this->categoryListPath());
        }
    }
}
