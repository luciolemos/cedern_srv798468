<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopBookDeleteAction extends AbstractAdminBookshopAction
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

        try {
            $book = $this->bookshopRepository->findBookByIdForAdmin($id);
            if ($book === null) {
                $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
            }

            $this->bookshopRepository->deleteBook($id);
            $this->deleteStoredBookshopCoverIfManaged((string) ($book['cover_image_path'] ?? ''));

            $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                'status' => 'deleted',
            ]);
        } catch (\Throwable $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'foreign key')
                ? 'delete-blocked'
                : 'delete-error';

            $this->logger->warning('Falha ao excluir item do acervo da livraria.', [
                'error' => $exception->getMessage(),
                'book_id' => $id,
            ]);

            $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                'status' => $status,
            ]);
        }

        return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
    }
}
