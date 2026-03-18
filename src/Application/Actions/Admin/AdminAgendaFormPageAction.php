<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminAgendaFormPageAction extends AbstractAdminAgendaAction
{
    private const FLASH_KEY_PREFIX = 'admin_agenda_form_';

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
        $eventId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $eventId !== null && $eventId > 0;

        $categories = $this->agendaRepository->findAllCategoriesForAdmin();

        $existingEvent = null;
        if ($isEdit) {
            $existingEvent = $this->agendaRepository->findByIdForAdmin($eventId);

            if ($existingEvent === null) {
                $this->storeSessionFlash(AdminAgendaListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $response->withHeader('Location', '/painel/eventos')->withStatus(303);
            }
        }

        $formPath = $this->resolveFormPath($eventId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($eventId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($response, $categories, $existingEvent, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        if (!empty($errors)) {
            $this->storeSessionFlash($this->resolveFlashKey($eventId), [
                'payload' => $payload,
                'errors' => $errors,
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }

        try {
            if ($isEdit) {
                $this->agendaRepository->updateEvent($eventId, $payload);
                $this->storeSessionFlash(AdminAgendaListPageAction::FLASH_KEY, [
                    'status' => 'updated',
                ]);

                return $response->withHeader('Location', '/painel/eventos')->withStatus(303);
            }

            $newId = $this->agendaRepository->createEvent($payload);

            if ($newId <= 0) {
                $this->storeSessionFlash($this->resolveFlashKey($eventId), [
                    'payload' => $payload,
                    'errors' => ['Não foi possível salvar o evento. Verifique a conexão com banco.'],
                ]);

                return $response->withHeader('Location', $formPath)->withStatus(303);
            }

            $this->storeSessionFlash(AdminAgendaListPageAction::FLASH_KEY, [
                'status' => 'created',
            ]);

            return $response->withHeader('Location', '/painel/eventos')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao salvar evento no admin.', [
                'error' => $exception->getMessage(),
                'event_id' => $eventId,
            ]);

            $this->storeSessionFlash($this->resolveFlashKey($eventId), [
                'payload' => $payload,
                'errors' => ['Erro ao salvar. Verifique se o slug já existe e tente novamente.'],
            ]);

            return $response->withHeader('Location', $formPath)->withStatus(303);
        }
    }

    private function resolveFlashKey(?int $eventId): string
    {
        return self::FLASH_KEY_PREFIX . (($eventId !== null && $eventId > 0) ? (string) $eventId : 'new');
    }

    private function resolveFormPath(?int $eventId): string
    {
        return ($eventId !== null && $eventId > 0)
            ? '/painel/eventos/' . $eventId . '/editar'
            : '/painel/eventos/novo';
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @param array<string, mixed>|null $existingEvent
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(
        Response $response,
        array $categories,
        ?array $existingEvent,
        array $submittedPayload,
        array $errors
    ): Response {
        $isEdit = $existingEvent !== null;

        $form = [
            'category_id' => $submittedPayload['category_id'] ?? ($existingEvent['category_id'] ?? ''),
            'slug' => $submittedPayload['slug'] ?? ($existingEvent['slug'] ?? ''),
            'title' => $submittedPayload['title'] ?? ($existingEvent['title'] ?? ''),
            'description' => $submittedPayload['description'] ?? ($existingEvent['description'] ?? ''),
            'theme' => $submittedPayload['theme'] ?? ($existingEvent['theme'] ?? ''),
            'location_name' => $submittedPayload['location_name'] ?? ($existingEvent['location_name'] ?? ''),
            'location_address' => $submittedPayload['location_address'] ?? ($existingEvent['location_address'] ?? ''),
            'mode' => $submittedPayload['mode'] ?? ($existingEvent['mode'] ?? ''),
            'meeting_url' => $submittedPayload['meeting_url'] ?? ($existingEvent['meeting_url'] ?? ''),
            'audience' => $submittedPayload['audience']
                ?? ($existingEvent['audience'] ?? ''),
            'notes' => $submittedPayload['notes'] ?? ($existingEvent['notes'] ?? ''),
            'starts_at' => $submittedPayload['starts_at']
                ?? $this->toDateTimeLocal((string) ($existingEvent['starts_at'] ?? '')),
            'ends_at' => $submittedPayload['ends_at']
                ?? $this->toDateTimeLocal((string) ($existingEvent['ends_at'] ?? '')),
            'status' => $submittedPayload['status'] ?? ($existingEvent['status'] ?? ''),
            'is_featured' => (string) ($submittedPayload['is_featured'] ?? ($existingEvent['is_featured'] ?? '0')),
        ];

        return $this->renderPage($response, 'pages/admin-agenda-form.twig', [
            'agenda_categories' => $categories,
            'agenda_form' => $form,
            'agenda_form_errors' => $errors,
            'agenda_form_is_edit' => $isEdit,
            'agenda_event_id' => $existingEvent['id'] ?? null,
            'agenda_audience_options' => self::AUDIENCE_OPTIONS,
            'page_title' => ($isEdit ? 'Editar evento' : 'Novo evento') . ' | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/eventos',
            'page_description' => 'Formulário do dashboard para eventos da agenda.',
        ]);
    }
}
