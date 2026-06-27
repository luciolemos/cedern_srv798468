<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPatrimonyAssetDeleteAction extends AbstractAdminPatrimonyAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $this->redirectTo($request, $response, $this->assetListPath());
        }

        try {
            $asset = $this->patrimonyRepository->findAssetByIdForAdmin($id);

            if ($asset === null) {
                $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $this->redirectTo($request, $response, $this->assetListPath());
            }

            if ($this->patrimonyRepository->assetHasLinkedHistory($id)) {
                $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                    'status' => 'delete-blocked',
                ]);

                return $this->redirectTo($request, $response, $this->assetListPath());
            }

            $attachments = $this->patrimonyRepository->findAttachmentsByAssetId($id);
            $this->patrimonyRepository->deleteAsset($id);

            $this->deleteStoredPatrimonyFileIfManaged((string) ($asset['main_photo_path'] ?? ''));
            $this->deleteStoredPatrimonyFileIfManaged((string) ($asset['purchase_document_path'] ?? ''));

            foreach ($attachments as $attachment) {
                $this->deleteStoredPatrimonyFileIfManaged((string) ($attachment['file_path'] ?? ''));
            }

            $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                'status' => 'deleted',
            ]);

            return $this->redirectTo($request, $response, $this->assetListPath());
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao excluir patrimônio.', [
                'asset_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                'status' => 'delete-error',
            ]);

            return $this->redirectTo($request, $response, $this->assetListPath());
        }
    }
}
