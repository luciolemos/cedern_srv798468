<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class AdminPatrimonyAssetAttachmentFormPageAction extends AbstractAdminPatrimonyAction
{
    private const FLASH_KEY_PREFIX = 'admin_patrimony_asset_attachment_form_';

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

        $formPath = $this->assetAttachmentPath($assetId);

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

        $uploadedFiles = $request->getUploadedFiles();
        $fileUpload = $uploadedFiles['attachment_file'] ?? null;
        $newAttachmentPath = '';

        if (!$fileUpload instanceof UploadedFileInterface || $fileUpload->getError() === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Selecione um arquivo para anexar.';
        } else {
            $uploadResult = $this->storePatrimonyDocument($fileUpload, 'attachment');

            if (!empty($uploadResult['error'])) {
                $errors[] = (string) $uploadResult['error'];
            } else {
                $newAttachmentPath = (string) ($uploadResult['path'] ?? '');
                $payload['file_path'] = $newAttachmentPath;
                $payload['mime_type'] = $uploadResult['mime_type'] ?? null;
                $payload['size_bytes'] = $uploadResult['size_bytes'] ?? null;
                $payload['original_file_name'] = $uploadResult['original_file_name'] ?? null;
            }
        }

        if (!empty($errors)) {
            if ($newAttachmentPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newAttachmentPath);
            }

            $flashPayload = $payload;
            unset(
                $flashPayload['file_path'],
                $flashPayload['mime_type'],
                $flashPayload['size_bytes'],
                $flashPayload['original_file_name']
            );

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $flashPayload,
                'errors' => $errors,
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }

        try {
            $this->patrimonyRepository->addAttachment($assetId, $payload);

            $this->storeSessionFlash($this->assetDetailFlashKey($assetId), [
                'status' => 'attachment-created',
            ]);

            return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
        } catch (\Throwable $exception) {
            if ($newAttachmentPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newAttachmentPath);
            }

            $this->logger->warning('Falha ao anexar documento ao patrimônio.', [
                'asset_id' => $assetId,
                'error' => $exception->getMessage(),
            ]);

            $flashPayload = $payload;
            unset(
                $flashPayload['file_path'],
                $flashPayload['mime_type'],
                $flashPayload['size_bytes'],
                $flashPayload['original_file_name']
            );

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $flashPayload,
                'errors' => ['Não foi possível anexar o arquivo agora.'],
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
            'attachment_type' => trim((string) ($input['attachment_type'] ?? 'outro')),
            'label' => trim((string) ($input['label'] ?? '')),
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

        if (!array_key_exists((string) ($payload['attachment_type'] ?? ''), $this->attachmentTypeOptions())) {
            $errors[] = 'Selecione um tipo de anexo válido.';
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
            'attachment_type' => $submittedPayload['attachment_type'] ?? 'outro',
            'label' => $submittedPayload['label'] ?? '',
            'notes' => $submittedPayload['notes'] ?? '',
        ];

        return $this->renderPage($response, 'pages/admin-patrimony-attachment-form.twig', [
            'patrimony_asset' => $asset,
            'patrimony_attachment_form' => $form,
            'patrimony_attachment_form_errors' => $errors,
            'patrimony_attachment_type_options' => $this->attachmentTypeOptions(),
            'page_title' => 'Adicionar anexo | Dashboard',
            'page_url' => $this->absoluteUrl($request, $this->assetAttachmentPath((int) ($asset['id'] ?? 0))),
            'page_description' => 'Cadastro de anexos do controle patrimonial do CEDE.',
        ]);
    }
}
