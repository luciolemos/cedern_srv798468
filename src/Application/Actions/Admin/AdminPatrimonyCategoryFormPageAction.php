<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPatrimonyCategoryFormPageAction extends AbstractAdminPatrimonyAction
{
    private const FLASH_KEY_PREFIX = 'admin_patrimony_category_form_';

    public function __invoke(Request $request, Response $response): Response
    {
        $idRaw = $request->getAttribute('id');
        $categoryId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $categoryId !== null && $categoryId > 0;

        $existingCategory = null;
        if ($isEdit) {
            $existingCategory = $this->patrimonyRepository->findCategoryByIdForAdmin($categoryId);

            if ($existingCategory === null) {
                $this->storeSessionFlash(AdminPatrimonyCategoryListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $this->redirectTo($request, $response, $this->categoryListPath());
            }
        }

        $formPath = $this->categoryFormPath($categoryId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($categoryId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($request, $response, $existingCategory, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        if (!empty($errors)) {
            $this->storeSessionFlash($this->resolveFlashKey($categoryId), [
                'payload' => $payload,
                'errors' => $errors,
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }

        try {
            if ($isEdit) {
                $this->patrimonyRepository->updateCategory($categoryId, $payload);

                $this->storeSessionFlash(AdminPatrimonyCategoryListPageAction::FLASH_KEY, [
                    'status' => 'updated',
                ]);

                return $this->redirectTo($request, $response, $this->categoryListPath());
            }

            $newId = $this->patrimonyRepository->createCategory($payload);
            if ($newId <= 0) {
                $this->storeSessionFlash($this->resolveFlashKey($categoryId), [
                    'payload' => $payload,
                    'errors' => ['Não foi possível salvar a categoria. Verifique a conexão com banco.'],
                ]);

                return $this->redirectTo($request, $response, $formPath);
            }

            $this->storeSessionFlash(AdminPatrimonyCategoryListPageAction::FLASH_KEY, [
                'status' => 'created',
            ]);

            return $this->redirectTo($request, $response, $this->categoryListPath());
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao salvar categoria patrimonial.', [
                'category_id' => $categoryId,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash($this->resolveFlashKey($categoryId), [
                'payload' => $payload,
                'errors' => ['Erro ao salvar. Verifique se o slug já existe e tente novamente.'],
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }
    }

    private function resolveFlashKey(?int $categoryId): string
    {
        return self::FLASH_KEY_PREFIX . (($categoryId !== null && $categoryId > 0) ? (string) $categoryId : 'new');
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
            'color' => trim((string) ($input['color'] ?? '')),
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
            $errors[] = 'Nome da categoria é obrigatório.';
        }

        if ((string) ($payload['slug'] ?? '') === '') {
            $errors[] = 'Slug da categoria é obrigatório.';
        }

        if (!in_array((int) ($payload['is_active'] ?? -1), [0, 1], true)) {
            $errors[] = 'Selecione o status da categoria.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $existingCategory
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(Request $request, Response $response, ?array $existingCategory, array $submittedPayload, array $errors): Response
    {
        $isEdit = $existingCategory !== null;
        $existingIsActive = array_key_exists('is_active', (array) $existingCategory)
            ? (string) ((int) $existingCategory['is_active'])
            : '';

        $form = [
            'name' => $submittedPayload['name'] ?? ($existingCategory['name'] ?? ''),
            'slug' => $submittedPayload['slug'] ?? ($existingCategory['slug'] ?? ''),
            'description' => $submittedPayload['description'] ?? ($existingCategory['description'] ?? ''),
            'color' => $submittedPayload['color'] ?? ($existingCategory['color'] ?? ''),
            'is_active' => array_key_exists('is_active', $submittedPayload)
                ? (string) $submittedPayload['is_active']
                : $existingIsActive,
        ];

        return $this->renderPage($response, 'pages/admin-patrimony-category-form.twig', [
            'patrimony_category_form' => $form,
            'patrimony_category_form_errors' => $errors,
            'patrimony_category_form_is_edit' => $isEdit,
            'patrimony_category_id' => $existingCategory['id'] ?? null,
            'page_title' => ($isEdit ? 'Editar categoria patrimonial' : 'Nova categoria patrimonial') . ' | Dashboard',
            'page_url' => $this->absoluteUrl(
                $request,
                $this->categoryFormPath($isEdit ? (int) ($existingCategory['id'] ?? 0) : null)
            ),
            'page_description' => 'Formulário do dashboard para categorias do controle patrimonial do CEDE.',
        ]);
    }
}
