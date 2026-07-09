<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Patrimony;

use App\Domain\Patrimony\PatrimonyRepository;
use App\Support\ManagedPublicMediaPath;

class MySqlPatrimonyRepository implements PatrimonyRepository
{
    private \PDO $pdo;

    private bool $schemaEnsured = false;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function generateNextAssetCode(): string
    {
        $operation = function (): string {
            return $this->generateNextAssetCodeValue();
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllAssetsForAdmin(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query($this->buildAssetSelect() . ' ORDER BY a.updated_at DESC, a.id DESC');

            return array_map([$this, 'normalizeAsset'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAssetByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $statement = $this->pdo->prepare($this->buildAssetSelect() . ' WHERE a.id = :id LIMIT 1');
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $asset = $statement->fetch();

            return $asset ? $this->normalizeAsset($asset) : null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function createAsset(array $data): int
    {
        $operation = function () use ($data): int {
            $sql = <<<SQL
                INSERT INTO patrimony_assets (
                    asset_code,
                    name,
                    description,
                    category_id,
                    subcategory,
                    brand,
                    model,
                    serial_number,
                    is_tagged,
                    quantity,
                    unit_of_measure,
                    acquisition_type,
                    acquisition_date,
                    acquisition_value,
                    supplier_name,
                    invoice_number,
                    purchase_document_path,
                    purchase_document_mime_type,
                    purchase_document_size_bytes,
                    warranty_expires_at,
                    payment_method,
                    current_location_id,
                    current_location_complement,
                    current_status,
                    conservation_state,
                    current_responsible,
                    responsible_department,
                    last_movement_at,
                    notes,
                    main_photo_path,
                    main_photo_mime_type,
                    main_photo_size_bytes
                ) VALUES (
                    :asset_code,
                    :name,
                    :description,
                    :category_id,
                    :subcategory,
                    :brand,
                    :model,
                    :serial_number,
                    :is_tagged,
                    :quantity,
                    :unit_of_measure,
                    :acquisition_type,
                    :acquisition_date,
                    :acquisition_value,
                    :supplier_name,
                    :invoice_number,
                    :purchase_document_path,
                    :purchase_document_mime_type,
                    :purchase_document_size_bytes,
                    :warranty_expires_at,
                    :payment_method,
                    :current_location_id,
                    :current_location_complement,
                    :current_status,
                    :conservation_state,
                    :current_responsible,
                    :responsible_department,
                    :last_movement_at,
                    :notes,
                    :main_photo_path,
                    :main_photo_mime_type,
                    :main_photo_size_bytes
                )
            SQL;

            $params = $this->buildAssetWriteParams($data);
            if (trim((string) ($params['asset_code'] ?? '')) === '') {
                $params['asset_code'] = $this->generateNextAssetCodeValue();
            }

            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return (int) $this->pdo->lastInsertId();
        };

        return $this->withSchemaRetry($operation);
    }

    public function updateAsset(int $id, array $data): bool
    {
        $operation = function () use ($id, $data): bool {
            $sql = <<<SQL
                UPDATE patrimony_assets
                SET
                    asset_code = :asset_code,
                    name = :name,
                    description = :description,
                    category_id = :category_id,
                    subcategory = :subcategory,
                    brand = :brand,
                    model = :model,
                    serial_number = :serial_number,
                    is_tagged = :is_tagged,
                    quantity = :quantity,
                    unit_of_measure = :unit_of_measure,
                    acquisition_type = :acquisition_type,
                    acquisition_date = :acquisition_date,
                    acquisition_value = :acquisition_value,
                    supplier_name = :supplier_name,
                    invoice_number = :invoice_number,
                    purchase_document_path = :purchase_document_path,
                    purchase_document_mime_type = :purchase_document_mime_type,
                    purchase_document_size_bytes = :purchase_document_size_bytes,
                    warranty_expires_at = :warranty_expires_at,
                    payment_method = :payment_method,
                    current_location_id = :current_location_id,
                    current_location_complement = :current_location_complement,
                    current_status = :current_status,
                    conservation_state = :conservation_state,
                    current_responsible = :current_responsible,
                    responsible_department = :responsible_department,
                    last_movement_at = :last_movement_at,
                    notes = :notes,
                    main_photo_path = :main_photo_path,
                    main_photo_mime_type = :main_photo_mime_type,
                    main_photo_size_bytes = :main_photo_size_bytes
                WHERE id = :id
                LIMIT 1
            SQL;

            $params = $this->buildAssetWriteParams($data);
            $params['id'] = $id;

            $statement = $this->pdo->prepare($sql);

            return $statement->execute($params);
        };

        return $this->withSchemaRetry($operation);
    }

    public function deleteAsset(int $id): bool
    {
        $operation = function () use ($id): bool {
            $statement = $this->pdo->prepare('DELETE FROM patrimony_assets WHERE id = :id LIMIT 1');

            return $statement->execute(['id' => $id]);
        };

        return $this->withSchemaRetry($operation);
    }

    public function assetHasLinkedHistory(int $id): bool
    {
        $operation = function () use ($id): bool {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    (
                        SELECT COUNT(*) FROM patrimony_movements WHERE asset_id = :asset_id
                    ) +
                    (
                        SELECT COUNT(*) FROM patrimony_maintenances WHERE asset_id = :asset_id
                    ) +
                    (
                        SELECT COUNT(*) FROM patrimony_disposals WHERE asset_id = :asset_id
                    ) AS total_history
            SQL);
            $statement->execute(['asset_id' => $id]);

            return (int) $statement->fetchColumn() > 0;
        };

        return $this->withSchemaRetry($operation);
    }

    public function findActiveCategories(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    color,
                    is_active
                FROM patrimony_categories
                WHERE is_active = 1
                ORDER BY name ASC
            SQL);

            return $statement->fetchAll() ?: [];
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllCategoriesForAdmin(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    color,
                    is_active,
                    created_at,
                    updated_at
                FROM patrimony_categories
                ORDER BY name ASC
            SQL);

            return $statement->fetchAll() ?: [];
        };

        return $this->withSchemaRetry($operation);
    }

    public function findCategoryByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    color,
                    is_active
                FROM patrimony_categories
                WHERE id = :id
                LIMIT 1
            SQL);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $category = $statement->fetch();

            return $category ?: null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function createCategory(array $data): int
    {
        $operation = function () use ($data): int {
            $statement = $this->pdo->prepare(<<<SQL
                INSERT INTO patrimony_categories (
                    slug,
                    name,
                    description,
                    color,
                    is_active
                ) VALUES (
                    :slug,
                    :name,
                    :description,
                    :color,
                    :is_active
                )
            SQL);
            $statement->execute($this->buildCategoryWriteParams($data));

            return (int) $this->pdo->lastInsertId();
        };

        return $this->withSchemaRetry($operation);
    }

    public function updateCategory(int $id, array $data): bool
    {
        $operation = function () use ($id, $data): bool {
            $statement = $this->pdo->prepare(<<<SQL
                UPDATE patrimony_categories
                SET
                    slug = :slug,
                    name = :name,
                    description = :description,
                    color = :color,
                    is_active = :is_active
                WHERE id = :id
                LIMIT 1
            SQL);

            $params = $this->buildCategoryWriteParams($data);
            $params['id'] = $id;

            return $statement->execute($params);
        };

        return $this->withSchemaRetry($operation);
    }

    public function setCategoryActive(int $id, bool $isActive): bool
    {
        $operation = function () use ($id, $isActive): bool {
            $statement = $this->pdo->prepare(<<<SQL
                UPDATE patrimony_categories
                SET is_active = :is_active
                WHERE id = :id
                LIMIT 1
            SQL);

            return $statement->execute([
                'id' => $id,
                'is_active' => $isActive ? 1 : 0,
            ]);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findActiveLocations(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    name,
                    type,
                    description,
                    is_active,
                    sort_order
                FROM patrimony_locations
                WHERE is_active = 1
                ORDER BY sort_order ASC, name ASC
            SQL);

            return $statement->fetchAll() ?: [];
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllLocationsForAdmin(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    name,
                    type,
                    description,
                    is_active,
                    sort_order,
                    created_at,
                    updated_at
                FROM patrimony_locations
                ORDER BY sort_order ASC, name ASC
            SQL);

            return $statement->fetchAll() ?: [];
        };

        return $this->withSchemaRetry($operation);
    }

    public function findLocationByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    name,
                    type,
                    description,
                    is_active,
                    sort_order
                FROM patrimony_locations
                WHERE id = :id
                LIMIT 1
            SQL);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $location = $statement->fetch();

