<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminCategoryFormPageAction extends AbstractAdminAgendaAction
{
    private const AUDIENCE_OPTIONS = [
        'Jovens',
        'Adultos',
        'Crianças',
        'Público interno',
        'Livre',
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $idRaw = $request->getAttribute('id');
        $categoryId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $categoryId !== null && $categoryId > 0;

        $existingCategory = null;
        if ($isEdit) {
            $existingCategory = $this->agendaRepository->findCategoryByIdForAdmin($categoryId);

            if ($existingCategory === null) {
                return $this->redirect($response, '/painel/categorias?status=not-found');
            }
        }

        if (strtoupper($request->getMethod()) !== 'POST') {
            return $this->renderForm($response, $existingCategory, [], []);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        if (!empty($errors)) {
            return $this->renderForm($response, $existingCategory, $payload, $errors);
        }

        try {
            if ($isEdit) {
                $this->agendaRepository->updateCategory($categoryId, $payload);
                return $this->redirect($response, '/painel/categorias?status=updated');
            }

            $newId = $this->agendaRepository->createCategory($payload);

            if ($newId <= 0) {
                return $this->renderForm(
                    $response,
                    null,
                    $payload,
                    ['Não foi possível salvar a categoria. Verifique a conexão com banco.']
                );
            }

            return $this->redirect($response, '/painel/categorias?status=created');
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao salvar categoria no admin.', [
                'error' => $exception->getMessage(),
                'category_id' => $categoryId,
            ]);

            return $this->renderForm(
                $response,
                $existingCategory,
                $payload,
                ['Erro ao salvar. Verifique se o slug já existe e tente novamente.']
            );
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $input): array
    {
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $slug = (string) preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = trim($slug, '-');

        $isActiveRaw = (string) ($input['is_active'] ?? '');

        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'slug' => $slug,
            'audience_default' => $this->normalizeAudience((string) ($input['audience_default'] ?? '')),
            'color' => trim((string) ($input['color'] ?? '')),
            'icon' => trim((string) ($input['icon'] ?? '')),
            'is_active' => $isActiveRaw === '1' ? 1 : ($isActiveRaw === '0' ? 0 : -1),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    protected function validatePayload(array $payload): array
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
            'audience_default' => $submittedPayload['audience_default']
                ?? ($existingCategory['audience_default'] ?? ''),
            'color' => $submittedPayload['color'] ?? ($existingCategory['color'] ?? ''),
            'icon' => $submittedPayload['icon'] ?? ($existingCategory['icon'] ?? ''),
            'is_active' => array_key_exists('is_active', $submittedPayload)
                ? (string) $submittedPayload['is_active']
                : $existingIsActive,
        ];

        return $this->renderPage($response, 'pages/admin-category-form.twig', [
            'agenda_category_form' => $form,
            'agenda_category_form_errors' => $errors,
            'agenda_category_form_is_edit' => $isEdit,
            'agenda_category_id' => $existingCategory['id'] ?? null,
            'agenda_audience_options' => self::AUDIENCE_OPTIONS,
            'page_title' => ($isEdit ? 'Editar categoria' : 'Nova categoria') . ' | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/categorias',
            'page_description' => 'Formulário do dashboard para categorias da agenda.',
        ]);
    }
}
