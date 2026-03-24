<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopCollectionFormPageAction extends AbstractAdminBookshopAction
{
    private const FLASH_KEY_PREFIX = 'admin_bookshop_collection_form_';

    public function __invoke(Request $request, Response $response): Response
    {
        $idRaw = $request->getAttribute('id');
        $collectionId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $collectionId !== null && $collectionId > 0;

        $existingCollection = null;
        if ($isEdit) {
            $existingCollection = $this->bookshopRepository->findCollectionByIdForAdmin($collectionId);

            if ($existingCollection === null) {
                $this->storeSessionFlash(AdminBookshopCollectionListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/livraria/colecoes')->withStatus(303);
            }
        }

        $formPath = $this->resolveFormPath($collectionId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($collectionId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($response, $existingCollection, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        if ($errors !== []) {
            $this->storeSessionFlash($this->resolveFlashKey($collectionId), [
                'payload' => $payload,
                'errors' => $errors,
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }

        try {
            if ($isEdit) {
                $this->bookshopRepository->updateCollection($collectionId, $payload);
                $this->storeSessionFlash(AdminBookshopCollectionListPageAction::FLASH_KEY, [
                    'status' => 'updated',
                ]);

                return $response->withHeader('Location', '/painel/livraria/colecoes')->withStatus(303);
            }

            $newId = $this->bookshopRepository->createCollection($payload);
            if ($newId <= 0) {
                $this->storeSessionFlash($this->resolveFlashKey($collectionId), [
                    'payload' => $payload,
                    'errors' => ['Não foi possível salvar a coleção.'],
                ]);

                return $response->withHeader('Location', $formPath)->withStatus(303);
            }

            $this->storeSessionFlash(AdminBookshopCollectionListPageAction::FLASH_KEY, [
                'status' => 'created',
            ]);

            return $response->withHeader('Location', '/painel/livraria/colecoes')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao salvar coleção da livraria.', [
                'error' => $exception->getMessage(),
                'collection_id' => $collectionId,
            ]);

            $this->storeSessionFlash($this->resolveFlashKey($collectionId), [
                'payload' => $payload,
                'errors' => ['Erro ao salvar. Verifique se o slug já existe e tente novamente.'],
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }
    }

    private function resolveFlashKey(?int $collectionId): string
    {
        return self::FLASH_KEY_PREFIX . (($collectionId !== null && $collectionId > 0) ? (string) $collectionId : 'new');
    }

    private function resolveFormPath(?int $collectionId): string
    {
        return ($collectionId !== null && $collectionId > 0)
            ? '/painel/livraria/colecoes/' . $collectionId . '/editar'
            : '/painel/livraria/colecoes/nova';
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
            $errors[] = 'Nome da coleção é obrigatório.';
        }

        if ((string) ($payload['slug'] ?? '') === '') {
            $errors[] = 'Slug da coleção é obrigatório.';
        }

        if (!in_array((int) ($payload['is_active'] ?? -1), [0, 1], true)) {
            $errors[] = 'Selecione o status da coleção.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $existingCollection
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(
        Response $response,
        ?array $existingCollection,
        array $submittedPayload,
        array $errors
    ): Response {
        $isEdit = $existingCollection !== null;
        $existingIsActive = array_key_exists('is_active', (array) $existingCollection)
            ? (string) ((int) $existingCollection['is_active'])
            : '';

        $form = [
            'name' => $submittedPayload['name'] ?? ($existingCollection['name'] ?? ''),
            'slug' => $submittedPayload['slug'] ?? ($existingCollection['slug'] ?? ''),
            'description' => $submittedPayload['description'] ?? ($existingCollection['description'] ?? ''),
            'is_active' => array_key_exists('is_active', $submittedPayload)
                ? (string) $submittedPayload['is_active']
                : $existingIsActive,
        ];

        return $this->renderPage($response, 'pages/admin-bookshop-collection-form.twig', [
            'bookshop_collection_form' => $form,
            'bookshop_collection_form_errors' => $errors,
            'bookshop_collection_form_is_edit' => $isEdit,
            'bookshop_collection_id' => $existingCollection['id'] ?? null,
            'page_title' => ($isEdit ? 'Editar coleção da livraria' : 'Nova coleção da livraria') . ' | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/colecoes',
            'page_description' => 'Formulário do dashboard para coleções e séries do acervo da livraria.',
        ]);
    }
}