            return $location ?: null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function recordMovement(int $assetId, array $data): int
    {
        $operation = function () use ($assetId, $data): int {
            $this->pdo->beginTransaction();

            try {
                $asset = $this->findAssetRowForUpdate($assetId);

                if ($asset === null) {
                    throw new \RuntimeException('asset-not-found');
                }

                $destinationLocationId = $this->nullableInteger($data['destination_location_id'] ?? null);
                $destinationLocation = $destinationLocationId !== null
                    ? $this->fetchLocationRow($destinationLocationId)
                    : null;

                $movedAt = $this->nullableDateTime($data['moved_at'] ?? null) ?? date('Y-m-d H:i:s');
                $newResponsible = $this->nullableText($data['new_responsible'] ?? null);
                $newDepartment = $this->nullableText($data['responsible_department'] ?? null);
                $newStatus = $this->nullableText($data['current_status'] ?? null)
                    ?? (string) ($asset['current_status'] ?? 'em_uso');

                $insertStatement = $this->pdo->prepare(<<<SQL
                    INSERT INTO patrimony_movements (
                        asset_id,
                        origin_location_id,
                        origin_location_label,
                        origin_location_complement,
                        destination_location_id,
                        destination_location_label,
                        destination_location_complement,
                        movement_responsible,
                        assigned_responsible,
                        responsible_department,
                        movement_reason,
                        notes,
                        moved_at
                    ) VALUES (
                        :asset_id,
                        :origin_location_id,
                        :origin_location_label,
                        :origin_location_complement,
                        :destination_location_id,
                        :destination_location_label,
                        :destination_location_complement,
                        :movement_responsible,
                        :assigned_responsible,
                        :responsible_department,
                        :movement_reason,
                        :notes,
                        :moved_at
                    )
                SQL);
                $insertStatement->execute([
                    'asset_id' => $assetId,
                    'origin_location_id' => $this->nullableInteger($asset['current_location_id'] ?? null),
                    'origin_location_label' => $this->nullableText($asset['location_name'] ?? null),
                    'origin_location_complement' => $this->nullableText($asset['current_location_complement'] ?? null),
                    'destination_location_id' => $destinationLocationId,
                    'destination_location_label' => $this->nullableText($destinationLocation['name'] ?? null),
                    'destination_location_complement' => $this->nullableText($data['destination_location_complement'] ?? null),
                    'movement_responsible' => (string) ($data['movement_responsible'] ?? ''),
                    'assigned_responsible' => $newResponsible,
                    'responsible_department' => $newDepartment,
                    'movement_reason' => (string) ($data['movement_reason'] ?? ''),
                    'notes' => $this->nullableText($data['notes'] ?? null),
                    'moved_at' => $movedAt,
                ]);

                $movementId = (int) $this->pdo->lastInsertId();

                $updateStatement = $this->pdo->prepare(<<<SQL
                    UPDATE patrimony_assets
                    SET
                        current_location_id = :current_location_id,
                        current_location_complement = :current_location_complement,
                        current_responsible = :current_responsible,
                        responsible_department = :responsible_department,
                        current_status = :current_status,
                        last_movement_at = :last_movement_at
                    WHERE id = :id
                    LIMIT 1
                SQL);
                $updateStatement->execute([
                    'current_location_id' => $destinationLocationId,
                    'current_location_complement' => $this->nullableText($data['destination_location_complement'] ?? null),
                    'current_responsible' => $newResponsible ?? $this->nullableText($asset['current_responsible'] ?? null),
                    'responsible_department' => $newDepartment ?? $this->nullableText($asset['responsible_department'] ?? null),
                    'current_status' => $newStatus,
                    'last_movement_at' => $movedAt,
                    'id' => $assetId,
                ]);

                $this->pdo->commit();

                return $movementId;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $exception;
            }
        };

        return $this->withSchemaRetry($operation);
    }

