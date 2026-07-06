<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPatrimonyAssetViewPageAction extends AbstractAdminPatrimonyAction
{
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

        $detailFlash = $this->consumeSessionFlash($this->assetDetailFlashKey($assetId));
        $status = trim((string) ($detailFlash['status'] ?? ''));

        return $this->renderPage($response, 'pages/admin-patrimony-asset-view.twig', [
            'patrimony_asset' => $asset,
            'patrimony_asset_movements' => $this->patrimonyRepository->findMovementsByAssetId($assetId),
            'patrimony_asset_maintenances' => $this->patrimonyRepository->findMaintenancesByAssetId($assetId),
            'patrimony_asset_disposals' => $this->patrimonyRepository->findDisposalsByAssetId($assetId),
            'patrimony_asset_attachments' => $this->patrimonyRepository->findAttachmentsByAssetId($assetId),
            'patrimony_asset_detail_status' => $status,
            'page_title' => ($asset['name'] ?? 'Patrimônio') . ' | Patrimônio | Dashboard',
            'page_url' => $this->absoluteUrl($request, $this->assetViewPath($assetId)),
            'page_description' => 'Resumo do bem patrimonial do CEDE.',
        ]);
    }
}
