<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class AdminLibraryBookFormPageAction extends AbstractAdminLibraryAction
{
    private const FLASH_KEY_PREFIX = 'admin_library_book_form_';

    public function __invoke(Request $request, Response $response): Response
    {
        $idRaw = $request->getAttribute('id');
        $bookId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $bookId !== null && $bookId > 0;

        $existingBook = null;
        if ($isEdit) {
            $existingBook = $this->libraryRepository->findBookByIdForAdmin($bookId);

            if ($existingBook === null) {
                $this->storeSessionFlash(AdminLibraryBookListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(303);
            }
        }

        $categories = [];
        try {
            $categories = $this->libraryRepository->findAllCategoriesForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar categorias para formulário de livros.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $formPath = $this->resolveFormPath($bookId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($bookId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($response, $existingBook, $submittedPayload, $errors, $categories);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        $existingPdfPath = (string) ($existingBook['pdf_path'] ?? '');
        $existingPdfMimeType = (string) ($existingBook['pdf_mime_type'] ?? '');
        $existingPdfSizeBytes = (int) ($existingBook['pdf_size_bytes'] ?? 0);
        $existingCoverImagePath = (string) ($existingBook['cover_image_path'] ?? '');
        $existingCoverImageMimeType = (string) ($existingBook['cover_image_mime_type'] ?? '');
        $existingCoverImageSizeBytes = (int) ($existingBook['cover_image_size_bytes'] ?? 0);
        $removeCoverImageRequested = !empty($body['remove_cover_image']);

        $payload['pdf_path'] = $existingPdfPath;
        $payload['pdf_mime_type'] = $existingPdfMimeType !== '' ? $existingPdfMimeType : null;
        $payload['pdf_size_bytes'] = $existingPdfSizeBytes > 0 ? $existingPdfSizeBytes : null;
        $payload['cover_image_path'] = $existingCoverImagePath;
        $payload['cover_image_mime_type'] = $existingCoverImageMimeType !== '' ? $existingCoverImageMimeType : null;
        $payload['cover_image_size_bytes'] = $existingCoverImageSizeBytes > 0 ? $existingCoverImageSizeBytes : null;
        $payload['remove_cover_image'] = $removeCoverImageRequested;

        if ($removeCoverImageRequested) {
            $payload['cover_image_path'] = '';
            $payload['cover_image_mime_type'] = null;
            $payload['cover_image_size_bytes'] = null;
        }

        $newPdfPath = '';
        $newCoverImagePath = '';
        $pdfUploadAttempted = false;
        $pdfUploadFailed = false;
        $uploadedFiles = $request->getUploadedFiles();
        $pdfUpload = $uploadedFiles['pdf_file'] ?? null;
        $coverUpload = $uploadedFiles['cover_image_file'] ?? null;

        if ($pdfUpload instanceof UploadedFileInterface && $pdfUpload->getError() !== UPLOAD_ERR_NO_FILE) {
            $pdfUploadAttempted = true;
            $uploadResult = $this->storeBookPdf($pdfUpload);

            if (!empty($uploadResult['error'])) {
                $pdfUploadFailed = true;
                $errors[] = (string) $uploadResult['error'];
            } else {
                $newPdfPath = (string) ($uploadResult['path'] ?? '');
                $payload['pdf_path'] = $newPdfPath;
                $payload['pdf_mime_type'] = (string) ($uploadResult['mime_type'] ?? 'application/pdf');
                $payload['pdf_size_bytes'] = (int) ($uploadResult['size_bytes'] ?? 0);
            }
        }

        if ($coverUpload instanceof UploadedFileInterface && $coverUpload->getError() !== UPLOAD_ERR_NO_FILE) {
            $coverUploadResult = $this->storeBookCover($coverUpload);

            if (!empty($coverUploadResult['error'])) {
                $errors[] = (string) $coverUploadResult['error'];
            } else {
                $removeCoverImageRequested = false;
                $payload['remove_cover_image'] = false;
                $newCoverImagePath = (string) ($coverUploadResult['path'] ?? '');
                $payload['cover_image_path'] = $newCoverImagePath;
                $payload['cover_image_mime_type'] = (string) ($coverUploadResult['mime_type'] ?? 'image/jpeg');
                $payload['cover_image_size_bytes'] = (int) ($coverUploadResult['size_bytes'] ?? 0);
            }
        }

        if ((string) $payload['pdf_path'] === '' && (!$pdfUploadAttempted || !$pdfUploadFailed)) {
            $errors[] = 'Envie o PDF do livro.';
        }

        if (!empty($errors)) {
            if ($newPdfPath !== '') {
                $this->deleteStoredPdfIfManaged($newPdfPath);
            }
            if ($newCoverImagePath !== '') {
                $this->deleteStoredBookCoverIfManaged($newCoverImagePath);
            }

            $flashPayload = $payload;
            unset(
                $flashPayload['pdf_path'],
                $flashPayload['pdf_mime_type'],
                $flashPayload['pdf_size_bytes'],
                $flashPayload['cover_image_path'],
                $flashPayload['cover_image_mime_type'],
                $flashPayload['cover_image_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($bookId), [
                'payload' => $flashPayload,
                'errors' => $errors,
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }

        try {
            if ($isEdit) {
                $this->libraryRepository->updateBook($bookId, $payload);

                if ($newPdfPath !== '' && $existingPdfPath !== '' && $existingPdfPath !== $newPdfPath) {
                    $this->deleteStoredPdfIfManaged($existingPdfPath);
                }
                if (
                    ($newCoverImagePath !== '' || $removeCoverImageRequested)
                    && $existingCoverImagePath !== ''
                    && $existingCoverImagePath !== $newCoverImagePath
                ) {
                    $this->deleteStoredBookCoverIfManaged($existingCoverImagePath);
                }

                $this->storeSessionFlash(AdminLibraryBookListPageAction::FLASH_KEY, [
                    'status' => 'updated',
                ]);

                return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(303);
            }

            $newId = $this->libraryRepository->createBook($payload);

            if ($newId <= 0) {
                if ($newPdfPath !== '') {
                    $this->deleteStoredPdfIfManaged($newPdfPath);
                }
                if ($newCoverImagePath !== '') {
                    $this->deleteStoredBookCoverIfManaged($newCoverImagePath);
                }

                $flashPayload = $payload;
                unset(
                    $flashPayload['pdf_path'],
                    $flashPayload['pdf_mime_type'],
                    $flashPayload['pdf_size_bytes'],
                    $flashPayload['cover_image_path'],
                    $flashPayload['cover_image_mime_type'],
                    $flashPayload['cover_image_size_bytes']
                );

                $this->storeSessionFlash($this->resolveFlashKey($bookId), [
                    'payload' => $flashPayload,
                    'errors' => ['Não foi possível salvar o livro. Verifique a conexão com banco.'],
                ]);

                return $response->withHeader('Location', $formPath)->withStatus(303);
            }

            $this->storeSessionFlash(AdminLibraryBookListPageAction::FLASH_KEY, [
                'status' => 'created',
            ]);

            return $response->withHeader('Location', '/painel/biblioteca/livros')->withStatus(303);
        } catch (\Throwable $exception) {
            if ($newPdfPath !== '') {
                $this->deleteStoredPdfIfManaged($newPdfPath);
            }
            if ($newCoverImagePath !== '') {
                $this->deleteStoredBookCoverIfManaged($newCoverImagePath);
            }

            $this->logger->warning('Falha ao salvar livro da biblioteca no admin.', [
                'error' => $exception->getMessage(),
                'book_id' => $bookId,
            ]);

            $flashPayload = $payload;
            unset(
                $flashPayload['pdf_path'],
                $flashPayload['pdf_mime_type'],
                $flashPayload['pdf_size_bytes'],
                $flashPayload['cover_image_path'],
                $flashPayload['cover_image_mime_type'],
                $flashPayload['cover_image_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($bookId), [
                'payload' => $flashPayload,
                'errors' => ['Erro ao salvar. Verifique se o slug já existe e tente novamente.'],
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }
    }

    private function resolveFlashKey(?int $bookId): string
    {
        return self::FLASH_KEY_PREFIX . (($bookId !== null && $bookId > 0) ? (string) $bookId : 'new');
    }

    private function resolveFormPath(?int $bookId): string
    {
        return ($bookId !== null && $bookId > 0)
            ? '/painel/biblioteca/livros/' . $bookId . '/editar'
            : '/painel/biblioteca/livros/novo';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $slugInput = trim((string) ($input['slug'] ?? ''));
        $slug = $this->slugify($slugInput !== '' ? $slugInput : $title);
        $status = trim((string) ($input['status'] ?? 'draft'));

        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }

        $publicationYear = trim((string) ($input['publication_year'] ?? ''));
        $pageCount = trim((string) ($input['page_count'] ?? ''));

        return [
            'category_id' => (int) ($input['category_id'] ?? 0),
            'slug' => $slug,
            'title' => $title,
            'subtitle' => trim((string) ($input['subtitle'] ?? '')),
            'author_name' => trim((string) ($input['author_name'] ?? '')),
            'organizer_name' => trim((string) ($input['organizer_name'] ?? '')),
            'translator_name' => trim((string) ($input['translator_name'] ?? '')),
            'publisher_name' => trim((string) ($input['publisher_name'] ?? '')),
            'publication_city' => trim((string) ($input['publication_city'] ?? '')),
            'publication_year' => ctype_digit($publicationYear) ? (int) $publicationYear : null,
            'edition_label' => trim((string) ($input['edition_label'] ?? '')),
            'isbn' => trim((string) ($input['isbn'] ?? '')),
            'page_count' => ctype_digit($pageCount) ? (int) $pageCount : null,
            'language' => trim((string) ($input['language'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'status' => $status,
            'cover_image_path' => '',
            'cover_image_mime_type' => null,
            'cover_image_size_bytes' => null,
            'remove_cover_image' => !empty($input['remove_cover_image']),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ((int) ($payload['category_id'] ?? 0) <= 0) {
            $errors[] = 'Selecione uma categoria válida.';
        }

        if ((string) ($payload['title'] ?? '') === '') {
            $errors[] = 'Título do livro é obrigatório.';
        }

        if ((string) ($payload['slug'] ?? '') === '') {
            $errors[] = 'Slug do livro é obrigatório.';
        }

        if ((string) ($payload['author_name'] ?? '') === '') {
            $errors[] = 'Autor principal é obrigatório.';
        }

        if (!in_array((string) ($payload['status'] ?? ''), ['draft', 'published'], true)) {
            $errors[] = 'Selecione um status válido.';
        }

        $publicationYear = $payload['publication_year'] ?? null;
        if ($publicationYear !== null && ((int) $publicationYear < 1400 || (int) $publicationYear > ((int) date('Y') + 2))) {
            $errors[] = 'Ano de publicação inválido.';
        }

        $pageCount = $payload['page_count'] ?? null;
        if ($pageCount !== null && (int) $pageCount <= 0) {
            $errors[] = 'Quantidade de páginas inválida.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $existingBook
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     * @param array<int, array<string, mixed>> $categories
     */
    private function renderForm(
        Response $response,
        ?array $existingBook,
        array $submittedPayload,
        array $errors,
        array $categories
    ): Response {
        $isEdit = $existingBook !== null;

        $form = [
            'category_id' => (string) ($submittedPayload['category_id'] ?? ($existingBook['category_id'] ?? '')),
            'slug' => $submittedPayload['slug'] ?? ($existingBook['slug'] ?? ''),
            'title' => $submittedPayload['title'] ?? ($existingBook['title'] ?? ''),
            'subtitle' => $submittedPayload['subtitle'] ?? ($existingBook['subtitle'] ?? ''),
            'author_name' => $submittedPayload['author_name'] ?? ($existingBook['author_name'] ?? ''),
            'organizer_name' => $submittedPayload['organizer_name'] ?? ($existingBook['organizer_name'] ?? ''),
            'translator_name' => $submittedPayload['translator_name'] ?? ($existingBook['translator_name'] ?? ''),
            'publisher_name' => $submittedPayload['publisher_name'] ?? ($existingBook['publisher_name'] ?? ''),
            'publication_city' => $submittedPayload['publication_city'] ?? ($existingBook['publication_city'] ?? ''),
            'publication_year' => array_key_exists('publication_year', $submittedPayload)
                ? (string) ($submittedPayload['publication_year'] ?? '')
                : (string) ($existingBook['publication_year'] ?? ''),
            'edition_label' => $submittedPayload['edition_label'] ?? ($existingBook['edition_label'] ?? ''),
            'isbn' => $submittedPayload['isbn'] ?? ($existingBook['isbn'] ?? ''),
            'page_count' => array_key_exists('page_count', $submittedPayload)
                ? (string) ($submittedPayload['page_count'] ?? '')
                : (string) ($existingBook['page_count'] ?? ''),
            'language' => $submittedPayload['language'] ?? ($existingBook['language'] ?? ''),
            'description' => $submittedPayload['description'] ?? ($existingBook['description'] ?? ''),
            'status' => $submittedPayload['status'] ?? ($existingBook['status'] ?? 'draft'),
            'pdf_path' => $existingBook['pdf_path'] ?? '',
            'pdf_url' => $existingBook['pdf_url'] ?? '',
            'pdf_size_label' => $existingBook['pdf_size_label'] ?? '',
            'cover_image_path' => $existingBook['cover_image_path'] ?? '',
            'cover_image_url' => $existingBook['cover_image_url'] ?? '',
            'remove_cover_image' => !empty($submittedPayload['remove_cover_image']),
        ];

        $categoryOptions = array_map(static function (array $category) use ($form): array {
            $statusLabel = ((int) ($category['is_active'] ?? 0) === 1) ? 'Ativa' : 'Inativa';

            return [
                'value' => (string) ($category['id'] ?? ''),
                'label' => trim((string) ($category['name'] ?? 'Categoria')) . ' (' . $statusLabel . ')',
                'selected' => (string) ($category['id'] ?? '') === (string) $form['category_id'],
            ];
        }, $categories);

        return $this->renderPage($response, 'pages/admin-library-book-form.twig', [
            'library_book_form' => $form,
            'library_book_form_errors' => $errors,
            'library_book_form_is_edit' => $isEdit,
            'library_book_id' => $existingBook['id'] ?? null,
            'library_book_categories' => $categoryOptions,
            'page_title' => ($isEdit ? 'Editar livro' : 'Novo livro') . ' | Dashboard',
            'page_url' => 'https://cedern.org/painel/biblioteca/livros',
            'page_description' => 'Formulário do dashboard para cadastro de livros da biblioteca.',
        ]);
    }
}
