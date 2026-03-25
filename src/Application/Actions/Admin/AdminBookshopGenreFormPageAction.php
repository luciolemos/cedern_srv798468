<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopGenreFormPageAction extends AbstractAdminBookshopAction
{
    private const FLASH_KEY_PREFIX = 'admin_bookshop_genre_form_';

    public function __invoke(Request $request, Response $response): Response
    {
        $idRaw = $request->getAttribute('id');
        $genreId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $genreId !== null && $genreId > 0;

        $existingGenre = null;
        if ($isEdit) {
            $existingGenre = $this->bookshopRepository->findGenreByIdForAdmin($genreId);

            if ($existingGenre === null) {
                $this->storeSessionFlash(AdminBookshopGenreListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/livraria/generos')->withStatus(303);
            }
        }

        $formPath = $this->resolveFormPath($genreId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($genreId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($response, $existingGenre, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        if ($errors !== []) {
            $this->storeSessionFlash($this->resolveFlashKey($genreId), [
                'payload' => $payload,
                'errors' => $errors,
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }

        try {
            if ($isEdit) {
                $this->bookshopRepository->updateGenre($genreId, $payload);
                $this->storeSessionFlash(AdminBookshopGenreListPageAction::FLASH_KEY, [
                    'status' => 'updated',
                ]);

                return $response->withHeader('Location', '/painel/livraria/generos')->withStatus(303);
            }

            $newId = $this->bookshopRepository->createGenre($payload);
            if ($newId <= 0) {
                $this->storeSessionFlash($this->resolveFlashKey($genreId), [
                    'payload' => $payload,
                'errors' => ['Não foi possível salvar o gênero literário.'],
                ]);

                return $response->withHeader('Location', $formPath)->withStatus(303);
            }

            $this->storeSessionFlash(AdminBookshopGenreListPageAction::FLASH_KEY, [
                'status' => 'created',
            ]);

            return $response->withHeader('Location', '/painel/livraria/generos')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao salvar gênero literário da livraria.', [
                'error' => $exception->getMessage(),
                'genre_id' => $genreId,
            ]);

            $this->storeSessionFlash($this->resolveFlashKey($genreId), [
                'payload' => $payload,
                'errors' => ['Erro ao salvar. Verifique se o slug já existe e tente novamente.'],
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }
    }

    private function resolveFlashKey(?int $genreId): string
    {
        return self::FLASH_KEY_PREFIX . (($genreId !== null && $genreId > 0) ? (string) $genreId : 'new');
    }

    private function resolveFormPath(?int $genreId): string
    {
        return ($genreId !== null && $genreId > 0)
            ? '/painel/livraria/generos/' . $genreId . '/editar'
            : '/painel/livraria/generos/novo';
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
            $errors[] = 'Nome do gênero literário é obrigatório.';
        }

        if ((string) ($payload['slug'] ?? '') === '') {
            $errors[] = 'Slug do gênero literário é obrigatório.';
        }

        if (!in_array((int) ($payload['is_active'] ?? -1), [0, 1], true)) {
            $errors[] = 'Selecione o status do gênero literário.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $existingGenre
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(
        Response $response,
        ?array $existingGenre,
        array $submittedPayload,
        array $errors
    ): Response {
        $isEdit = $existingGenre !== null;
        $existingIsActive = array_key_exists('is_active', (array) $existingGenre)
            ? (string) ((int) $existingGenre['is_active'])
            : '';

        $form = [
            'name' => $submittedPayload['name'] ?? ($existingGenre['name'] ?? ''),
            'slug' => $submittedPayload['slug'] ?? ($existingGenre['slug'] ?? ''),
            'description' => $submittedPayload['description'] ?? ($existingGenre['description'] ?? ''),
            'is_active' => array_key_exists('is_active', $submittedPayload)
                ? (string) $submittedPayload['is_active']
                : $existingIsActive,
        ];

        return $this->renderPage($response, 'pages/admin-bookshop-genre-form.twig', [
            'bookshop_genre_form' => $form,
            'bookshop_genre_form_errors' => $errors,
            'bookshop_genre_form_is_edit' => $isEdit,
            'bookshop_genre_id' => $existingGenre['id'] ?? null,
            'page_title' => ($isEdit ? 'Editar gênero literário da livraria' : 'Novo gênero literário da livraria') . ' | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/generos',
            'page_description' => 'Formulário do dashboard para gêneros literários do acervo da livraria.',
        ]);
    }
}
