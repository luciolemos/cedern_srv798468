<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class AdminPatrimonyAssetDisposalFormPageAction extends AbstractAdminPatrimonyAction
{
    private const FLASH_KEY_PREFIX = 'admin_patrimony_asset_disposal_form_';

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
                'status' => 'disposal-blocked',
            ]);

            return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
        }

        $formPath = $this->assetDisposalPath($assetId);

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
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        $newDocumentPath = '';
        $uploadedFiles = $request->getUploadedFiles();
        $documentUpload = $uploadedFiles['document_file'] ?? null;
        if ($documentUpload instanceof UploadedFileInterface && $documentUpload->getError() !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $this->storePatrimonyDocument($documentUpload, 'disposal-document');

            if (!empty($uploadResult['error'])) {
                $errors[] = (string) $uploadResult['error'];
            } else {
                $newDocumentPath = (string) ($uploadResult['path'] ?? '');
                $payload['document_path'] = $newDocumentPath;
                $payload['document_mime_type'] = $uploadResult['mime_type'] ?? null;
                $payload['document_size_bytes'] = $uploadResult['size_bytes'] ?? null;
            }
        }

        if (!empty($errors)) {
            if ($newDocumentPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newDocumentPath);
            }

            $flashPayload = $payload;
            unset(
                $flashPayload['document_path'],
                $flashPayload['document_mime_type'],
                $flashPayload['document_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $flashPayload,
                'errors' => $errors,
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }

        try {
            $this->patrimonyRepository->recordDisposal($assetId, $payload);

            $this->storeSessionFlash($this->assetDetailFlashKey($assetId), [
                'status' => 'disposal-created',
            ]);

            return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
        } catch (\Throwable $exception) {
            if ($newDocumentPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newDocumentPath);
            }

            $this->logger->warning('Falha ao registrar baixa patrimonial.', [
                'asset_id' => $assetId,
                'error' => $exception->getMessage(),
            ]);

            $flashPayload = $payload;
            unset(
                $flashPayload['document_path'],
                $flashPayload['document_mime_type'],
                $flashPayload['document_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $flashPayload,
                'errors' => ['Não foi possível registrar a baixa agora.'],
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
            'disposed_at' => trim((string) ($input['disposed_at'] ?? '')),
            'disposal_reason' => trim((string) ($input['disposal_reason'] ?? '')),
            'disposal_responsible' => trim((string) ($input['disposal_responsible'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ((string) ($payload['disposed_at'] ?? '') === '' || $this->formatDateTimeLocalInput((string) $payload['disposed_at']) === '') {
            $errors[] = 'Informe uma data e hora válidas para a baixa.';
        }

        if (!array_key_exists((string) ($payload['disposal_reason'] ?? ''), $this->disposalReasonOptions())) {
            $errors[] = 'Selecione um motivo válido para a baixa.';
        }

        if ((string) ($payload['disposal_responsible'] ?? '') === '') {
            $errors[] = 'Informe o responsável pela baixa.';
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
            'disposed_at' => $submittedPayload['disposed_at'] ?? $this->formatDateTimeLocalInput(date('Y-m-d H:i:s')),
            'disposal_reason' => $submittedPayload['disposal_reason'] ?? '',
            'disposal_responsible' => $submittedPayload['disposal_responsible'] ?? '',
            'notes' => $submittedPayload['notes'] ?? '',
        ];

        return $this->renderPage($response, 'pages/admin-patrimony-disposal-form.twig', [
            'patrimony_asset' => $asset,
            'patrimony_disposal_form' => $form,
            'patrimony_disposal_form_errors' => $errors,
            'patrimony_disposal_reason_options' => $this->disposalReasonOptions(),
            'page_title' => 'Baixa patrimonial | Dashboard',
            'page_url' => $this->absoluteUrl($request, $this->assetDisposalPath((int) ($asset['id'] ?? 0))),
            'page_description' => 'Registro de baixa patrimonial do CEDE.',
        ]);
    }
}
