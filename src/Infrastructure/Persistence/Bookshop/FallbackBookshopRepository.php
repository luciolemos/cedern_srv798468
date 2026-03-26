<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Bookshop;

use App\Domain\Bookshop\BookshopRepository;

class FallbackBookshopRepository implements BookshopRepository
{
    public function findCatalogBooks(): array
    {
        return [];
    }

    public function findCatalogCategories(): array
    {
        return [];
    }

    public function findCatalogGenres(): array
    {
        return [];
    }

    public function findAllBooksForAdmin(): array
    {
        return [];
    }

    public function findBookByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function findStockLotsByBookIdForAdmin(int $bookId): array
    {
        return [];
    }

    public function findBookBySku(string $sku): ?array
    {
        return null;
    }

    public function findBookByIsbn(string $isbn): ?array
    {
        return null;
    }

    public function createBook(array $data): int
    {
        return 0;
    }

    public function updateBook(int $id, array $data): bool
    {
        return false;
    }

    public function deleteBook(int $id): bool
    {
        return false;
    }

    public function findAllCategoriesForAdmin(): array
    {
        return [];
    }

    public function findCategoryByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function findAllGenresForAdmin(): array
    {
        return [];
    }

    public function findGenreByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function findAllCollectionsForAdmin(): array
    {
        return [];
    }

    public function findCollectionByIdForAdmin(int $id): ?array
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

    public function createGenre(array $data): int
    {
        return 0;
    }

    public function updateGenre(int $id, array $data): bool
    {
        return false;
    }

    public function setGenreActive(int $id, bool $isActive): bool
    {
        return false;
    }

    public function createCollection(array $data): int
    {
        return 0;
    }

    public function updateCollection(int $id, array $data): bool
    {
        return false;
    }

    public function setCollectionActive(int $id, bool $isActive): bool
    {
        return false;
    }

    public function findAllSalesForAdmin(): array
    {
        return [];
    }

    public function findAllStockMovementsForAdmin(): array
    {
        return [];
    }

    public function findSaleByIdForAdmin(int $id): ?array
    {
        return null;
    }

    public function createSale(array $saleData, array $items): int
    {
        return 0;
    }

    public function createStockMovement(array $data): int
    {
        return 0;
    }

    public function cancelSale(int $id, ?int $memberId = null, ?string $memberName = null): bool
    {
        return false;
    }
}