    public function findMovementsByAssetId(int $assetId): array
    {
        $operation = function () use ($assetId): array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    asset_id,
                    origin_location_id,
                    origin_location_label,
                    origin_location_complement,
                    destination_location_id,
                    destination_location_label,
                    destination_location_complement,
                    movement_responsible,
                    assigned_responsible,
                    responsible_department,
                    movement_reason,
                    notes,
                    moved_at,
                    created_at
                FROM patrimony_movements
                WHERE asset_id = :asset_id
                ORDER BY moved_at DESC, id DESC
            SQL);
            $statement->execute(['asset_id' => $assetId]);

            return array_map([$this, 'normalizeMovement'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findRecentMovements(int $limit = 10): array
    {
        $operation = function () use ($limit): array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    m.id,
                    m.asset_id,
                    a.asset_code,
                    a.name AS asset_name,
                    m.origin_location_id,
                    m.origin_location_label,
                    m.origin_location_complement,
                    m.destination_location_id,
                    m.destination_location_label,
                    m.destination_location_complement,
                    m.movement_responsible,
                    m.assigned_responsible,
                    m.responsible_department,
                    m.movement_reason,
                    m.notes,
                    m.moved_at,
                    m.created_at
                FROM patrimony_movements m
                INNER JOIN patrimony_assets a ON a.id = m.asset_id
                ORDER BY m.moved_at DESC, m.id DESC
                LIMIT :row_limit
            SQL);
            $statement->bindValue(':row_limit', max($limit, 1), \PDO::PARAM_INT);
            $statement->execute();

            return array_map([$this, 'normalizeMovement'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function recordMaintenance(int $assetId, array $data): int
    {
        $operation = function () use ($assetId, $data): int {
            $this->pdo->beginTransaction();

            try {
                $asset = $this->findAssetRowForUpdate($assetId);

                if ($asset === null) {
                    throw new \RuntimeException('asset-not-found');
                }

                $statement = $this->pdo->prepare(<<<SQL
                    INSERT INTO patrimony_maintenances (
                        asset_id,
                        maintenance_date,
                        maintenance_type,
                        vendor_name,
                        cost_amount,
                        service_description,
                        next_maintenance_at,
                        attachment_path,
                        attachment_mime_type,
                        attachment_size_bytes,
                        notes
                    ) VALUES (
                        :asset_id,
                        :maintenance_date,
                        :maintenance_type,
                        :vendor_name,
                        :cost_amount,
                        :service_description,
                        :next_maintenance_at,
                        :attachment_path,
                        :attachment_mime_type,
                        :attachment_size_bytes,
                        :notes
                    )
                SQL);
                $statement->execute([
                    'asset_id' => $assetId,
                    'maintenance_date' => $this->nullableDateTime($data['maintenance_date'] ?? null) ?? date('Y-m-d H:i:s'),
                    'maintenance_type' => (string) ($data['maintenance_type'] ?? ''),
                    'vendor_name' => $this->nullableText($data['vendor_name'] ?? null),
                    'cost_amount' => $this->nullableDecimal($data['cost_amount'] ?? null),
                    'service_description' => (string) ($data['service_description'] ?? ''),
                    'next_maintenance_at' => $this->nullableDate($data['next_maintenance_at'] ?? null),
                    'attachment_path' => $this->nullableText($data['attachment_path'] ?? null),
                    'attachment_mime_type' => $this->nullableText($data['attachment_mime_type'] ?? null),
                    'attachment_size_bytes' => $this->nullableInteger($data['attachment_size_bytes'] ?? null),
                    'notes' => $this->nullableText($data['notes'] ?? null),
                ]);

                $maintenanceId = (int) $this->pdo->lastInsertId();
                $nextStatus = $this->nullableText($data['current_status'] ?? null);

                if ($nextStatus !== null && $nextStatus !== '') {
                    $updateStatement = $this->pdo->prepare(<<<SQL
                        UPDATE patrimony_assets
                        SET current_status = :current_status
                        WHERE id = :id
                        LIMIT 1
                    SQL);
                    $updateStatement->execute([
                        'current_status' => $nextStatus,
                        'id' => $assetId,
                    ]);
                }

                $this->pdo->commit();

                return $maintenanceId;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $exception;
            }
        };

        return $this->withSchemaRetry($operation);
    }

    public function findMaintenancesByAssetId(int $assetId): array
    {
        $operation = function () use ($assetId): array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    asset_id,
                    maintenance_date,
                    maintenance_type,
                    vendor_name,
                    cost_amount,
                    service_description,
                    next_maintenance_at,
                    attachment_path,
                    attachment_mime_type,
                    attachment_size_bytes,
                    notes,
                    created_at
                FROM patrimony_maintenances
                WHERE asset_id = :asset_id
                ORDER BY maintenance_date DESC, id DESC
            SQL);
            $statement->execute(['asset_id' => $assetId]);

            return array_map([$this, 'normalizeMaintenance'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function recordDisposal(int $assetId, array $data): int
    {
        $operation = function () use ($assetId, $data): int {
            $this->pdo->beginTransaction();

            try {
                $asset = $this->findAssetRowForUpdate($assetId);

                if ($asset === null) {
                    throw new \RuntimeException('asset-not-found');
                }

                $disposedAt = $this->nullableDateTime($data['disposed_at'] ?? null) ?? date('Y-m-d H:i:s');

                $statement = $this->pdo->prepare(<<<SQL
                    INSERT INTO patrimony_disposals (
                        asset_id,
                        disposed_at,
                        disposal_reason,
                        disposal_responsible,
                        document_path,
                        document_mime_type,
                        document_size_bytes,
                        notes
                    ) VALUES (
                        :asset_id,
                        :disposed_at,
                        :disposal_reason,
                        :disposal_responsible,
                        :document_path,
                        :document_mime_type,
                        :document_size_bytes,
                        :notes
                    )
                SQL);
                $statement->execute([
                    'asset_id' => $assetId,
                    'disposed_at' => $disposedAt,
                    'disposal_reason' => (string) ($data['disposal_reason'] ?? ''),
                    'disposal_responsible' => (string) ($data['disposal_responsible'] ?? ''),
                    'document_path' => $this->nullableText($data['document_path'] ?? null),
                    'document_mime_type' => $this->nullableText($data['document_mime_type'] ?? null),
                    'document_size_bytes' => $this->nullableInteger($data['document_size_bytes'] ?? null),
                    'notes' => $this->nullableText($data['notes'] ?? null),
                ]);

                $disposalId = (int) $this->pdo->lastInsertId();

                $updateStatement = $this->pdo->prepare(<<<SQL
                    UPDATE patrimony_assets
                    SET
                        current_status = 'baixado',
                        last_movement_at = :last_movement_at
                    WHERE id = :id
                    LIMIT 1
                SQL);
                $updateStatement->execute([
                    'last_movement_at' => $disposedAt,
                    'id' => $assetId,
                ]);

                $this->pdo->commit();

                return $disposalId;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $exception;
            }
        };

        return $this->withSchemaRetry($operation);
    }

    public function findDisposalsByAssetId(int $assetId): array
    {
        $operation = function () use ($assetId): array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    asset_id,
                    disposed_at,
                    disposal_reason,
                    disposal_responsible,
                    document_path,
                    document_mime_type,
                    document_size_bytes,
                    notes,
                    created_at
                FROM patrimony_disposals
                WHERE asset_id = :asset_id
                ORDER BY disposed_at DESC, id DESC
            SQL);
            $statement->execute(['asset_id' => $assetId]);

            return array_map([$this, 'normalizeDisposal'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function addAttachment(int $assetId, array $data): int
    {
        $operation = function () use ($assetId, $data): int {
            $statement = $this->pdo->prepare(<<<SQL
                INSERT INTO patrimony_attachments (
                    asset_id,
                    attachment_type,
                    label,
                    original_file_name,
                    file_path,
                    mime_type,
                    size_bytes,
                    notes
                ) VALUES (
                    :asset_id,
                    :attachment_type,
                    :label,
                    :original_file_name,
                    :file_path,
                    :mime_type,
                    :size_bytes,
                    :notes
                )
            SQL);
            $statement->execute([
                'asset_id' => $assetId,
                'attachment_type' => (string) ($data['attachment_type'] ?? ''),
                'label' => $this->nullableText($data['label'] ?? null),
                'original_file_name' => $this->nullableText($data['original_file_name'] ?? null),
                'file_path' => (string) ($data['file_path'] ?? ''),
                'mime_type' => $this->nullableText($data['mime_type'] ?? null),
                'size_bytes' => $this->nullableInteger($data['size_bytes'] ?? null),
                'notes' => $this->nullableText($data['notes'] ?? null),
            ]);

            return (int) $this->pdo->lastInsertId();
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAttachmentsByAssetId(int $assetId): array
    {
        $operation = function () use ($assetId): array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    asset_id,
                    attachment_type,
                    label,
                    original_file_name,
                    file_path,
                    mime_type,
                    size_bytes,
                    notes,
                    created_at
                FROM patrimony_attachments
                WHERE asset_id = :asset_id
                ORDER BY created_at DESC, id DESC
            SQL);
            $statement->execute(['asset_id' => $assetId]);

            return array_map([$this, 'normalizeAttachment'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAttachmentByIdForAdmin(int $attachmentId): ?array
    {
        $operation = function () use ($attachmentId): ?array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    asset_id,
                    attachment_type,
                    label,
                    original_file_name,
                    file_path,
                    mime_type,
                    size_bytes,
                    notes,
                    created_at
                FROM patrimony_attachments
                WHERE id = :id
                LIMIT 1
            SQL);
            $statement->bindValue(':id', $attachmentId, \PDO::PARAM_INT);
            $statement->execute();

            $attachment = $statement->fetch();

            return $attachment ? $this->normalizeAttachment($attachment) : null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function deleteAttachment(int $attachmentId): bool
    {
        $operation = function () use ($attachmentId): bool {
            $statement = $this->pdo->prepare('DELETE FROM patrimony_attachments WHERE id = :id LIMIT 1');

            return $statement->execute(['id' => $attachmentId]);
        };

        return $this->withSchemaRetry($operation);
    }

    private function buildAssetSelect(): string
    {
        return <<<SQL
            SELECT
                a.id,
                a.asset_code,
                a.name,
                a.description,
                a.category_id,
                a.subcategory,
                a.brand,
                a.model,
                a.serial_number,
                a.is_tagged,
                a.quantity,
                a.unit_of_measure,
                a.acquisition_type,
                a.acquisition_date,
                a.acquisition_value,
                a.supplier_name,
                a.invoice_number,
                a.purchase_document_path,
                a.purchase_document_mime_type,
                a.purchase_document_size_bytes,
                a.warranty_expires_at,
                a.payment_method,
                a.current_location_id,
                a.current_location_complement,
                a.current_status,
                a.conservation_state,
                a.current_responsible,
                a.responsible_department,
                a.last_movement_at,
                a.notes,
                a.main_photo_path,
                a.main_photo_mime_type,
                a.main_photo_size_bytes,
                a.created_at,
                a.updated_at,
                c.name AS category_name,
                c.slug AS category_slug,
                c.color AS category_color,
                c.is_active AS category_is_active,
                l.name AS location_name,
                l.type AS location_type
            FROM patrimony_assets a
            LEFT JOIN patrimony_categories c ON c.id = a.category_id
            LEFT JOIN patrimony_locations l ON l.id = a.current_location_id
        SQL;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildAssetWriteParams(array $data): array
    {
        return [
            'asset_code' => trim((string) ($data['asset_code'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => $this->nullableText($data['description'] ?? null),
            'category_id' => $this->nullableInteger($data['category_id'] ?? null),
            'subcategory' => $this->nullableText($data['subcategory'] ?? null),
            'brand' => $this->nullableText($data['brand'] ?? null),
            'model' => $this->nullableText($data['model'] ?? null),
            'serial_number' => $this->nullableText($data['serial_number'] ?? null),
            'is_tagged' => ((int) ($data['is_tagged'] ?? 0)) === 1 ? 1 : 0,
            'quantity' => $this->nullableDecimal($data['quantity'] ?? null, 3) ?? '1.000',
            'unit_of_measure' => trim((string) ($data['unit_of_measure'] ?? 'un')),
            'acquisition_type' => trim((string) ($data['acquisition_type'] ?? 'outro')),
            'acquisition_date' => $this->nullableDate($data['acquisition_date'] ?? null),
            'acquisition_value' => $this->nullableDecimal($data['acquisition_value'] ?? null),
            'supplier_name' => $this->nullableText($data['supplier_name'] ?? null),
            'invoice_number' => $this->nullableText($data['invoice_number'] ?? null),
            'purchase_document_path' => $this->nullableText($data['purchase_document_path'] ?? null),
            'purchase_document_mime_type' => $this->nullableText($data['purchase_document_mime_type'] ?? null),
            'purchase_document_size_bytes' => $this->nullableInteger($data['purchase_document_size_bytes'] ?? null),
            'warranty_expires_at' => $this->nullableDate($data['warranty_expires_at'] ?? null),
            'payment_method' => $this->nullableText($data['payment_method'] ?? null),
            'current_location_id' => $this->nullableInteger($data['current_location_id'] ?? null),
            'current_location_complement' => $this->nullableText($data['current_location_complement'] ?? null),
            'current_status' => trim((string) ($data['current_status'] ?? 'em_uso')),
            'conservation_state' => trim((string) ($data['conservation_state'] ?? 'bom')),
            'current_responsible' => $this->nullableText($data['current_responsible'] ?? null),
            'responsible_department' => $this->nullableText($data['responsible_department'] ?? null),
            'last_movement_at' => $this->nullableDateTime($data['last_movement_at'] ?? null),
            'notes' => $this->nullableText($data['notes'] ?? null),
            'main_photo_path' => $this->nullableText($data['main_photo_path'] ?? null),
            'main_photo_mime_type' => $this->nullableText($data['main_photo_mime_type'] ?? null),
            'main_photo_size_bytes' => $this->nullableInteger($data['main_photo_size_bytes'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildCategoryWriteParams(array $data): array
    {
        return [
            'slug' => trim((string) ($data['slug'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => $this->nullableText($data['description'] ?? null),
            'color' => $this->nullableText($data['color'] ?? null),
            'is_active' => ((int) ($data['is_active'] ?? 0)) === 1 ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $asset
     * @return array<string, mixed>
     */
    private function normalizeAsset(array $asset): array
    {
        $mainPhotoPath = ltrim((string) ($asset['main_photo_path'] ?? ''), '/');
        $purchaseDocumentPath = ltrim((string) ($asset['purchase_document_path'] ?? ''), '/');
        $quantity = isset($asset['quantity']) ? (float) $asset['quantity'] : 0.0;
        $acquisitionValue = isset($asset['acquisition_value']) && $asset['acquisition_value'] !== null
            ? (float) $asset['acquisition_value']
            : null;
        $mainPhotoSize = isset($asset['main_photo_size_bytes']) && $asset['main_photo_size_bytes'] !== null
            ? (int) $asset['main_photo_size_bytes']
            : null;
        $purchaseDocumentSize = isset($asset['purchase_document_size_bytes']) && $asset['purchase_document_size_bytes'] !== null
            ? (int) $asset['purchase_document_size_bytes']
            : null;
        $warrantyDaysRemaining = $this->daysUntil((string) ($asset['warranty_expires_at'] ?? ''));
        $locationDisplay = trim(implode(' • ', array_filter([
            trim((string) ($asset['location_name'] ?? '')),
            trim((string) ($asset['current_location_complement'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));

        return array_merge($asset, [
            'is_tagged' => ((int) ($asset['is_tagged'] ?? 0)) === 1,
            'quantity' => $quantity,
            'quantity_label' => $this->formatQuantity($quantity),
            'acquisition_value' => $acquisitionValue,
            'acquisition_value_label' => $acquisitionValue !== null ? $this->formatCurrency($acquisitionValue) : '',
            'main_photo_size_bytes' => $mainPhotoSize,
            'main_photo_url' => ManagedPublicMediaPath::toUrl($mainPhotoPath, 'media/patrimonio/img'),
            'purchase_document_size_bytes' => $purchaseDocumentSize,
            'purchase_document_url' => ManagedPublicMediaPath::toUrl(
                $purchaseDocumentPath,
                'media/patrimonio/docs'
            ),
            'purchase_document_size_label' => $purchaseDocumentSize !== null ? $this->formatBytes($purchaseDocumentSize) : '',
            'current_status_label' => $this->formatStatusLabel((string) ($asset['current_status'] ?? '')),
            'conservation_state_label' => $this->formatConservationLabel((string) ($asset['conservation_state'] ?? '')),
            'acquisition_type_label' => $this->formatAcquisitionTypeLabel((string) ($asset['acquisition_type'] ?? '')),
            'current_location_display' => $locationDisplay !== '' ? $locationDisplay : '-',
            'warranty_days_remaining' => $warrantyDaysRemaining,
            'warranty_expires_soon' => $warrantyDaysRemaining !== null && $warrantyDaysRemaining >= 0 && $warrantyDaysRemaining <= 45,
            'warranty_expired' => $warrantyDaysRemaining !== null && $warrantyDaysRemaining < 0,
            'main_photo_size_label' => $mainPhotoSize !== null ? $this->formatBytes($mainPhotoSize) : '',
        ]);
    }

    /**
     * @param array<string, mixed> $movement
     * @return array<string, mixed>
     */
    private function normalizeMovement(array $movement): array
    {
        $originDisplay = trim(implode(' • ', array_filter([
            trim((string) ($movement['origin_location_label'] ?? '')),
            trim((string) ($movement['origin_location_complement'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));
        $destinationDisplay = trim(implode(' • ', array_filter([
            trim((string) ($movement['destination_location_label'] ?? '')),
            trim((string) ($movement['destination_location_complement'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));

        return array_merge($movement, [
            'origin_display' => $originDisplay !== '' ? $originDisplay : '-',
            'destination_display' => $destinationDisplay !== '' ? $destinationDisplay : '-',
        ]);
    }

    /**
     * @param array<string, mixed> $maintenance
     * @return array<string, mixed>
     */
    private function normalizeMaintenance(array $maintenance): array
    {
        $attachmentPath = ltrim((string) ($maintenance['attachment_path'] ?? ''), '/');
        $sizeBytes = isset($maintenance['attachment_size_bytes']) && $maintenance['attachment_size_bytes'] !== null
            ? (int) $maintenance['attachment_size_bytes']
            : null;
        $costAmount = isset($maintenance['cost_amount']) && $maintenance['cost_amount'] !== null
            ? (float) $maintenance['cost_amount']
            : null;

        return array_merge($maintenance, [
            'attachment_url' => ManagedPublicMediaPath::toUrl($attachmentPath, 'media/patrimonio/docs'),
            'attachment_size_bytes' => $sizeBytes,
            'attachment_size_label' => $sizeBytes !== null ? $this->formatBytes($sizeBytes) : '',
            'cost_amount' => $costAmount,
            'cost_amount_label' => $costAmount !== null ? $this->formatCurrency($costAmount) : '',
        ]);
    }

    /**
     * @param array<string, mixed> $disposal
     * @return array<string, mixed>
     */
    private function normalizeDisposal(array $disposal): array
    {
        $documentPath = ltrim((string) ($disposal['document_path'] ?? ''), '/');
        $sizeBytes = isset($disposal['document_size_bytes']) && $disposal['document_size_bytes'] !== null
            ? (int) $disposal['document_size_bytes']
            : null;

        return array_merge($disposal, [
            'document_url' => ManagedPublicMediaPath::toUrl($documentPath, 'media/patrimonio/docs'),
            'document_size_bytes' => $sizeBytes,
            'document_size_label' => $sizeBytes !== null ? $this->formatBytes($sizeBytes) : '',
        ]);
    }

    /**
     * @param array<string, mixed> $attachment
     * @return array<string, mixed>
     */
    private function normalizeAttachment(array $attachment): array
    {
        $filePath = ltrim((string) ($attachment['file_path'] ?? ''), '/');
        $sizeBytes = isset($attachment['size_bytes']) && $attachment['size_bytes'] !== null
            ? (int) $attachment['size_bytes']
            : null;

        return array_merge($attachment, [
            'file_url' => ManagedPublicMediaPath::toUrl($filePath, 'media/patrimonio/docs'),
            'size_bytes' => $sizeBytes,
            'size_label' => $sizeBytes !== null ? $this->formatBytes($sizeBytes) : '',
            'attachment_type_label' => $this->formatAttachmentTypeLabel((string) ($attachment['attachment_type'] ?? '')),
        ]);
    }

    private function generateNextAssetCodeValue(): string
    {
        $statement = $this->pdo->query(<<<SQL
            SELECT MAX(CAST(SUBSTRING(asset_code, 5) AS UNSIGNED))
            FROM patrimony_assets
            WHERE asset_code REGEXP '^PAT-[0-9]{6}$'
        SQL);
        $currentMax = (int) $statement->fetchColumn();

        return sprintf('PAT-%06d', $currentMax + 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAssetRowForUpdate(int $assetId): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT
                a.id,
                a.current_location_id,
                a.current_location_complement,
                a.current_status,
                a.current_responsible,
                a.responsible_department,
                l.name AS location_name
            FROM patrimony_assets a
            LEFT JOIN patrimony_locations l ON l.id = a.current_location_id
            WHERE a.id = :id
            LIMIT 1
            FOR UPDATE
        SQL);
        $statement->execute(['id' => $assetId]);

        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchLocationRow(int $locationId): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT
                id,
                name,
                type,
                description,
                is_active
            FROM patrimony_locations
            WHERE id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $locationId]);

        $row = $statement->fetch();

        return $row ?: null;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function nullableDecimal(mixed $value, int $scale = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', 'R$', '.'], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, $scale, '.', '');
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function nullableDateTime(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, $unitIndex === 0 ? 0 : 1, ',', '.') . ' ' . $units[$unitIndex];
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function formatQuantity(float $value): string
    {
        if (abs($value - (float) (int) $value) < 0.00001) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
    }

    private function formatAcquisitionTypeLabel(string $type): string
    {
        $map = [
            'compra' => 'Compra',
            'doacao' => 'Doação',
            'campanha_beneficente' => 'Campanha beneficente',
            'contribuicao_trabalhador' => 'Contribuição de trabalhador',
            'transferencia' => 'Transferência',
            'permuta' => 'Permuta',
            'comodato' => 'Comodato',
            'inventario_inicial' => 'Inventário inicial',
            'outro' => 'Outro',
        ];

        return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    private function formatStatusLabel(string $status): string
    {
        $map = [
            'em_uso' => 'Em uso',
            'em_estoque' => 'Em estoque',
            'reservado' => 'Reservado',
            'em_manutencao' => 'Em manutenção',
            'emprestado' => 'Emprestado',
            'danificado' => 'Danificado',
            'baixado' => 'Baixado',
            'extraviado' => 'Extraviado',
        ];

        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private function formatConservationLabel(string $state): string
    {
        $map = [
            'novo' => 'Novo',
            'excelente' => 'Excelente',
            'bom' => 'Bom',
            'regular' => 'Regular',
            'ruim' => 'Ruim',
            'inservivel' => 'Inservível',
        ];

        return $map[$state] ?? ucfirst(str_replace('_', ' ', $state));
    }

    private function formatAttachmentTypeLabel(string $type): string
    {
        $map = [
            'foto_complementar' => 'Foto complementar',
            'manual' => 'Manual',
            'nota_fiscal' => 'Nota fiscal',
            'garantia' => 'Garantia',
            'certificado' => 'Certificado',
            'outro' => 'Outro',
        ];

        return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    private function daysUntil(string $date): ?int
    {
        $normalized = trim($date);

        if ($normalized === '') {
            return null;
        }

        try {
            $target = new \DateTimeImmutable($normalized . ' 00:00:00');
            $today = new \DateTimeImmutable(date('Y-m-d') . ' 00:00:00');
        } catch (\Throwable $exception) {
            return null;
        }

        return (int) $today->diff($target)->format('%r%a');
    }

    /**
     * @param callable(): mixed $operation
     * @return mixed
     */
    private function withSchemaRetry(callable $operation)
    {
        try {
            return $operation();
        } catch (\Throwable $exception) {
            if ($this->schemaEnsured) {
                throw $exception;
            }

            $this->ensurePatrimonySchemaCompatibility();

            return $operation();
        }
    }

    private function ensurePatrimonySchemaCompatibility(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS patrimony_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(120) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                color VARCHAR(20) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_patrimony_categories_name (name),
                INDEX idx_patrimony_categories_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS patrimony_locations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL UNIQUE,
                type VARCHAR(60) NOT NULL DEFAULT 'interno',
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_patrimony_locations_name (name),
                INDEX idx_patrimony_locations_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS patrimony_assets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                asset_code VARCHAR(40) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                category_id BIGINT UNSIGNED NULL,
                subcategory VARCHAR(160) NULL,
                brand VARCHAR(160) NULL,
                model VARCHAR(160) NULL,
                serial_number VARCHAR(120) NULL,
                is_tagged TINYINT(1) NOT NULL DEFAULT 0,
                quantity DECIMAL(12, 3) NOT NULL DEFAULT 1.000,
                unit_of_measure VARCHAR(60) NOT NULL DEFAULT 'un',
                acquisition_type VARCHAR(40) NOT NULL DEFAULT 'outro',
                acquisition_date DATE NULL,
                acquisition_value DECIMAL(12, 2) NULL,
                supplier_name VARCHAR(255) NULL,
                invoice_number VARCHAR(120) NULL,
                purchase_document_path VARCHAR(255) NULL,
                purchase_document_mime_type VARCHAR(120) NULL,
                purchase_document_size_bytes BIGINT UNSIGNED NULL,
                warranty_expires_at DATE NULL,
                payment_method VARCHAR(120) NULL,
                current_location_id BIGINT UNSIGNED NULL,
                current_location_complement VARCHAR(160) NULL,
                current_status VARCHAR(30) NOT NULL DEFAULT 'em_uso',
                conservation_state VARCHAR(30) NOT NULL DEFAULT 'bom',
                current_responsible VARCHAR(255) NULL,
                responsible_department VARCHAR(255) NULL,
                last_movement_at DATETIME NULL,
                notes TEXT NULL,
                main_photo_path VARCHAR(255) NULL,
                main_photo_mime_type VARCHAR(120) NULL,
                main_photo_size_bytes BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_patrimony_assets_category
                    FOREIGN KEY (category_id) REFERENCES patrimony_categories(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL,
                CONSTRAINT fk_patrimony_assets_location
                    FOREIGN KEY (current_location_id) REFERENCES patrimony_locations(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL,
                INDEX idx_patrimony_assets_name (name),
                INDEX idx_patrimony_assets_category (category_id),
                INDEX idx_patrimony_assets_location (current_location_id),
                INDEX idx_patrimony_assets_status (current_status),
                INDEX idx_patrimony_assets_warranty (warranty_expires_at),
                INDEX idx_patrimony_assets_acquisition_date (acquisition_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS patrimony_movements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                asset_id BIGINT UNSIGNED NOT NULL,
                origin_location_id BIGINT UNSIGNED NULL,
                origin_location_label VARCHAR(160) NULL,
                origin_location_complement VARCHAR(160) NULL,
                destination_location_id BIGINT UNSIGNED NULL,
                destination_location_label VARCHAR(160) NULL,
                destination_location_complement VARCHAR(160) NULL,
                movement_responsible VARCHAR(255) NOT NULL,
                assigned_responsible VARCHAR(255) NULL,
                responsible_department VARCHAR(255) NULL,
                movement_reason VARCHAR(255) NOT NULL,
                notes TEXT NULL,
                moved_at DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_patrimony_movements_asset
                    FOREIGN KEY (asset_id) REFERENCES patrimony_assets(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                INDEX idx_patrimony_movements_asset (asset_id),
                INDEX idx_patrimony_movements_moved_at (moved_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS patrimony_maintenances (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                asset_id BIGINT UNSIGNED NOT NULL,
                maintenance_date DATETIME NOT NULL,
                maintenance_type VARCHAR(160) NOT NULL,
                vendor_name VARCHAR(255) NULL,
                cost_amount DECIMAL(12, 2) NULL,
                service_description TEXT NOT NULL,
                next_maintenance_at DATE NULL,
                attachment_path VARCHAR(255) NULL,
                attachment_mime_type VARCHAR(120) NULL,
                attachment_size_bytes BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_patrimony_maintenances_asset
                    FOREIGN KEY (asset_id) REFERENCES patrimony_assets(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                INDEX idx_patrimony_maintenances_asset (asset_id),
                INDEX idx_patrimony_maintenances_date (maintenance_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS patrimony_disposals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                asset_id BIGINT UNSIGNED NOT NULL,
                disposed_at DATETIME NOT NULL,
                disposal_reason VARCHAR(160) NOT NULL,
                disposal_responsible VARCHAR(255) NOT NULL,
                document_path VARCHAR(255) NULL,
                document_mime_type VARCHAR(120) NULL,
                document_size_bytes BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_patrimony_disposals_asset
                    FOREIGN KEY (asset_id) REFERENCES patrimony_assets(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                INDEX idx_patrimony_disposals_asset (asset_id),
                INDEX idx_patrimony_disposals_date (disposed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS patrimony_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                asset_id BIGINT UNSIGNED NOT NULL,
                attachment_type VARCHAR(60) NOT NULL DEFAULT 'outro',
                label VARCHAR(255) NULL,
                original_file_name VARCHAR(255) NULL,
                file_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NULL,
                size_bytes BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_patrimony_attachments_asset
                    FOREIGN KEY (asset_id) REFERENCES patrimony_assets(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE,
                INDEX idx_patrimony_attachments_asset (asset_id),
                INDEX idx_patrimony_attachments_type (attachment_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            INSERT INTO patrimony_categories (slug, name, description, color, is_active)
            VALUES
                ('informatica', 'Informática', 'Computadores, impressoras, periféricos e acessórios.', '#275dad', 1),
                ('moveis', 'Móveis', 'Mesas, cadeiras, armários, estantes e similares.', '#6f4e37', 1),
                ('equipamentos-de-som', 'Equipamentos de Som', 'Caixas, microfones, mesas e acessórios de áudio.', '#2e8b57', 1),
                ('equipamentos-de-video', 'Equipamentos de Vídeo', 'Projetores, televisores, câmeras e telas.', '#a64d79', 1),
                ('eletrodomesticos', 'Eletrodomésticos', 'Geladeiras, fogões, ventiladores e similares.', '#b36b00', 1),
                ('equipamentos-administrativos', 'Equipamentos Administrativos', 'Itens usados na secretaria e administração.', '#1f6f5f', 1),
                ('equipamentos-de-seguranca', 'Equipamentos de Segurança', 'Extintores, alarmes e sinalização.', '#b22222', 1),
                ('ferramentas', 'Ferramentas', 'Ferramentas e itens de apoio à manutenção.', '#4f6d7a', 1),
                ('outros', 'Outros', 'Demais itens patrimoniais do CEDE.', '#555555', 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                color = VALUES(color),
                is_active = VALUES(is_active)
        SQL);

        $this->pdo->exec(<<<SQL
            INSERT INTO patrimony_locations (name, type, description, is_active, sort_order)
            VALUES
                ('Recepção', 'interno', 'Área principal de recepção.', 1, 10),
                ('Secretaria', 'interno', 'Sala administrativa da secretaria.', 1, 20),
                ('Diretoria', 'interno', 'Sala da diretoria.', 1, 30),
                ('Sala de Atendimento Fraterno', 'interno', 'Espaço de atendimento fraterno.', 1, 40),
                ('Sala de Passes', 'interno', 'Sala de passes.', 1, 50),
                ('Sala de Estudos 01', 'interno', 'Primeira sala de estudos.', 1, 60),
                ('Sala de Estudos 02', 'interno', 'Segunda sala de estudos.', 1, 70),
                ('Biblioteca', 'interno', 'Biblioteca do CEDE.', 1, 80),
                ('Livraria', 'interno', 'Espaço da livraria.', 1, 90),
                ('Auditório', 'interno', 'Auditório principal.', 1, 100),
                ('Evangelização Infantil', 'interno', 'Sala da evangelização infantil.', 1, 110),
                ('Juventude', 'interno', 'Espaço das atividades da juventude.', 1, 120),
                ('Cozinha', 'interno', 'Cozinha da instituição.', 1, 130),
                ('Cantina', 'interno', 'Cantina do CEDE.', 1, 140),
                ('Almoxarifado', 'interno', 'Estoque de materiais.', 1, 150),
                ('Depósito', 'interno', 'Depósito de apoio.', 1, 160),
                ('Área Externa', 'externo', 'Área externa do imóvel.', 1, 170),
                ('Jardim', 'externo', 'Jardim e áreas verdes.', 1, 180),
                ('Estacionamento', 'externo', 'Estacionamento.', 1, 190),
                ('Sala de Som', 'interno', 'Sala de equipamentos de áudio.', 1, 200),
                ('Sala de Multimídia', 'interno', 'Sala de recursos multimídia.', 1, 210),
                ('Cabine de Transmissão', 'interno', 'Cabine de transmissão e apoio técnico.', 1, 220),
                ('Administração', 'interno', 'Área administrativa geral.', 1, 230),
                ('Outro', 'variavel', 'Localização personalizada.', 1, 999)
            ON DUPLICATE KEY UPDATE
                type = VALUES(type),
                description = VALUES(description),
                is_active = VALUES(is_active),
                sort_order = VALUES(sort_order)
        SQL);

        $this->ensureIndex(
            'patrimony_assets',
            'idx_patrimony_assets_code',
            'ALTER TABLE patrimony_assets ADD INDEX idx_patrimony_assets_code (asset_code)'
        );

        $this->schemaEnsured = true;
    }

    private function ensureIndex(string $tableName, string $indexName, string $alterSql): void
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name '
            . 'AND INDEX_NAME = :index_name'
        );
        $statement->execute([
            'table_name' => $tableName,
            'index_name' => $indexName,
        ]);

        if ((int) $statement->fetchColumn() === 0) {
            $this->pdo->exec($alterSql);
        }
    }
}
