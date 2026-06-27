<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class AdminPatrimonyAssetMaintenanceFormPageAction extends AbstractAdminPatrimonyAction
{
    private const FLASH_KEY_PREFIX = 'admin_patrimony_asset_maintenance_form_';

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

        $formPath = $this->assetMaintenancePath($assetId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($assetId));
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm($request, $response, $asset, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body, $asset);
        $errors = $this->validatePayload($payload);

        $newAttachmentPath = '';
        $uploadedFiles = $request->getUploadedFiles();
        $attachmentUpload = $uploadedFiles['attachment_file'] ?? null;
        if ($attachmentUpload instanceof UploadedFileInterface && $attachmentUpload->getError() !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $this->storePatrimonyDocument($attachmentUpload, 'maintenance-document');

            if (!empty($uploadResult['error'])) {
                $errors[] = (string) $uploadResult['error'];
            } else {
                $newAttachmentPath = (string) ($uploadResult['path'] ?? '');
                $payload['attachment_path'] = $newAttachmentPath;
                $payload['attachment_mime_type'] = $uploadResult['mime_type'] ?? null;
                $payload['attachment_size_bytes'] = $uploadResult['size_bytes'] ?? null;
            }
        }

        if (!empty($errors)) {
            if ($newAttachmentPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newAttachmentPath);
            }

            $flashPayload = $payload;
            unset(
                $flashPayload['attachment_path'],
                $flashPayload['attachment_mime_type'],
                $flashPayload['attachment_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $flashPayload,
                'errors' => $errors,
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }

        try {
            $this->patrimonyRepository->recordMaintenance($assetId, $payload);

            $this->storeSessionFlash($this->assetDetailFlashKey($assetId), [
                'status' => 'maintenance-created',
            ]);

            return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
        } catch (\Throwable $exception) {
            if ($newAttachmentPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newAttachmentPath);
            }

            $this->logger->warning('Falha ao registrar manutenção patrimonial.', [
                'asset_id' => $assetId,
                'error' => $exception->getMessage(),
            ]);

            $flashPayload = $payload;
            unset(
                $flashPayload['attachment_path'],
                $flashPayload['attachment_mime_type'],
                $flashPayload['attachment_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $flashPayload,
                'errors' => ['Não foi possível registrar a manutenção agora.'],
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
     * @param array<string, mixed> $asset
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input, array $asset): array
    {
        return [
            'maintenance_date' => trim((string) ($input['maintenance_date'] ?? '')),
            'maintenance_type' => trim((string) ($input['maintenance_type'] ?? '')),
            'vendor_name' => trim((string) ($input['vendor_name'] ?? '')),
            'cost_amount' => trim((string) ($input['cost_amount'] ?? '')),
            'service_description' => trim((string) ($input['service_description'] ?? '')),
            'next_maintenance_at' => trim((string) ($input['next_maintenance_at'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'current_status' => trim((string) ($input['current_status'] ?? ($asset['current_status'] ?? 'em_manutencao'))),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ((string) ($payload['maintenance_date'] ?? '') === '' || $this->formatDateTimeLocalInput((string) $payload['maintenance_date']) === '') {
            $errors[] = 'Informe uma data e hora válidas para a manutenção.';
        }

        if ((string) ($payload['maintenance_type'] ?? '') === '') {
            $errors[] = 'Informe o tipo da manutenção.';
        }

        if ((string) ($payload['service_description'] ?? '') === '') {
            $errors[] = 'Descreva o serviço executado.';
        }

        if ((string) ($payload['next_maintenance_at'] ?? '') !== '' && $this->formatDateInput((string) $payload['next_maintenance_at']) === '') {
            $errors[] = 'Data da próxima manutenção inválida.';
        }

        if (!array_key_exists((string) ($payload['current_status'] ?? ''), $this->editableStatusOptions())) {
            $errors[] = 'Selecione a situação do bem após a manutenção.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $asset
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(Request $request, Response $response, array $asset, array $submittedPayload, array $errors): Response
    {
        $form = [
            'maintenance_date' => $submittedPayload['maintenance_date'] ?? $this->formatDateTimeLocalInput(date('Y-m-d H:i:s')),
            'maintenance_type' => $submittedPayload['maintenance_type'] ?? '',
            'vendor_name' => $submittedPayload['vendor_name'] ?? '',
            'cost_amount' => $submittedPayload['cost_amount'] ?? '',
            'service_description' => $submittedPayload['service_description'] ?? '',
            'next_maintenance_at' => $submittedPayload['next_maintenance_at'] ?? '',
            'notes' => $submittedPayload['notes'] ?? '',
            'current_status' => $submittedPayload['current_status'] ?? 'em_manutencao',
        ];

        return $this->renderPage($response, 'pages/admin-patrimony-maintenance-form.twig', [
            'patrimony_asset' => $asset,
            'patrimony_maintenance_form' => $form,
            'patrimony_maintenance_form_errors' => $errors,
            'patrimony_status_options' => $this->editableStatusOptions(),
            'page_title' => 'Registrar manutenção | Dashboard',
            'page_url' => $this->absoluteUrl($request, $this->assetMaintenancePath((int) ($asset['id'] ?? 0))),
            'page_description' => 'Registro de manutenção patrimonial do CEDE.',
        ]);
    }
}
