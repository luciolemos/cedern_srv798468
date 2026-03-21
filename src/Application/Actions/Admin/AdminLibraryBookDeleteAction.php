<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminLibraryBookDeleteAction extends AbstractAdminLibraryAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminLibraryBookListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(303);
        }

        try {
            $book = $this->libraryRepository->findBookByIdForAdmin($id);

            if ($book === null) {
                $this->storeSessionFlash(AdminLibraryBookListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(303);
            }

            $this->libraryRepository->deleteBook($id);
            $this->deleteStoredPdfIfManaged((string) ($book['pdf_path'] ?? ''));
            $this->deleteStoredBookCoverIfManaged((string) ($book['cover_image_path'] ?? ''));

            $this->storeSessionFlash(AdminLibraryBookListPageAction::FLASH_KEY, [
                'status' => 'deleted',
            ]);

            return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao excluir livro da biblioteca no admin.', [
                'book_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(AdminLibraryBookListPageAction::FLASH_KEY, [
                'status' => 'delete-error',
            ]);

            return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(303);
        }
    }
}
