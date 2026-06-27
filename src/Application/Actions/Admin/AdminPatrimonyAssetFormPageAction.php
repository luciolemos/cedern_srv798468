<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class AdminPatrimonyAssetFormPageAction extends AbstractAdminPatrimonyAction
{
    private const FLASH_KEY_PREFIX = 'admin_patrimony_asset_form_';

    public function __invoke(Request $request, Response $response): Response
    {
        $idRaw = $request->getAttribute('id');
        $assetId = ($idRaw !== null) ? (int) $idRaw : null;
        $isEdit = $assetId !== null && $assetId > 0;

        $existingAsset = null;
        if ($isEdit) {
            $existingAsset = $this->patrimonyRepository->findAssetByIdForAdmin($assetId);

            if ($existingAsset === null) {
                $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                    'status' => 'not-found',
                ]);

                return $this->redirectTo($request, $response, $this->assetListPath());
            }
        }

        $categories = $this->patrimonyRepository->findAllCategoriesForAdmin();
        $locations = $this->patrimonyRepository->findActiveLocations();
        $movements = $isEdit ? $this->patrimonyRepository->findMovementsByAssetId($assetId) : [];
        $maintenances = $isEdit ? $this->patrimonyRepository->findMaintenancesByAssetId($assetId) : [];
        $disposals = $isEdit ? $this->patrimonyRepository->findDisposalsByAssetId($assetId) : [];
        $attachments = $isEdit ? $this->patrimonyRepository->findAttachmentsByAssetId($assetId) : [];

        $formPath = $this->assetFormPath($assetId);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash($this->resolveFlashKey($assetId));
            $detailFlash = $isEdit ? $this->consumeSessionFlash($this->assetDetailFlashKey($assetId)) : [];
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            return $this->renderForm(
                $request,
                $response,
                $existingAsset,
                $submittedPayload,
                $errors,
                $categories,
                $locations,
                $movements,
                $maintenances,
                $disposals,
                $attachments,
                $detailFlash
            );
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body, $existingAsset);
        $errors = $this->validatePayload($payload, $categories, $locations, $isEdit, $existingAsset);

        $existingMainPhotoPath = (string) ($existingAsset['main_photo_path'] ?? '');
        $existingMainPhotoMimeType = (string) ($existingAsset['main_photo_mime_type'] ?? '');
        $existingMainPhotoSizeBytes = (int) ($existingAsset['main_photo_size_bytes'] ?? 0);
        $existingPurchaseDocumentPath = (string) ($existingAsset['purchase_document_path'] ?? '');
        $existingPurchaseDocumentMimeType = (string) ($existingAsset['purchase_document_mime_type'] ?? '');
        $existingPurchaseDocumentSizeBytes = (int) ($existingAsset['purchase_document_size_bytes'] ?? 0);

        $payload['main_photo_path'] = $existingMainPhotoPath;
        $payload['main_photo_mime_type'] = $existingMainPhotoMimeType !== '' ? $existingMainPhotoMimeType : null;
        $payload['main_photo_size_bytes'] = $existingMainPhotoSizeBytes > 0 ? $existingMainPhotoSizeBytes : null;
        $payload['purchase_document_path'] = $existingPurchaseDocumentPath;
        $payload['purchase_document_mime_type'] = $existingPurchaseDocumentMimeType !== '' ? $existingPurchaseDocumentMimeType : null;
        $payload['purchase_document_size_bytes'] = $existingPurchaseDocumentSizeBytes > 0 ? $existingPurchaseDocumentSizeBytes : null;

        $removeMainPhotoRequested = !empty($body['remove_main_photo']);
        $removePurchaseDocumentRequested = !empty($body['remove_purchase_document']);

        if ($removeMainPhotoRequested) {
            $payload['main_photo_path'] = '';
            $payload['main_photo_mime_type'] = null;
            $payload['main_photo_size_bytes'] = null;
        }

        if ($removePurchaseDocumentRequested) {
            $payload['purchase_document_path'] = '';
            $payload['purchase_document_mime_type'] = null;
            $payload['purchase_document_size_bytes'] = null;
        }

        $newMainPhotoPath = '';
        $newPurchaseDocumentPath = '';
        $uploadedFiles = $request->getUploadedFiles();
        $mainPhotoUpload = $uploadedFiles['main_photo_file'] ?? null;
        $purchaseDocumentUpload = $uploadedFiles['purchase_document_file'] ?? null;

        if ($mainPhotoUpload instanceof UploadedFileInterface && $mainPhotoUpload->getError() !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $this->storePatrimonyImage($mainPhotoUpload, 'asset-photo');

            if (!empty($uploadResult['error'])) {
                $errors[] = (string) $uploadResult['error'];
            } else {
                $newMainPhotoPath = (string) ($uploadResult['path'] ?? '');
                $payload['main_photo_path'] = $newMainPhotoPath;
                $payload['main_photo_mime_type'] = $uploadResult['mime_type'] ?? null;
                $payload['main_photo_size_bytes'] = $uploadResult['size_bytes'] ?? null;
            }
        }

        if ($purchaseDocumentUpload instanceof UploadedFileInterface && $purchaseDocumentUpload->getError() !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $this->storePatrimonyDocument($purchaseDocumentUpload, 'purchase-document');

            if (!empty($uploadResult['error'])) {
                $errors[] = (string) $uploadResult['error'];
            } else {
                $newPurchaseDocumentPath = (string) ($uploadResult['path'] ?? '');
                $payload['purchase_document_path'] = $newPurchaseDocumentPath;
                $payload['purchase_document_mime_type'] = $uploadResult['mime_type'] ?? null;
                $payload['purchase_document_size_bytes'] = $uploadResult['size_bytes'] ?? null;
            }
        }

        if (!empty($errors)) {
            if ($newMainPhotoPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newMainPhotoPath);
            }
            if ($newPurchaseDocumentPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newPurchaseDocumentPath);
            }

            $flashPayload = $payload;
            unset(
                $flashPayload['main_photo_path'],
                $flashPayload['main_photo_mime_type'],
                $flashPayload['main_photo_size_bytes'],
                $flashPayload['purchase_document_path'],
                $flashPayload['purchase_document_mime_type'],
                $flashPayload['purchase_document_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $flashPayload,
                'errors' => $errors,
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }

        try {
            if ($isEdit) {
                $this->patrimonyRepository->updateAsset($assetId, $payload);

                if ($newMainPhotoPath !== '' && $existingMainPhotoPath !== '' && $existingMainPhotoPath !== $newMainPhotoPath) {
                    $this->deleteStoredPatrimonyFileIfManaged($existingMainPhotoPath);
                }
                if ($removeMainPhotoRequested && $existingMainPhotoPath !== '') {
                    $this->deleteStoredPatrimonyFileIfManaged($existingMainPhotoPath);
                }
                if (
                    $newPurchaseDocumentPath !== ''
                    && $existingPurchaseDocumentPath !== ''
                    && $existingPurchaseDocumentPath !== $newPurchaseDocumentPath
                ) {
                    $this->deleteStoredPatrimonyFileIfManaged($existingPurchaseDocumentPath);
                }
                if ($removePurchaseDocumentRequested && $existingPurchaseDocumentPath !== '') {
                    $this->deleteStoredPatrimonyFileIfManaged($existingPurchaseDocumentPath);
                }

                $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                    'status' => 'updated',
                ]);

                return $this->redirectTo($request, $response, $this->assetListPath());
            }

            $newId = $this->patrimonyRepository->createAsset($payload);
            if ($newId <= 0) {
                if ($newMainPhotoPath !== '') {
                    $this->deleteStoredPatrimonyFileIfManaged($newMainPhotoPath);
                }
                if ($newPurchaseDocumentPath !== '') {
                    $this->deleteStoredPatrimonyFileIfManaged($newPurchaseDocumentPath);
                }

                $flashPayload = $payload;
                unset(
                    $flashPayload['main_photo_path'],
                    $flashPayload['main_photo_mime_type'],
                    $flashPayload['main_photo_size_bytes'],
                    $flashPayload['purchase_document_path'],
                    $flashPayload['purchase_document_mime_type'],
                    $flashPayload['purchase_document_size_bytes']
                );

                $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                    'payload' => $flashPayload,
                    'errors' => ['Não foi possível salvar o patrimônio. Verifique a conexão com banco.'],
                ]);

                return $this->redirectTo($request, $response, $formPath);
            }

            $this->storeSessionFlash(AdminPatrimonyAssetListPageAction::FLASH_KEY, [
                'status' => 'created',
            ]);

            return $this->redirectTo($request, $response, $this->assetListPath());
        } catch (\Throwable $exception) {
            if ($newMainPhotoPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newMainPhotoPath);
            }
            if ($newPurchaseDocumentPath !== '') {
                $this->deleteStoredPatrimonyFileIfManaged($newPurchaseDocumentPath);
            }

            $this->logger->warning('Falha ao salvar patrimônio.', [
                'asset_id' => $assetId,
                'error' => $exception->getMessage(),
            ]);

            $flashPayload = $payload;
            unset(
                $flashPayload['main_photo_path'],
                $flashPayload['main_photo_mime_type'],
                $flashPayload['main_photo_size_bytes'],
                $flashPayload['purchase_document_path'],
                $flashPayload['purchase_document_mime_type'],
                $flashPayload['purchase_document_size_bytes']
            );

            $this->storeSessionFlash($this->resolveFlashKey($assetId), [
                'payload' => $flashPayload,
                'errors' => ['Erro ao salvar. Verifique se o código patrimonial é único e tente novamente.'],
            ]);

            return $this->redirectTo($request, $response, $formPath);
        }
    }

    private function resolveFlashKey(?int $assetId): string
    {
        return self::FLASH_KEY_PREFIX . (($assetId !== null && $assetId > 0) ? (string) $assetId : 'new');
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existingAsset
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input, ?array $existingAsset): array
    {
        $isEdit = $existingAsset !== null;
        $generatedCode = trim((string) ($input['asset_code'] ?? ''));
        if ($generatedCode === '') {
            $generatedCode = $isEdit
                ? (string) ($existingAsset['asset_code'] ?? '')
                : $this->patrimonyRepository->generateNextAssetCode();
        }

        return [
            'asset_code' => $generatedCode,
            'name' => trim((string) ($input['name'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'category_id' => (string) ($input['category_id'] ?? ''),
            'subcategory' => trim((string) ($input['subcategory'] ?? '')),
            'brand' => trim((string) ($input['brand'] ?? '')),
            'model' => trim((string) ($input['model'] ?? '')),
            'serial_number' => trim((string) ($input['serial_number'] ?? '')),
            'is_tagged' => !empty($input['is_tagged']) ? 1 : 0,
            'quantity' => trim((string) ($input['quantity'] ?? '1')),
            'unit_of_measure' => trim((string) ($input['unit_of_measure'] ?? 'un')),
            'acquisition_type' => trim((string) ($input['acquisition_type'] ?? 'outro')),
            'acquisition_date' => trim((string) ($input['acquisition_date'] ?? '')),
            'acquisition_value' => trim((string) ($input['acquisition_value'] ?? '')),
            'supplier_name' => trim((string) ($input['supplier_name'] ?? '')),
            'invoice_number' => trim((string) ($input['invoice_number'] ?? '')),
            'warranty_expires_at' => trim((string) ($input['warranty_expires_at'] ?? '')),
            'payment_method' => trim((string) ($input['payment_method'] ?? '')),
            'current_location_id' => $isEdit
                ? (string) ($existingAsset['current_location_id'] ?? '')
                : (string) ($input['current_location_id'] ?? ''),
            'current_location_complement' => $isEdit
                ? (string) ($existingAsset['current_location_complement'] ?? '')
                : trim((string) ($input['current_location_complement'] ?? '')),
            'current_status' => trim((string) ($input['current_status'] ?? ($existingAsset['current_status'] ?? 'em_uso'))),
            'conservation_state' => trim((string) ($input['conservation_state'] ?? ($existingAsset['conservation_state'] ?? 'bom'))),
            'current_responsible' => trim((string) ($input['current_responsible'] ?? '')),
            'responsible_department' => trim((string) ($input['responsible_department'] ?? '')),
            'last_movement_at' => (string) ($existingAsset['last_movement_at'] ?? ''),
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $categories
     * @param array<int, array<string, mixed>> $locations
     * @param array<string, mixed>|null $existingAsset
     * @return array<int, string>
     */
    private function validatePayload(
        array $payload,
        array $categories,
        array $locations,
        bool $isEdit,
        ?array $existingAsset
    ): array {
        $errors = [];
        $validCategoryIds = array_map(static fn (array $category): int => (int) ($category['id'] ?? 0), $categories);
        $validLocationIds = array_map(static fn (array $location): int => (int) ($location['id'] ?? 0), $locations);

        if ((string) ($payload['asset_code'] ?? '') === '') {
            $errors[] = 'Código patrimonial é obrigatório.';
        }

        if ((string) ($payload['name'] ?? '') === '') {
            $errors[] = 'Nome do material é obrigatório.';
        }

        $categoryId = (int) ($payload['category_id'] ?? 0);
        if ($categoryId <= 0 || !in_array($categoryId, $validCategoryIds, true)) {
            $errors[] = 'Selecione uma categoria válida.';
        }

        if (!is_numeric((string) ($payload['quantity'] ?? '')) || (float) ($payload['quantity'] ?? 0) <= 0) {
            $errors[] = 'Quantidade deve ser maior que zero.';
        }

        if ((string) ($payload['unit_of_measure'] ?? '') === '') {
            $errors[] = 'Unidade de medida é obrigatória.';
        }

        if (!array_key_exists((string) ($payload['acquisition_type'] ?? ''), $this->acquisitionTypeOptions())) {
            $errors[] = 'Selecione um tipo de aquisição válido.';
        }

        if ($payload['acquisition_date'] !== '' && !$this->formatDateInput((string) $payload['acquisition_date'])) {
            $errors[] = 'Data de aquisição inválida.';
        }

        if ($payload['warranty_expires_at'] !== '' && !$this->formatDateInput((string) $payload['warranty_expires_at'])) {
            $errors[] = 'Data final da garantia inválida.';
        }

        if (!array_key_exists((string) ($payload['current_status'] ?? ''), $this->statusOptions())) {
            $errors[] = 'Selecione uma situação válida.';
        }

        if (!array_key_exists((string) ($payload['conservation_state'] ?? ''), $this->conservationOptions())) {
            $errors[] = 'Selecione um estado de conservação válido.';
        }

        if (!$isEdit) {
            $locationId = (int) ($payload['current_location_id'] ?? 0);
            if ($locationId <= 0 || !in_array($locationId, $validLocationIds, true)) {
                $errors[] = 'Selecione a localização inicial do patrimônio.';
            }
        }

        if ($isEdit && (string) ($payload['current_status'] ?? '') === 'baixado' && (string) ($existingAsset['current_status'] ?? '') !== 'baixado') {
            $errors[] = 'Use o fluxo de baixa patrimonial para marcar o bem como baixado.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $existingAsset
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     * @param array<int, array<string, mixed>> $categories
     * @param array<int, array<string, mixed>> $locations
     * @param array<int, array<string, mixed>> $movements
     * @param array<int, array<string, mixed>> $maintenances
     * @param array<int, array<string, mixed>> $disposals
     * @param array<int, array<string, mixed>> $attachments
     * @param array<string, mixed> $detailFlash
     */
    private function renderForm(
        Request $request,
        Response $response,
        ?array $existingAsset,
        array $submittedPayload,
        array $errors,
        array $categories,
        array $locations,
        array $movements,
        array $maintenances,
        array $disposals,
        array $attachments,
        array $detailFlash
    ): Response {
        $isEdit = $existingAsset !== null;

        $form = [
            'asset_code' => $submittedPayload['asset_code']
                ?? ($existingAsset['asset_code'] ?? $this->patrimonyRepository->generateNextAssetCode()),
            'name' => $submittedPayload['name'] ?? ($existingAsset['name'] ?? ''),
            'description' => $submittedPayload['description'] ?? ($existingAsset['description'] ?? ''),
            'category_id' => $submittedPayload['category_id'] ?? (string) ($existingAsset['category_id'] ?? ''),
            'subcategory' => $submittedPayload['subcategory'] ?? ($existingAsset['subcategory'] ?? ''),
            'brand' => $submittedPayload['brand'] ?? ($existingAsset['brand'] ?? ''),
            'model' => $submittedPayload['model'] ?? ($existingAsset['model'] ?? ''),
            'serial_number' => $submittedPayload['serial_number'] ?? ($existingAsset['serial_number'] ?? ''),
            'is_tagged' => array_key_exists('is_tagged', $submittedPayload)
                ? (int) $submittedPayload['is_tagged']
                : (((bool) ($existingAsset['is_tagged'] ?? false)) ? 1 : 0),
            'quantity' => $submittedPayload['quantity'] ?? ($existingAsset['quantity_label'] ?? '1'),
            'unit_of_measure' => $submittedPayload['unit_of_measure'] ?? ($existingAsset['unit_of_measure'] ?? 'un'),
            'acquisition_type' => $submittedPayload['acquisition_type'] ?? ($existingAsset['acquisition_type'] ?? 'outro'),
            'acquisition_date' => $submittedPayload['acquisition_date'] ?? $this->formatDateInput($existingAsset['acquisition_date'] ?? null),
            'acquisition_value' => $submittedPayload['acquisition_value']
                ?? ((($existingAsset['acquisition_value'] ?? null) !== null)
                    ? number_format((float) $existingAsset['acquisition_value'], 2, ',', '.')
                    : ''),
            'supplier_name' => $submittedPayload['supplier_name'] ?? ($existingAsset['supplier_name'] ?? ''),
            'invoice_number' => $submittedPayload['invoice_number'] ?? ($existingAsset['invoice_number'] ?? ''),
            'warranty_expires_at' => $submittedPayload['warranty_expires_at'] ?? $this->formatDateInput($existingAsset['warranty_expires_at'] ?? null),
            'payment_method' => $submittedPayload['payment_method'] ?? ($existingAsset['payment_method'] ?? ''),
            'current_location_id' => $submittedPayload['current_location_id'] ?? (string) ($existingAsset['current_location_id'] ?? ''),
            'current_location_complement' => $submittedPayload['current_location_complement'] ?? ($existingAsset['current_location_complement'] ?? ''),
            'current_status' => $submittedPayload['current_status'] ?? ($existingAsset['current_status'] ?? 'em_uso'),
            'conservation_state' => $submittedPayload['conservation_state'] ?? ($existingAsset['conservation_state'] ?? 'bom'),
            'current_responsible' => $submittedPayload['current_responsible'] ?? ($existingAsset['current_responsible'] ?? ''),
            'responsible_department' => $submittedPayload['responsible_department'] ?? ($existingAsset['responsible_department'] ?? ''),
            'notes' => $submittedPayload['notes'] ?? ($existingAsset['notes'] ?? ''),
        ];

        return $this->renderPage($response, 'pages/admin-patrimony-asset-form.twig', [
            'patrimony_asset_form' => $form,
            'patrimony_asset_form_errors' => $errors,
            'patrimony_asset_form_is_edit' => $isEdit,
            'patrimony_asset_id' => $existingAsset['id'] ?? null,
            'patrimony_asset' => $existingAsset,
            'patrimony_asset_categories' => $categories,
            'patrimony_asset_locations' => $locations,
            'patrimony_asset_acquisition_types' => $this->acquisitionTypeOptions(),
            'patrimony_asset_status_options' => $isEdit ? $this->editableStatusOptions() : $this->statusOptions(),
            'patrimony_asset_conservation_options' => $this->conservationOptions(),
            'patrimony_asset_movements' => $movements,
            'patrimony_asset_maintenances' => $maintenances,
            'patrimony_asset_disposals' => $disposals,
            'patrimony_asset_attachments' => $attachments,
            'patrimony_asset_detail_status' => trim((string) ($detailFlash['status'] ?? '')),
            'page_title' => ($isEdit ? 'Editar patrimônio' : 'Novo patrimônio') . ' | Dashboard',
            'page_url' => $this->absoluteUrl(
                $request,
                $this->assetFormPath($isEdit ? (int) ($existingAsset['id'] ?? 0) : null)
            ),
            'page_description' => 'Formulário do dashboard para controle patrimonial do CEDE.',
        ]);
    }
}
