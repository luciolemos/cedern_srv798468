<?php

declare(strict_types=1);

namespace App\Domain\Bookshop;

interface BookshopRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function findCatalogBooks(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findCatalogCategories(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findCatalogGenres(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllBooksForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findBookByIdForAdmin(int $id): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findStockLotsByBookIdForAdmin(int $bookId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findBookBySku(string $sku): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findBookByIsbn(string $isbn): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function createBook(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function updateBook(int $id, array $data): bool;

    public function deleteBook(int $id): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllCategoriesForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findCategoryByIdForAdmin(int $id): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllGenresForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findGenreByIdForAdmin(int $id): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllCollectionsForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findCollectionByIdForAdmin(int $id): ?array;

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
     * @param array<string, mixed> $data
     */
    public function createGenre(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function updateGenre(int $id, array $data): bool;

    public function setGenreActive(int $id, bool $isActive): bool;

    /**
     * @param array<string, mixed> $data
     */
    public function createCollection(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function updateCollection(int $id, array $data): bool;

    public function setCollectionActive(int $id, bool $isActive): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllSalesForAdmin(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllStockMovementsForAdmin(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findSaleByIdForAdmin(int $id): ?array;

    /**
     * @param array<string, mixed> $saleData
     * @param array<int, array<string, mixed>> $items
     */
    public function createSale(array $saleData, array $items): int;

    /**
     * @param array<string, mixed> $data
     */
    public function createStockMovement(array $data): int;

    public function cancelSale(int $id, ?int $memberId = null, ?string $memberName = null): bool;
}
