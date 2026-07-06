<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPatrimonyAssetAttachmentDeleteAction extends AbstractAdminPatrimonyAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $assetId = (int) ($request->getAttribute('id') ?? 0);
        $attachmentId = (int) ($request->getAttribute('attachmentId') ?? 0);

        if ($assetId <= 0 || $attachmentId <= 0) {
            $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $this->redirectTo($request, $response, $this->assetListPath());
        }

        try {
            $attachment = $this->patrimonyRepository->findAttachmentByIdForAdmin($attachmentId);
            if ($attachment === null || (int) ($attachment['asset_id'] ?? 0) !== $assetId) {
                $this->storeSessionFlash($this->assetDetailFlashKey($assetId), [
                    'status' => 'attachment-not-found',
                ]);

                return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
            }

            $this->patrimonyRepository->deleteAttachment($attachmentId);
            $this->deleteStoredPatrimonyFileIfManaged((string) ($attachment['file_path'] ?? ''));

            $this->storeSessionFlash($this->assetDetailFlashKey($assetId), [
                'status' => 'attachment-deleted',
            ]);

            return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao excluir anexo patrimonial.', [
                'asset_id' => $assetId,
                'attachment_id' => $attachmentId,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash($this->assetDetailFlashKey($assetId), [
                'status' => 'attachment-delete-error',
            ]);

            return $this->redirectTo($request, $response, $this->assetFormPath($assetId));
        }
    }
}
