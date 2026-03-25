<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopCategoryFormPageAction extends AbstractAdminBookshopAction
{
    private const FLASH_KEY_PREFIX = 'admin_bookshop_category_form_';

    public function __invoke(Request $request, Response $response): Response
    {
        $idRaw = $request->getAttribute('id');
        $categoryId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $categoryId !== null && $categoryId > 0;

        $existingCategory = null;
        if ($isEdit) {
            $existingCategory = $this->bookshopRepository->findCategoryByIdForAdmin($categoryId);

            if ($existingCategory === null) {
                $this->storeSessionFlash(AdminBookshopCategoryListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/livraria/categorias')->withStatus(303);
            }
        }

        $formPath = $this->resolveFormPath($categoryId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($categoryId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($response, $existingCategory, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        if (!empty($errors)) {
            $this->storeSessionFlash($this->resolveFlashKey($categoryId), [
                'payload' => $payload,
                'errors' => $errors,
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }

        try {
            if ($isEdit) {
                $this->bookshopRepository->updateCategory($categoryId, $payload);
                $this->storeSessionFlash(AdminBookshopCategoryListPageAction::FLASH_KEY, [
                    'status' => 'updated',
                ]);

                return $response->withHeader('Location', '/painel/livraria/categorias')->withStatus(303);
            }

            $newId = $this->bookshopRepository->createCategory($payload);
            if ($newId <= 0) {
                $this->storeSessionFlash($this->resolveFlashKey($categoryId), [
                    'payload' => $payload,
                'errors' => ['Não foi possível salvar a categoria doutrinária.'],
                ]);

                return $response->withHeader('Location', $formPath)->withStatus(303);
            }

            $this->storeSessionFlash(AdminBookshopCategoryListPageAction::FLASH_KEY, [
                'status' => 'created',
            ]);

            return $response->withHeader('Location', '/painel/livraria/categorias')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao salvar categoria doutrinária da livraria.', [
                'error' => $exception->getMessage(),
                'category_id' => $categoryId,
            ]);

            $this->storeSessionFlash($this->resolveFlashKey($categoryId), [
                'payload' => $payload,
                'errors' => ['Erro ao salvar. Verifique se o slug já existe e tente novamente.'],
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }
    }

    private function resolveFlashKey(?int $categoryId): string
    {
        return self::FLASH_KEY_PREFIX . (($categoryId !== null && $categoryId > 0) ? (string) $categoryId : 'new');
    }

    private function resolveFormPath(?int $categoryId): string
    {
        return ($categoryId !== null && $categoryId > 0)
            ? '/painel/livraria/categorias/' . $categoryId . '/editar'
            : '/painel/livraria/categorias/nova';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slugInput = trim((string) ($input['slug'] ?? ''));
        $slug = $this->slugify($slugInput !== '' ? $slugInput : $name);
        $isActiveRaw = (string) ($input['is_active'] ?? '');

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) ($input['description'] ?? '')),
            'is_active' => $isActiveRaw === '1' ? 1 : ($isActiveRaw === '0' ? 0 : -1),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ((string) ($payload['name'] ?? '') === '') {
            $errors[] = 'Nome da categoria doutrinária é obrigatório.';
        }

        if ((string) ($payload['slug'] ?? '') === '') {
            $errors[] = 'Slug da categoria doutrinária é obrigatório.';
        }

        if (!in_array((int) ($payload['is_active'] ?? -1), [0, 1], true)) {
            $errors[] = 'Selecione o status da categoria doutrinária.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $existingCategory
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(
        Response $response,
        ?array $existingCategory,
        array $submittedPayload,
        array $errors
    ): Response {
        $isEdit = $existingCategory !== null;
        $existingIsActive = array_key_exists('is_active', (array) $existingCategory)
            ? (string) ((int) $existingCategory['is_active'])
            : '';

        $form = [
            'name' => $submittedPayload['name'] ?? ($existingCategory['name'] ?? ''),
            'slug' => $submittedPayload['slug'] ?? ($existingCategory['slug'] ?? ''),
            'description' => $submittedPayload['description'] ?? ($existingCategory['description'] ?? ''),
            'is_active' => array_key_exists('is_active', $submittedPayload)
                ? (string) $submittedPayload['is_active']
                : $existingIsActive,
        ];

        return $this->renderPage($response, 'pages/admin-bookshop-category-form.twig', [
            'bookshop_category_form' => $form,
            'bookshop_category_form_errors' => $errors,
            'bookshop_category_form_is_edit' => $isEdit,
            'bookshop_category_id' => $existingCategory['id'] ?? null,
            'page_title' => ($isEdit ? 'Editar categoria doutrinária da livraria' : 'Nova categoria doutrinária da livraria') . ' | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/categorias',
            'page_description' => 'Formulário do dashboard para categorias doutrinárias do acervo da livraria.',
        ]);
    }
}
