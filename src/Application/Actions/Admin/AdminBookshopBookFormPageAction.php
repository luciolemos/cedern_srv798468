<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Support\BookshopTextNormalizer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class AdminBookshopBookFormPageAction extends AbstractAdminBookshopAction
{
    private const FLASH_KEY_PREFIX = 'admin_bookshop_book_form_';

    private const SKU_PATTERN = '/^CEDE-LIV-\d{4}$/';

    public function __invoke(Request $request, Response $response): Response
    {
        $idRaw = $request->getAttribute('id');
        $bookId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $bookId !== null && $bookId > 0;

        $existingBook = null;
        if ($isEdit) {
            $existingBook = $this->bookshopRepository->findBookByIdForAdmin($bookId);

            if ($existingBook === null) {
                $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
            }
        }

        $formPath = $this->resolveFormPath($bookId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($bookId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($response, $existingBook, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $payload['cost_price'] = $isEdit
            ? $this->normalizeMoneyInput($existingBook['cost_price'] ?? '0')
            : $this->normalizeMoneyInput('0');
        $payload['stock_quantity'] = $isEdit
            ? (int) ($existingBook['stock_quantity'] ?? 0)
            : 0;
        $errors = $this->validatePayload($payload);
        $existingCoverImagePath = (string) ($existingBook['cover_image_path'] ?? '');
        $existingCoverImageMimeType = (string) ($existingBook['cover_image_mime_type'] ?? '');
        $existingCoverImageSizeBytes = (int) ($existingBook['cover_image_size_bytes'] ?? 0);
        $removeCoverImageRequested = !empty($body['remove_cover_image']);

        $payload['cover_image_path'] = $existingCoverImagePath;
        $payload['cover_image_mime_type'] = $existingCoverImageMimeType !== '' ? $existingCoverImageMimeType : null;
        $payload['cover_image_size_bytes'] = $existingCoverImageSizeBytes > 0 ? $existingCoverImageSizeBytes : null;
        $payload['remove_cover_image'] = $removeCoverImageRequested;

        if ($removeCoverImageRequested) {
            $payload['cover_image_path'] = '';
            $payload['cover_image_mime_type'] = null;
            $payload['cover_image_size_bytes'] = null;
        }

        $newCoverImagePath = '';
        $uploadedFiles = $request->getUploadedFiles();
        $coverUpload = $uploadedFiles['cover_image_file'] ?? null;

        if ($coverUpload instanceof UploadedFileInterface && $coverUpload->getError() !== UPLOAD_ERR_NO_FILE) {
            $coverUploadResult = $this->storeBookshopCover($coverUpload);

            if (!empty($coverUploadResult['error'])) {
                $errors[] = (string) $coverUploadResult['error'];
            } else {
                $payload['remove_cover_image'] = false;
                $newCoverImagePath = (string) ($coverUploadResult['path'] ?? '');
                $payload['cover_image_path'] = $newCoverImagePath;
                $payload['cover_image_mime_type'] = (string) ($coverUploadResult['mime_type'] ?? 'image/jpeg');
                $payload['cover_image_size_bytes'] = (int) ($coverUploadResult['size_bytes'] ?? 0);
            }
        }

        $bookWithSameSku = $this->bookshopRepository->findBookBySku((string) $payload['sku']);
        if ($bookWithSameSku !== null && (int) ($bookWithSameSku['id'] ?? 0) !== (int) ($bookId ?? 0)) {
            $errors[] = 'Já existe um item cadastrado com este SKU.';
        }

        $isbn = trim((string) ($payload['isbn'] ?? ''));
        if ($isbn !== '') {
            $bookWithSameIsbn = $this->bookshopRepository->findBookByIsbn($isbn);
            if ($bookWithSameIsbn !== null && (int) ($bookWithSameIsbn['id'] ?? 0) !== (int) ($bookId ?? 0)) {
                $errors[] = 'Já existe um item cadastrado com este ISBN.';
            }
        }

        if (!empty($errors)) {
            if ($newCoverImagePath !== '') {
                $this->deleteStoredBookshopCoverIfManaged($newCoverImagePath);
            }

            $flashPayload = $payload;
            unset(
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
                $this->bookshopRepository->updateBook($bookId, $payload);

                if (
                    ($newCoverImagePath !== '' || $removeCoverImageRequested)
                    && $existingCoverImagePath !== ''
                    && $existingCoverImagePath !== $newCoverImagePath
                ) {
                    $this->deleteStoredBookshopCoverIfManaged($existingCoverImagePath);
                }

                $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                    'status' => 'updated',
                ]);

                return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
            }

            $newId = $this->bookshopRepository->createBook($payload);
            if ($newId <= 0) {
                if ($newCoverImagePath !== '') {
                    $this->deleteStoredBookshopCoverIfManaged($newCoverImagePath);
                }

                $flashPayload = $payload;
                unset(
                    $flashPayload['cover_image_path'],
                    $flashPayload['cover_image_mime_type'],
                    $flashPayload['cover_image_size_bytes']
                );

                $this->storeSessionFlash($this->resolveFlashKey($bookId), [
                    'payload' => $flashPayload,
                    'errors' => ['Não foi possível salvar o item do acervo.'],
                ]);

                return $response->withHeader('Location', $formPath)->withStatus(303);
            }

            $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                'status' => 'created',
            ]);

            return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
        } catch (\Throwable $exception) {
            if ($newCoverImagePath !== '') {
                $this->deleteStoredBookshopCoverIfManaged($newCoverImagePath);
            }

            $this->logger->warning('Falha ao salvar item do acervo da livraria.', [
                'error' => $exception->getMessage(),
                'book_id' => $bookId,
            ]);

            $flashPayload = $payload;
            unset(
                $flashPayload['cover_image_path'],
                $flashPayload['cover_image_mime_type'],
                $flashPayload['cover_image_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($bookId), [
                'payload' => $flashPayload,
                'errors' => ['Erro ao salvar. Verifique SKU, slug e ISBN e tente novamente.'],
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
            ? '/painel/livraria/acervo/' . $bookId . '/editar'
            : '/painel/livraria/acervo/novo';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input): array
    {
        $title = BookshopTextNormalizer::normalizeTitle((string) ($input['title'] ?? ''));
        $sku = $this->normalizeBookshopSku($input['sku'] ?? '');
        $slugInput = trim((string) ($input['slug'] ?? ''));
        $slug = $this->slugify($slugInput !== '' ? $slugInput : ($title . '-' . strtolower($sku)));
        $status = trim((string) ($input['status'] ?? 'active'));

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $publicationYear = trim((string) ($input['publication_year'] ?? ''));
        $pageCount = trim((string) ($input['page_count'] ?? ''));

        return [
            'sku' => $sku,
            'slug' => $slug,
            'category_id' => max(0, $this->normalizeIntegerInput($input['category_id'] ?? 0, 0)),
            'genre_id' => max(0, $this->normalizeIntegerInput($input['genre_id'] ?? 0, 0)),
            'collection_id' => max(0, $this->normalizeIntegerInput($input['collection_id'] ?? 0, 0)),
            'title' => $title,
            'subtitle' => trim((string) ($input['subtitle'] ?? '')),
            'author_name' => BookshopTextNormalizer::normalizeAuthorName((string) ($input['author_name'] ?? '')),
            'publisher_name' => trim((string) ($input['publisher_name'] ?? '')),
            'isbn' => trim((string) ($input['isbn'] ?? '')),
            'barcode' => trim((string) ($input['barcode'] ?? '')),
            'edition_label' => trim((string) ($input['edition_label'] ?? '')),
            'volume_number' => ctype_digit(trim((string) ($input['volume_number'] ?? '')))
                ? (int) trim((string) ($input['volume_number'] ?? ''))
                : null,
            'volume_label' => trim((string) ($input['volume_label'] ?? '')),
            'publication_year' => ctype_digit($publicationYear) ? (int) $publicationYear : null,
            'page_count' => ctype_digit($pageCount) ? (int) $pageCount : null,
            'language' => $this->normalizeBookshopLanguage($input['language'] ?? ''),
            'description' => trim((string) ($input['description'] ?? '')),
            'cost_price' => $this->normalizeMoneyInput($input['cost_price'] ?? '0'),
            'sale_price' => $this->normalizeMoneyInput($input['sale_price'] ?? '0'),
            'stock_quantity' => max(0, $this->normalizeIntegerInput($input['stock_quantity'] ?? 0, 0)),
            'stock_minimum' => max(0, $this->normalizeIntegerInput($input['stock_minimum'] ?? 0, 0)),
            'status' => $status,
            'location_label' => trim((string) ($input['location_label'] ?? '')),
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

        if ((string) ($payload['sku'] ?? '') === '') {
            $errors[] = 'SKU é obrigatório.';
        } elseif (!preg_match(self::SKU_PATTERN, (string) $payload['sku'])) {
            $errors[] = 'SKU inválido. Use o padrão CEDE-LIV-0001.';
        }

        if ((string) ($payload['slug'] ?? '') === '') {
            $errors[] = 'Slug é obrigatório.';
        }

        if ((string) ($payload['title'] ?? '') === '') {
            $errors[] = 'Título é obrigatório.';
        }

        if ((string) ($payload['author_name'] ?? '') === '') {
            $errors[] = 'Autor é obrigatório.';
        }

        if ((string) ($payload['isbn'] ?? '') === '') {
            $errors[] = 'ISBN é obrigatório.';
        }

        if ((string) ($payload['barcode'] ?? '') === '') {
            $errors[] = 'Código de barras é obrigatório.';
        }

        if (
            (int) ($payload['category_id'] ?? 0) > 0
            && $this->bookshopRepository->findCategoryByIdForAdmin((int) $payload['category_id']) === null
        ) {
            $errors[] = 'Selecione uma categoria doutrinária válida.';
        }

        if ((int) ($payload['genre_id'] ?? 0) <= 0) {
            $errors[] = 'Gênero literário é obrigatório.';
        } elseif (
            $this->bookshopRepository->findGenreByIdForAdmin((int) $payload['genre_id']) === null
        ) {
            $errors[] = 'Selecione um gênero literário válido.';
        }

        if (
            (int) ($payload['collection_id'] ?? 0) > 0
            && $this->bookshopRepository->findCollectionByIdForAdmin((int) $payload['collection_id']) === null
        ) {
            $errors[] = 'Selecione uma coleção válida.';
        }

        if (
            (int) ($payload['collection_id'] ?? 0) <= 0
            && (
                ($payload['volume_number'] ?? null) !== null
                || (string) ($payload['volume_label'] ?? '') !== ''
            )
        ) {
            $errors[] = 'Selecione uma coleção para informar volume.';
        }

        if (($payload['volume_number'] ?? null) !== null) {
            $volumeNumber = (int) $payload['volume_number'];

            if ($volumeNumber <= 0 || $volumeNumber > 999) {
                $errors[] = 'Número do volume inválido.';
            }
        }

        if (!in_array((string) ($payload['status'] ?? ''), ['active', 'inactive'], true)) {
            $errors[] = 'Selecione um status válido.';
        }

        $publicationYear = $payload['publication_year'] ?? null;
        if (
            $publicationYear !== null
            && ((int) $publicationYear < 1400 || (int) $publicationYear > ((int) date('Y') + 2))
        ) {
            $errors[] = 'Ano de publicação inválido.';
        }

        $pageCount = $payload['page_count'] ?? null;
        if ($pageCount !== null && (int) $pageCount <= 0) {
            $errors[] = 'Quantidade de páginas inválida.';
        }

        if ((float) ($payload['cost_price'] ?? 0) < 0) {
            $errors[] = 'Preço de custo inválido.';
        }

        if ((float) ($payload['sale_price'] ?? 0) < 0) {
            $errors[] = 'Preço de venda inválido.';
        }

        if ((int) ($payload['stock_minimum'] ?? 0) < 0) {
            $errors[] = 'Estoque mínimo inválido.';
        }

        return $errors;
    }

    private function normalizeBookshopSku(mixed $value): string
    {
        $sku = strtoupper(trim((string) $value));
        if ($sku === '') {
            return '';
        }

        $sku = preg_replace('/[^A-Z0-9]+/', '-', $sku) ?? $sku;
        $sku = preg_replace('/-+/', '-', $sku) ?? $sku;

        return trim($sku, '-');
    }

    /**
     * @param array<string, mixed>|null $existingBook
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(
        Response $response,
        ?array $existingBook,
        array $submittedPayload,
        array $errors
    ): Response {
        $isEdit = $existingBook !== null;
        $categories = $this->resolveCategoryOptions();
        $genres = $this->resolveGenreOptions();
        $collections = $this->resolveCollectionOptions();

        $form = [
            'sku' => $submittedPayload['sku'] ?? ($existingBook['sku'] ?? ''),
            'slug' => $submittedPayload['slug'] ?? ($existingBook['slug'] ?? ''),
            'category_id' => array_key_exists('category_id', $submittedPayload)
                ? (string) ($submittedPayload['category_id'] ?? '0')
                : (string) ($existingBook['category_id'] ?? '0'),
            'genre_id' => array_key_exists('genre_id', $submittedPayload)
                ? (string) ($submittedPayload['genre_id'] ?? '0')
                : (string) ($existingBook['genre_id'] ?? '0'),
            'collection_id' => array_key_exists('collection_id', $submittedPayload)
                ? (string) ($submittedPayload['collection_id'] ?? '0')
                : (string) ($existingBook['collection_id'] ?? '0'),
            'title' => $submittedPayload['title'] ?? ($existingBook['title'] ?? ''),
            'subtitle' => $submittedPayload['subtitle'] ?? ($existingBook['subtitle'] ?? ''),
            'author_name' => $submittedPayload['author_name'] ?? ($existingBook['author_name'] ?? ''),
            'publisher_name' => $submittedPayload['publisher_name'] ?? ($existingBook['publisher_name'] ?? ''),
            'isbn' => $submittedPayload['isbn'] ?? ($existingBook['isbn'] ?? ''),
            'barcode' => $submittedPayload['barcode'] ?? ($existingBook['barcode'] ?? ''),
            'edition_label' => $submittedPayload['edition_label'] ?? ($existingBook['edition_label'] ?? ''),
            'volume_number' => array_key_exists('volume_number', $submittedPayload)
                ? (string) ($submittedPayload['volume_number'] ?? '')
                : (string) ($existingBook['volume_number'] ?? ''),
            'volume_label' => $submittedPayload['volume_label'] ?? ($existingBook['volume_label'] ?? ''),
            'publication_year' => array_key_exists('publication_year', $submittedPayload)
                ? (string) ($submittedPayload['publication_year'] ?? '')
                : (string) ($existingBook['publication_year'] ?? ''),
            'page_count' => array_key_exists('page_count', $submittedPayload)
                ? (string) ($submittedPayload['page_count'] ?? '')
                : (string) ($existingBook['page_count'] ?? ''),
            'language' => array_key_exists('language', $submittedPayload)
                ? (string) ($submittedPayload['language'] ?? '')
                : $this->normalizeBookshopLanguage($existingBook['language'] ?? ''),
            'description' => $submittedPayload['description'] ?? ($existingBook['description'] ?? ''),
            'cost_price' => $submittedPayload['cost_price'] ?? ($existingBook['cost_price'] ?? '0.00'),
            'sale_price' => $submittedPayload['sale_price'] ?? ($existingBook['sale_price'] ?? '0.00'),
            'stock_quantity' => (string) ($existingBook['stock_quantity'] ?? '0'),
            'stock_minimum' => array_key_exists('stock_minimum', $submittedPayload)
                ? (string) ($submittedPayload['stock_minimum'] ?? '0')
                : (string) ($existingBook['stock_minimum'] ?? '0'),
            'status' => $submittedPayload['status'] ?? ($existingBook['status'] ?? 'active'),
            'location_label' => $submittedPayload['location_label'] ?? ($existingBook['location_label'] ?? ''),
            'cover_image_path' => $existingBook['cover_image_path'] ?? '',
            'cover_image_url' => $existingBook['cover_image_url'] ?? '',
            'remove_cover_image' => !empty($submittedPayload['remove_cover_image']),
            'inventory_value_label' => $existingBook['inventory_value_label'] ?? '',
            'potential_revenue_label' => $existingBook['potential_revenue_label'] ?? '',
        ];

        $languageOptions = $this->resolveLanguageOptions((string) $form['language']);

        return $this->renderPage($response, 'pages/admin-bookshop-book-form.twig', [
            'bookshop_book_form' => $form,
            'bookshop_book_form_errors' => $errors,
            'bookshop_book_form_is_edit' => $isEdit,
            'bookshop_book_id' => $existingBook['id'] ?? null,
            'bookshop_book_categories' => $categories,
            'bookshop_book_genres' => $genres,
            'bookshop_book_collections' => $collections,
            'bookshop_book_language_options' => $languageOptions,
            'page_title' => ($isEdit ? 'Editar item do acervo' : 'Novo item do acervo') . ' | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/acervo',
            'page_description' => 'Formulário do acervo da livraria no dashboard.',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCategoryOptions(): array
    {
        try {
            $categories = $this->bookshopRepository->findAllCategoriesForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar categorias doutrinárias da livraria para o formulário do acervo.', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        return array_map(static function (array $category): array {
            $label = (string) ($category['name'] ?? 'Categoria doutrinária');
            if ((int) ($category['is_active'] ?? 0) !== 1) {
                $label .= ' (inativa)';
            }

            return [
                'id' => (int) ($category['id'] ?? 0),
                'label' => $label,
            ];
        }, $categories);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveGenreOptions(): array
    {
        try {
            $genres = $this->bookshopRepository->findAllGenresForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar gêneros literários da livraria para o formulário do acervo.', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        return array_map(static function (array $genre): array {
            $label = (string) ($genre['name'] ?? 'Gênero literário');
            if ((int) ($genre['is_active'] ?? 0) !== 1) {
                $label .= ' (inativo)';
            }

            return [
                'id' => (int) ($genre['id'] ?? 0),
                'label' => $label,
            ];
        }, $genres);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCollectionOptions(): array
    {
        try {
            $collections = $this->bookshopRepository->findAllCollectionsForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar coleções da livraria para o formulário do acervo.', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        return array_map(static function (array $collection): array {
            $label = (string) ($collection['name'] ?? 'Coleção');
            if ((int) ($collection['is_active'] ?? 0) !== 1) {
                $label .= ' (inativa)';
            }

            return [
                'id' => (int) ($collection['id'] ?? 0),
                'label' => $label,
            ];
        }, $collections);
    }

    /**
     * @return array<int, string>
     */
    private function resolveLanguageOptions(string $currentLanguage): array
    {
        $options = $this->getBookshopLanguageOptions();
        $normalizedCurrentLanguage = trim($currentLanguage);

        if ($normalizedCurrentLanguage !== '' && !in_array($normalizedCurrentLanguage, $options, true)) {
            array_unshift($options, $normalizedCurrentLanguage);
        }

        return $options;
    }
}
