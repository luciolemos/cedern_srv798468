<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Patrimony;

use App\Domain\Patrimony\PatrimonyRepository;

class FallbackPatrimonyRepository implements PatrimonyRepository
{
    public function generateNextAssetCode(): string
    {
        return 'PAT-000001';
    }

    public function findAllAssetsForAdmin(): array
    {
        return [];
    }

    public function findAssetByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function createAsset(array $data): int
    {
        return 0;
    }

    public function updateAsset(int $id, array $data): bool
    {
        return false;
    }

    public function deleteAsset(int $id): bool
    {
        return false;
    }

    public function assetHasLinkedHistory(int $id): bool
    {
        return false;
    }

    public function findActiveCategories(): array
    {
        return [];
    }

    public function findAllCategoriesForAdmin(): array
    {
        return [];
    }

    public function findCategoryByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function createCategory(array $data): int
    {
        return 0;
    }

    public function updateCategory(int $id, array $data): bool
    {
        return false;
    }

    public function setCategoryActive(int $id, bool $isActive): bool
    {
        return false;
    }

    public function findActiveLocations(): array
    {
        return [];
    }

    public function findAllLocationsForAdmin(): array
    {
        return [];
    }

    public function findLocationByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function recordMovement(int $assetId, array $data): int
    {
        return 0;
    }

    public function findMovementsByAssetId(int $assetId): array
    {
        return [];
    }

    public function findRecentMovements(int $limit = 10): array
    {
        return [];
    }

    public function recordMaintenance(int $assetId, array $data): int
    {
        return 0;
    }

    public function findMaintenancesByAssetId(int $assetId): array
    {
        return [];
    }

    public function recordDisposal(int $assetId, array $data): int
    {
        return 0;
    }

    public function findDisposalsByAssetId(int $assetId): array
    {
        return [];
    }

    public function addAttachment(int $assetId, array $data): int
    {
        return 0;
    }

    public function findAttachmentsByAssetId(int $assetId): array
    {
        return [];
    }

    public function findAttachmentByIdForAdmin(int $attachmentId): ?array
    {
        return null;
    }

    public function deleteAttachment(int $attachmentId): bool
    {
        return false;
    }
}
