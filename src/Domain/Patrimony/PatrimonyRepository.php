<?php

declare(strict_types=1);

namespace App\Domain\Patrimony;

interface PatrimonyRepository
{
    public function generateNextAssetCode(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllAssetsForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findAssetByIdForAdmin(int $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function createAsset(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function updateAsset(int $id, array $data): bool;

    public function deleteAsset(int $id): bool;

    public function assetHasLinkedHistory(int $id): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveCategories(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllCategoriesForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findCategoryByIdForAdmin(int $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function createCategory(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function updateCategory(int $id, array $data): bool;

    public function setCategoryActive(int $id, bool $isActive): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveLocations(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllLocationsForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findLocationByIdForAdmin(int $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function recordMovement(int $assetId, array $data): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMovementsByAssetId(int $assetId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecentMovements(int $limit = 10): array;

    /**
     * @param array<string, mixed> $data
     */
    public function recordMaintenance(int $assetId, array $data): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMaintenancesByAssetId(int $assetId): array;

    /**
     * @param array<string, mixed> $data
     */
    public function recordDisposal(int $assetId, array $data): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDisposalsByAssetId(int $assetId): array;

    /**
     * @param array<string, mixed> $data
     */
    public function addAttachment(int $assetId, array $data): int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAttachmentsByAssetId(int $assetId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findAttachmentByIdForAdmin(int $attachmentId): ?array;

    public function deleteAttachment(int $attachmentId): bool;
}
