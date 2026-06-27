<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPatrimonyAssetMovementFormPageAction extends AbstractAdminPatrimonyAction
{
    private const FLASH_KEY_PREFIX = 'admin_patrimony_asset_movement_form_';

    public function __invoke(Request $request, Response $response): Response
    {
        $assetId = (int) ($request->getAttribute('id') ?? 0);
        if ($assetId <= 0) {
            $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $this->redirectTo($request, $response, $this->assetListPath());
        }

        $asset = $this->patrimonyRepository->findAssetByIdForAdmin($assetId);
        if ($asset === null) {
            $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $this->redirectTo($request, $response, $this->assetListPath());
        }

        if ((string) ($asset['current_status'] ?? '') === 'baixado') {
            $this->storeSessionFlash($this->assetDetailFlashKey($assetId), [
                'status' => 'movement-blocked',
            ]);

            return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
        }

        $locations = $this->patrimonyRepository->findActiveLocations();
        $formPath = $this->assetMovementPath($assetId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($assetId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($request, $response, $asset, $locations, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload, $asset, $locations);

        if (!empty($errors)) {
            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $payload,
                'errors' => $errors,
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }

        try {
            $this->patrimonyRepository->recordMovement($assetId, $payload);

            $this->storeSessionFlash($this->assetDetailFlashKey($assetId), [
                'status' => 'movement-created',
            ]);

            return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao registrar movimentação patrimonial.', [
                'asset_id' => $assetId,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $payload,
                'errors' => ['Não foi possível registrar a movimentação agora.'],
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }
    }

    private function resolveFlashKey(int $assetId): string
    {
        return self::FLASH_KEY_PREFIX . $assetId;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input): array
    {
        return [
            'destination_location_id' => trim((string) ($input['destination_location_id'] ?? '')),
            'destination_location_complement' => trim((string) ($input['destination_location_complement'] ?? '')),
            'movement_responsible' => trim((string) ($input['movement_responsible'] ?? '')),
            'new_responsible' => trim((string) ($input['new_responsible'] ?? '')),
            'responsible_department' => trim((string) ($input['responsible_department'] ?? '')),
            'current_status' => trim((string) ($input['current_status'] ?? 'em_uso')),
            'movement_reason' => trim((string) ($input['movement_reason'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'moved_at' => trim((string) ($input['moved_at'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $asset
     * @param array<int, array<string, mixed>> $locations
     * @return array<int, string>
     */
    private function validatePayload(array $payload, array $asset, array $locations): array
    {
        $errors = [];
        $locationIds = array_map(static fn (array $location): int => (int) ($location['id'] ?? 0), $locations);
        $destinationLocationId = (int) ($payload['destination_location_id'] ?? 0);

        if ($destinationLocationId <= 0 || !in_array($destinationLocationId, $locationIds, true)) {
            $errors[] = 'Selecione um destino válido.';
        }

        if ((string) ($asset['current_location_id'] ?? '') === (string) $destinationLocationId
            && trim((string) ($asset['current_location_complement'] ?? '')) === trim((string) ($payload['destination_location_complement'] ?? ''))
        ) {
            $errors[] = 'Selecione uma localização diferente da atual.';
        }

        if ((string) ($payload['movement_responsible'] ?? '') === '') {
            $errors[] = 'Informe o responsável pela movimentação.';
        }

        if ((string) ($payload['movement_reason'] ?? '') === '') {
            $errors[] = 'Informe o motivo da movimentação.';
        }

        if ((string) ($payload['moved_at'] ?? '') === '' || $this->formatDateTimeLocalInput((string) $payload['moved_at']) === '') {
            $errors[] = 'Informe uma data e hora válidas para a movimentação.';
        }

        if (!array_key_exists((string) ($payload['current_status'] ?? ''), $this->editableStatusOptions())) {
            $errors[] = 'Selecione uma situação válida para o bem após a movimentação.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $asset
     * @param array<int, array<string, mixed>> $locations
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(
        Request $request,
        Response $response,
        array $asset,
        array $locations,
        array $submittedPayload,
        array $errors
    ): Response
    {
        $form = [
            'destination_location_id' => $submittedPayload['destination_location_id'] ?? '',
            'destination_location_complement' => $submittedPayload['destination_location_complement'] ?? '',
            'movement_responsible' => $submittedPayload['movement_responsible'] ?? '',
            'new_responsible' => $submittedPayload['new_responsible'] ?? ($asset['current_responsible'] ?? ''),
            'responsible_department' => $submittedPayload['responsible_department'] ?? ($asset['responsible_department'] ?? ''),
            'current_status' => $submittedPayload['current_status'] ?? ($asset['current_status'] ?? 'em_uso'),
            'movement_reason' => $submittedPayload['movement_reason'] ?? '',
            'notes' => $submittedPayload['notes'] ?? '',
            'moved_at' => $submittedPayload['moved_at'] ?? $this->formatDateTimeLocalInput(date('Y-m-d H:i:s')),
        ];

        return $this->renderPage($response, 'pages/admin-patrimony-movement-form.twig', [
            'patrimony_asset' => $asset,
            'patrimony_movement_form' => $form,
            'patrimony_movement_form_errors' => $errors,
            'patrimony_locations' => $locations,
            'patrimony_status_options' => $this->editableStatusOptions(),
            'page_title' => 'Movimentar patrimônio | Dashboard',
            'page_url' => $this->absoluteUrl($request, $this->assetMovementPath((int) ($asset['id'] ?? 0))),
            'page_description' => 'Registro de movimentação patrimonial do CEDE.',
        ]);
    }
}
