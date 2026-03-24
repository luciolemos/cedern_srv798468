<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopGenreToggleStatusAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminBookshopGenreListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/generos')->withStatus(303);
        }

        try {
            $genre = $this->bookshopRepository->findGenreByIdForAdmin($id);

            if ($genre === null) {
                $this->storeSessionFlash(AdminBookshopGenreListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/livraria/generos')->withStatus(303);
            }

            $newIsActive = ((int) ($genre['is_active'] ?? 0)) !== 1;
            $this->bookshopRepository->setGenreActive($id, $newIsActive);

            $this->storeSessionFlash(AdminBookshopGenreListPageAction::FLASH_KEY, [
                'status' => $newIsActive ? 'enabled' : 'disabled',
            ]);

            return $response->withHeader('Location', '/painel/livraria/generos')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao alternar status do gênero da livraria.', [
                'genre_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(AdminBookshopGenreListPageAction::FLASH_KEY, [
                'status' => 'toggle-error',
            ]);

            return $response->withHeader('Location', '/painel/livraria/generos')->withStatus(303);
        }
    }
}
