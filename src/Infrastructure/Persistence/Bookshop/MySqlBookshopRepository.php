<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Bookshop;

use App\Domain\Bookshop\BookshopRepository;
use App\Support\BookshopTextNormalizer;

class MySqlBookshopRepository implements BookshopRepository
{
    private const SALE_STORAGE_TIMEZONE = 'UTC';

    private const SALE_DISPLAY_TIMEZONE = 'America/Fortaleza';

    private \PDO $pdo;

    private bool $schemaEnsured = false;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findCatalogBooks(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(
                $this->buildBookSelect()
                . " WHERE b.status = 'active' ORDER BY b.title ASC, b.author_name ASC"
            );

            return array_map([$this, 'normalizeBook'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findCatalogCategories(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    is_active,
                    created_at,
                    updated_at
                FROM bookshop_categories
                WHERE is_active = 1
                ORDER BY name ASC
            SQL);

            return array_map([$this, 'normalizeCategory'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findCatalogGenres(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    is_active,
                    created_at,
                    updated_at
                FROM bookshop_genres
                WHERE is_active = 1
                ORDER BY name ASC
            SQL);

            return array_map([$this, 'normalizeGenre'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllBooksForAdmin(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query($this->buildBookSelect() . ' ORDER BY b.title ASC, b.author_name ASC');
            $books = array_map([$this, 'normalizeBook'], $statement->fetchAll() ?: []);

            return $this->attachAvailableStockLotsToBooks($books);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findBookByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $statement = $this->pdo->prepare($this->buildBookSelect() . ' WHERE b.id = :id LIMIT 1');
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $book = $statement->fetch();

            if (!$book) {
                return null;
            }

            $books = $this->attachAvailableStockLotsToBooks([$this->normalizeBook($book)]);

            return $books[0] ?? null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function findBookBySku(string $sku): ?array
    {
        $normalizedSku = trim($sku);
        if ($normalizedSku === '') {
            return null;
        }

        $operation = function () use ($normalizedSku): ?array {
            $statement = $this->pdo->prepare($this->buildBookSelect() . ' WHERE b.sku = :sku LIMIT 1');
            $statement->bindValue(':sku', $normalizedSku, \PDO::PARAM_STR);
            $statement->execute();

            $book = $statement->fetch();

            if (!$book) {
                return null;
            }

            $books = $this->attachAvailableStockLotsToBooks([$this->normalizeBook($book)]);

            return $books[0] ?? null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function findBookByIsbn(string $isbn): ?array
    {
        $normalizedIsbn = trim($isbn);
        if ($normalizedIsbn === '') {
            return null;
        }

        $operation = function () use ($normalizedIsbn): ?array {
            $statement = $this->pdo->prepare($this->buildBookSelect() . ' WHERE b.isbn = :isbn LIMIT 1');
            $statement->bindValue(':isbn', $normalizedIsbn, \PDO::PARAM_STR);
            $statement->execute();

            $book = $statement->fetch();

            if (!$book) {
                return null;
            }

            $books = $this->attachAvailableStockLotsToBooks([$this->normalizeBook($book)]);

            return $books[0] ?? null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function createBook(array $data): int
    {
        $operation = function () use ($data): int {
            $sql = <<<SQL
                INSERT INTO bookshop_books (
                    sku,
                    slug,
                    category_id,
                    category_name,
                    genre_id,
                    genre_name,
                    collection_id,
                    collection_name,
                    title,
                    subtitle,
                    author_name,
                    publisher_name,
                    isbn,
                    barcode,
                    edition_label,
                    volume_number,
                    volume_label,
                    publication_year,
                    page_count,
                    language,
                    description,
                    cover_image_path,
                    cover_image_mime_type,
                    cover_image_size_bytes,
                    cost_price,
                    sale_price,
                    stock_quantity,
                    stock_minimum,
                    status,
                    location_label
                ) VALUES (
                    :sku,
                    :slug,
                    :category_id,
                    :category_name,
                    :genre_id,
                    :genre_name,
                    :collection_id,
                    :collection_name,
                    :title,
                    :subtitle,
                    :author_name,
                    :publisher_name,
                    :isbn,
                    :barcode,
                    :edition_label,
                    :volume_number,
                    :volume_label,
                    :publication_year,
                    :page_count,
                    :language,
                    :description,
                    :cover_image_path,
                    :cover_image_mime_type,
                    :cover_image_size_bytes,
                    :cost_price,
                    :sale_price,
                    :stock_quantity,
                    :stock_minimum,
                    :status,
                    :location_label
                )
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->execute($this->buildBookWriteParams($data));

            return (int) $this->pdo->lastInsertId();
        };

        return $this->withSchemaRetry($operation);
    }

    public function updateBook(int $id, array $data): bool
    {
        $operation = function () use ($id, $data): bool {
            $sql = <<<SQL
                UPDATE bookshop_books
                SET
                    sku = :sku,
                    slug = :slug,
                    category_id = :category_id,
                    category_name = :category_name,
                    genre_id = :genre_id,
                    genre_name = :genre_name,
                    collection_id = :collection_id,
                    collection_name = :collection_name,
                    title = :title,
                    subtitle = :subtitle,
                    author_name = :author_name,
                    publisher_name = :publisher_name,
                    isbn = :isbn,
                    barcode = :barcode,
                    edition_label = :edition_label,
                    volume_number = :volume_number,
                    volume_label = :volume_label,
                    publication_year = :publication_year,
                    page_count = :page_count,
                    language = :language,
                    description = :description,
                    cover_image_path = :cover_image_path,
                    cover_image_mime_type = :cover_image_mime_type,
                    cover_image_size_bytes = :cover_image_size_bytes,
                    cost_price = :cost_price,
                    sale_price = :sale_price,
                    stock_quantity = :stock_quantity,
                    stock_minimum = :stock_minimum,
                    status = :status,
                    location_label = :location_label
                WHERE id = :id
                LIMIT 1
            SQL;

            $statement = $this->pdo->prepare($sql);
            $params = $this->buildBookWriteParams($data);
            $params['id'] = $id;

            return $statement->execute($params);
        };

        return $this->withSchemaRetry($operation);
    }

    public function deleteBook(int $id): bool
    {
        $operation = function () use ($id): bool {
            $statement = $this->pdo->prepare('DELETE FROM bookshop_books WHERE id = :id LIMIT 1');

            return $statement->execute(['id' => $id]);
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
                    is_active,
                    created_at,
                    updated_at
                FROM bookshop_categories
                ORDER BY name ASC
            SQL);

            return array_map([$this, 'normalizeCategory'], $statement->fetchAll() ?: []);
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
                    is_active,
                    created_at,
                    updated_at
                FROM bookshop_categories
                WHERE id = :id
                LIMIT 1
            SQL);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $category = $statement->fetch();

            return $category ? $this->normalizeCategory($category) : null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllGenresForAdmin(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    is_active,
                    created_at,
                    updated_at
                FROM bookshop_genres
                ORDER BY name ASC
            SQL);

            return array_map([$this, 'normalizeGenre'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findGenreByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    is_active,
                    created_at,
                    updated_at
                FROM bookshop_genres
                WHERE id = :id
                LIMIT 1
            SQL);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $genre = $statement->fetch();

            return $genre ? $this->normalizeGenre($genre) : null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllCollectionsForAdmin(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    is_active,
                    created_at,
                    updated_at
                FROM bookshop_collections
                ORDER BY name ASC
            SQL);

            return array_map([$this, 'normalizeCollection'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findCollectionByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    is_active,
                    created_at,
                    updated_at
                FROM bookshop_collections
                WHERE id = :id
                LIMIT 1
            SQL);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $collection = $statement->fetch();

            return $collection ? $this->normalizeCollection($collection) : null;
        };

        return $this->withSchemaRetry($operation);
    }

    public function createCategory(array $data): int
    {
        $operation = function () use ($data): int {
            $statement = $this->pdo->prepare(<<<SQL
                INSERT INTO bookshop_categories (
                    slug,
                    name,
                    description,
                    is_active
                ) VALUES (
                    :slug,
                    :name,
                    :description,
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
                UPDATE bookshop_categories
                SET
                    slug = :slug,
                    name = :name,
                    description = :description,
                    is_active = :is_active
                WHERE id = :id
                LIMIT 1
            SQL);
            $params = $this->buildCategoryWriteParams($data);
            $params['id'] = $id;

            $updated = $statement->execute($params);
            if ($updated) {
                $this->syncBookCategoryNamesFromCategoryId($id);
            }

            return $updated;
        };

        return $this->withSchemaRetry($operation);
    }

    public function setCategoryActive(int $id, bool $isActive): bool
    {
        $operation = function () use ($id, $isActive): bool {
            $statement = $this->pdo->prepare(<<<SQL
                UPDATE bookshop_categories
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

    public function createGenre(array $data): int
    {
        $operation = function () use ($data): int {
            $statement = $this->pdo->prepare(<<<SQL
                INSERT INTO bookshop_genres (
                    slug,
                    name,
                    description,
                    is_active
                ) VALUES (
                    :slug,
                    :name,
                    :description,
                    :is_active
                )
            SQL);
            $statement->execute($this->buildGenreWriteParams($data));

            return (int) $this->pdo->lastInsertId();
        };

        return $this->withSchemaRetry($operation);
    }

    public function updateGenre(int $id, array $data): bool
    {
        $operation = function () use ($id, $data): bool {
            $statement = $this->pdo->prepare(<<<SQL
                UPDATE bookshop_genres
                SET
                    slug = :slug,
                    name = :name,
                    description = :description,
                    is_active = :is_active
                WHERE id = :id
                LIMIT 1
            SQL);
            $params = $this->buildGenreWriteParams($data);
            $params['id'] = $id;

            $updated = $statement->execute($params);
            if ($updated) {
                $this->syncBookGenreNamesFromGenreId($id);
            }

            return $updated;
        };

        return $this->withSchemaRetry($operation);
    }

    public function setGenreActive(int $id, bool $isActive): bool
    {
        $operation = function () use ($id, $isActive): bool {
            $statement = $this->pdo->prepare(<<<SQL
                UPDATE bookshop_genres
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

    public function createCollection(array $data): int
    {
        $operation = function () use ($data): int {
            $statement = $this->pdo->prepare(<<<SQL
                INSERT INTO bookshop_collections (
                    slug,
                    name,
                    description,
                    is_active
                ) VALUES (
                    :slug,
                    :name,
                    :description,
                    :is_active
                )
            SQL);
            $statement->execute($this->buildCollectionWriteParams($data));

            return (int) $this->pdo->lastInsertId();
        };

        return $this->withSchemaRetry($operation);
    }

    public function updateCollection(int $id, array $data): bool
    {
        $operation = function () use ($id, $data): bool {
            $statement = $this->pdo->prepare(<<<SQL
                UPDATE bookshop_collections
                SET
                    slug = :slug,
                    name = :name,
                    description = :description,
                    is_active = :is_active
                WHERE id = :id
                LIMIT 1
            SQL);
            $params = $this->buildCollectionWriteParams($data);
            $params['id'] = $id;

            $updated = $statement->execute($params);
            if ($updated) {
                $this->syncBookCollectionNamesFromCollectionId($id);
            }

            return $updated;
        };

        return $this->withSchemaRetry($operation);
    }

    public function setCollectionActive(int $id, bool $isActive): bool
    {
        $operation = function () use ($id, $isActive): bool {
            $statement = $this->pdo->prepare(<<<SQL
                UPDATE bookshop_collections
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

    public function findAllSalesForAdmin(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    id,
                    sale_code,
                    sold_at,
                    customer_name,
                    customer_phone,
                    customer_email,
                    customer_cpf,
                    payment_method,
                    item_count,
                    subtotal_amount,
                    discount_amount,
                    total_amount,
                    received_amount,
                    change_amount,
                    notes,
                    status,
                    created_by_member_id,
                    created_by_name,
                    cancelled_at,
                    cancelled_by_member_id,
                    cancelled_by_name,
                    created_at,
                    updated_at
                FROM bookshop_sales
                ORDER BY sold_at DESC, id DESC
            SQL);

            return array_map([$this, 'normalizeSale'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllStockMovementsForAdmin(): array
    {
        $operation = function (): array {
            $statement = $this->pdo->query(<<<SQL
                SELECT
                    m.id,
                    m.book_id,
                    m.stock_lot_id,
                    m.stock_lot_code_snapshot,
                    m.sku_snapshot,
                    m.title_snapshot,
                    m.author_snapshot,
                    m.movement_type,
                    m.quantity,
                    m.stock_delta,
                    m.stock_before,
                    m.stock_after,
                    m.unit_cost,
                    m.unit_sale_price,
                    m.total_cost,
                    m.total_sale_value,
                    m.notes,
                    m.occurred_at,
                    m.created_by_member_id,
                    m.created_by_name,
                    m.created_at,
                    b.location_label,
                    b.status AS book_status,
                    b.stock_quantity AS current_stock_quantity
                FROM bookshop_stock_movements m
                LEFT JOIN bookshop_books b ON b.id = m.book_id
                ORDER BY m.occurred_at DESC, m.id DESC
            SQL);

            return array_map([$this, 'normalizeStockMovement'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findSaleByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $saleStatement = $this->pdo->prepare(<<<SQL
                SELECT
                    id,
                    sale_code,
                    sold_at,
                    customer_name,
                    customer_phone,
                    customer_email,
                    customer_cpf,
                    payment_method,
                    item_count,
                    subtotal_amount,
                    discount_amount,
                    total_amount,
                    received_amount,
                    change_amount,
                    notes,
                    status,
                    created_by_member_id,
                    created_by_name,
                    cancelled_at,
                    cancelled_by_member_id,
                    cancelled_by_name,
                    created_at,
                    updated_at
                FROM bookshop_sales
                WHERE id = :id
                LIMIT 1
            SQL);
            $saleStatement->bindValue(':id', $id, \PDO::PARAM_INT);
            $saleStatement->execute();

            $sale = $saleStatement->fetch();
            if (!$sale) {
                return null;
            }

            $itemStatement = $this->pdo->prepare(<<<SQL
                SELECT
                    si.id,
                    si.sale_id,
                    si.book_id,
                    si.stock_lot_id,
                    si.stock_lot_code_snapshot,
                    si.sku_snapshot,
                    si.title_snapshot,
                    si.author_snapshot,
                    si.unit_cost_snapshot,
                    si.unit_price,
                    si.quantity,
                    si.line_total,
                    b.status AS book_status,
                    b.stock_quantity AS book_stock_quantity
                FROM bookshop_sale_items si
                INNER JOIN bookshop_books b ON b.id = si.book_id
                WHERE si.sale_id = :sale_id
                ORDER BY si.id ASC
            SQL);
            $itemStatement->bindValue(':sale_id', $id, \PDO::PARAM_INT);
            $itemStatement->execute();

            $normalizedSale = $this->normalizeSale($sale);
            $normalizedSale['items'] = array_map(
                [$this, 'normalizeSaleItem'],
                $itemStatement->fetchAll() ?: []
            );

            return $normalizedSale;
        };

        return $this->withSchemaRetry($operation);
    }

    public function createSale(array $saleData, array $items): int
    {
        $operation = function () use ($saleData, $items): int {
            $this->pdo->beginTransaction();

            try {
                $resolvedItems = [];
                $booksById = [];
                $remainingBookStockById = [];
                $remainingLotsByBookId = [];
                $subtotal = 0.0;
                $itemCount = 0;

                foreach ($items as $item) {
                    $bookId = (int) ($item['book_id'] ?? 0);
                    $lotId = (int) ($item['lot_id'] ?? 0);
                    $quantity = (int) ($item['quantity'] ?? 0);

                    if ($bookId <= 0 || $quantity <= 0) {
                        continue;
                    }

                    if (!isset($booksById[$bookId])) {
                        $booksById[$bookId] = $this->findBookForSale($bookId);
                    }

                    $book = $booksById[$bookId];
                    if ($book === null) {
                        throw new \RuntimeException('Um dos livros selecionados não está mais disponível.');
                    }

                    if (!array_key_exists($bookId, $remainingBookStockById)) {
                        $remainingBookStockById[$bookId] = (int) ($book['stock_quantity'] ?? 0);
                    }

                    if (!array_key_exists($bookId, $remainingLotsByBookId)) {
                        $remainingLotsByBookId[$bookId] = $this->findAvailableSaleLotsForBook($bookId);
                    }

                    if ($remainingBookStockById[$bookId] < $quantity) {
                        throw new \RuntimeException(
                            'Estoque insuficiente para "' . (string) ($book['title'] ?? 'Livro') . '".'
                        );
                    }

                    $lotAllocations = $this->resolveSaleLotAllocations(
                        $bookId,
                        $quantity,
                        $lotId,
                        $remainingLotsByBookId[$bookId]
                    );

                    if ($lotAllocations === []) {
                        throw new \RuntimeException(
                            $lotId > 0
                                ? 'Selecione um lote válido para "' . (string) ($book['title'] ?? 'Livro') . '".'
                                : 'Os lotes disponíveis de "'
                                    . (string) ($book['title'] ?? 'Livro')
                                    . '" não comportam a quantidade desta linha.'
                        );
                    }

                    $unitPrice = $this->normalizeDecimal(
                        $item['unit_price']
                        ?? $book['sale_price']
                        ?? $lotAllocations[0]['unit_sale_price']
                        ?? '0.00'
                    );

                    foreach ($lotAllocations as $allocation) {
                        $allocatedQuantity = max(0, (int) ($allocation['allocated_quantity'] ?? 0));
                        if ($allocatedQuantity <= 0) {
                            continue;
                        }

                        $lineTotal = round(((float) $unitPrice) * $allocatedQuantity, 2);
                        $unitCostSnapshot = isset($allocation['unit_cost']) && $allocation['unit_cost'] !== null
                            ? (float) $allocation['unit_cost']
                            : null;

                        $resolvedItems[] = [
                            'book_id' => $bookId,
                            'stock_lot_id' => (int) ($allocation['id'] ?? 0),
                            'stock_lot_code_snapshot' => $this->formatStockLotCode((int) ($allocation['id'] ?? 0)),
                            'quantity' => $allocatedQuantity,
                            'unit_cost_snapshot' => $unitCostSnapshot !== null
                                ? number_format($unitCostSnapshot, 2, '.', '')
                                : null,
                            'unit_price' => $unitPrice,
                            'line_total' => number_format($lineTotal, 2, '.', ''),
                            'sku_snapshot' => (string) ($book['sku'] ?? ''),
                            'title_snapshot' => (string) ($book['title'] ?? ''),
                            'author_snapshot' => $this->nullableText($book['author_name'] ?? null),
                        ];

                        $subtotal += $lineTotal;
                        $itemCount += $allocatedQuantity;
                    }

                    $remainingBookStockById[$bookId] -= $quantity;
                }

                if ($resolvedItems === []) {
                    throw new \RuntimeException('Selecione ao menos um item válido para concluir a venda.');
                }

                $discountAmount = (float) $this->normalizeDecimal($saleData['discount_amount'] ?? '0.00');
                if ($discountAmount < 0) {
                    $discountAmount = 0.0;
                }

                if ($discountAmount > $subtotal) {
                    throw new \RuntimeException('O desconto não pode ser maior do que o subtotal da venda.');
                }

                $totalAmount = $subtotal - $discountAmount;
                $paymentMethod = (string) ($saleData['payment_method'] ?? 'other');
                $receivedAmount = null;
                $changeAmount = null;

                if ($paymentMethod === 'cash') {
                    $receivedAmount = (float) $this->normalizeDecimal($saleData['received_amount'] ?? '0.00');

                    if ($receivedAmount < $totalAmount) {
                        throw new \RuntimeException('O valor recebido em dinheiro não pode ser menor do que o total.');
                    }

                    $changeAmount = max(0, $receivedAmount - $totalAmount);
                }

                $saleCode = $this->generateSaleCode();

                $saleStatement = $this->pdo->prepare(<<<SQL
                    INSERT INTO bookshop_sales (
                        sale_code,
                        sold_at,
                        customer_name,
                        customer_phone,
                        customer_email,
                        customer_cpf,
                        payment_method,
                        item_count,
                        subtotal_amount,
                        discount_amount,
                        total_amount,
                        received_amount,
                        change_amount,
                        notes,
                        status,
                        created_by_member_id,
                        created_by_name
                    ) VALUES (
                        :sale_code,
                        :sold_at,
                        :customer_name,
                        :customer_phone,
                        :customer_email,
                        :customer_cpf,
                        :payment_method,
                        :item_count,
                        :subtotal_amount,
                        :discount_amount,
                        :total_amount,
                        :received_amount,
                        :change_amount,
                        :notes,
                        'completed',
                        :created_by_member_id,
                        :created_by_name
                    )
                SQL);
                $saleStatement->execute([
                    'sale_code' => $saleCode,
                    'sold_at' => (string) ($saleData['sold_at'] ?? gmdate('Y-m-d H:i:s')),
                    'customer_name' => $this->nullableText($saleData['customer_name'] ?? null),
                    'customer_phone' => $this->nullableText($saleData['customer_phone'] ?? null),
                    'customer_email' => $this->nullableText($saleData['customer_email'] ?? null),
                    'customer_cpf' => $this->nullableText($saleData['customer_cpf'] ?? null),
                    'payment_method' => $paymentMethod,
                    'item_count' => $itemCount,
                    'subtotal_amount' => number_format($subtotal, 2, '.', ''),
                    'discount_amount' => number_format($discountAmount, 2, '.', ''),
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                    'received_amount' => $receivedAmount !== null
                        ? number_format($receivedAmount, 2, '.', '')
                        : null,
                    'change_amount' => $changeAmount !== null
                        ? number_format($changeAmount, 2, '.', '')
                        : null,
                    'notes' => $this->nullableText($saleData['notes'] ?? null),
                    'created_by_member_id' => (int) ($saleData['created_by_member_id'] ?? 0) > 0
                        ? (int) $saleData['created_by_member_id']
                        : null,
                    'created_by_name' => $this->nullableText($saleData['created_by_name'] ?? null),
                ]);

                $saleId = (int) $this->pdo->lastInsertId();

                $itemStatement = $this->pdo->prepare(<<<SQL
                    INSERT INTO bookshop_sale_items (
                        sale_id,
                        book_id,
                        stock_lot_id,
                        stock_lot_code_snapshot,
                        sku_snapshot,
                        title_snapshot,
                        author_snapshot,
                        unit_cost_snapshot,
                        unit_price,
                        quantity,
                        line_total
                    ) VALUES (
                        :sale_id,
                        :book_id,
                        :stock_lot_id,
                        :stock_lot_code_snapshot,
                        :sku_snapshot,
                        :title_snapshot,
                        :author_snapshot,
                        :unit_cost_snapshot,
                        :unit_price,
                        :quantity,
                        :line_total
                    )
                SQL);

                $stockStatement = $this->pdo->prepare(<<<SQL
                    UPDATE bookshop_books
                    SET stock_quantity = stock_quantity - :quantity
                    WHERE id = :book_id
                    LIMIT 1
                SQL);

                $lotStockStatement = $this->pdo->prepare(<<<SQL
                    UPDATE bookshop_stock_lots
                    SET quantity_available = quantity_available - :quantity
                    WHERE id = :lot_id
                    LIMIT 1
                SQL);

                foreach ($resolvedItems as $resolvedItem) {
                    $itemStatement->execute([
                        'sale_id' => $saleId,
                        'book_id' => $resolvedItem['book_id'],
                        'stock_lot_id' => $resolvedItem['stock_lot_id'],
                        'stock_lot_code_snapshot' => $resolvedItem['stock_lot_code_snapshot'],
                        'sku_snapshot' => $resolvedItem['sku_snapshot'],
                        'title_snapshot' => $resolvedItem['title_snapshot'],
                        'author_snapshot' => $resolvedItem['author_snapshot'],
                        'unit_cost_snapshot' => $resolvedItem['unit_cost_snapshot'],
                        'unit_price' => $resolvedItem['unit_price'],
                        'quantity' => $resolvedItem['quantity'],
                        'line_total' => $resolvedItem['line_total'],
                    ]);

                    $stockStatement->execute([
                        'book_id' => $resolvedItem['book_id'],
                        'quantity' => $resolvedItem['quantity'],
                    ]);

                    $lotStockStatement->execute([
                        'lot_id' => $resolvedItem['stock_lot_id'],
                        'quantity' => $resolvedItem['quantity'],
                    ]);
                }

                $this->pdo->commit();

                return $saleId;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $exception;
            }
        };

        return $this->withSchemaRetry($operation);
    }

    public function createStockMovement(array $data): int
    {
        $operation = function () use ($data): int {
            $this->pdo->beginTransaction();

            try {
                $bookId = (int) ($data['book_id'] ?? 0);
                $movementType = trim((string) ($data['movement_type'] ?? ''));
                $quantity = max(0, (int) ($data['quantity'] ?? 0));
                $stockLotId = max(0, (int) ($data['stock_lot_id'] ?? 0));

                if ($bookId <= 0 || $quantity <= 0) {
                    throw new \RuntimeException('Informe um livro e uma quantidade válida para a movimentação.');
                }

                $book = $this->findBookForStockMovement($bookId);
                if ($book === null) {
                    throw new \RuntimeException('O livro selecionado não foi encontrado no acervo.');
                }

                $stockDelta = match ($movementType) {
                    'entry', 'donation', 'adjustment_add' => $quantity,
                    'adjustment_remove', 'loss' => -$quantity,
                    default => null,
                };

                if ($stockDelta === null) {
                    throw new \RuntimeException('Selecione um tipo de movimentação válido.');
                }

                $stockBefore = (int) ($book['stock_quantity'] ?? 0);
                $stockAfter = $stockBefore + $stockDelta;

                if ($stockAfter < 0) {
                    throw new \RuntimeException(
                        'O saldo atual de "' . (string) ($book['title'] ?? 'Livro') . '" não comporta essa saída.'
                    );
                }

                $availableLots = [];
                $selectedLot = null;
                $stockLotCodeSnapshot = null;
                $requiresLotSelection = in_array($movementType, ['adjustment_add', 'adjustment_remove', 'loss'], true);

                if ($requiresLotSelection) {
                    $availableLots = $this->findAvailableStockLotsByBookIds([$bookId])[$bookId] ?? [];

                    if ($stockLotId > 0) {
                        $selectedLot = $this->resolveStockMovementLot(
                            $bookId,
                            $stockLotId,
                            $movementType === 'adjustment_add'
                        );
                    } elseif ($availableLots !== []) {
                        if (count($availableLots) === 1) {
                            $selectedLot = $this->resolveStockMovementLot(
                                $bookId,
                                (int) ($availableLots[0]['id'] ?? 0),
                                $movementType === 'adjustment_add'
                            );
                        } else {
                            throw new \RuntimeException(
                                'Selecione o lote do ajuste para "' . (string) ($book['title'] ?? 'Livro') . '".'
                            );
                        }
                    } elseif (in_array($movementType, ['adjustment_remove', 'loss'], true)) {
                        throw new \RuntimeException(
                            'O item "' . (string) ($book['title'] ?? 'Livro') . '" não possui lote disponível para este ajuste.'
                        );
                    }

                    if ($stockLotId > 0 && $selectedLot === null) {
                        throw new \RuntimeException(
                            'Selecione um lote válido para "' . (string) ($book['title'] ?? 'Livro') . '".'
                        );
                    }

                    if (
                        $selectedLot !== null
                        && in_array($movementType, ['adjustment_remove', 'loss'], true)
                        && (int) ($selectedLot['quantity_available'] ?? 0) < $quantity
                    ) {
                        throw new \RuntimeException(
                            'O lote '
                            . $this->formatStockLotCode((int) ($selectedLot['id'] ?? 0))
                            . ' de "'
                            . (string) ($book['title'] ?? 'Livro')
                            . '" tem apenas '
                            . (int) ($selectedLot['quantity_available'] ?? 0)
                            . ' unidade(s) disponível(is).'
                        );
                    }

                    if ($selectedLot !== null) {
                        $stockLotId = (int) ($selectedLot['id'] ?? 0);
                        $stockLotCodeSnapshot = $this->formatStockLotCode($stockLotId);
                    }
                }

                $unitCost = null;
                $unitCostRaw = trim((string) ($data['unit_cost'] ?? ''));
                if ($unitCostRaw !== '') {
                    $unitCost = (float) $this->normalizeDecimal($unitCostRaw);
                }

                $unitSalePrice = null;
                $unitSalePriceRaw = trim((string) ($data['sale_price'] ?? ''));
                if ($unitSalePriceRaw !== '') {
                    $unitSalePrice = (float) $this->normalizeDecimal($unitSalePriceRaw);
                }

                if ($unitCost === null && $selectedLot !== null && isset($selectedLot['unit_cost']) && $selectedLot['unit_cost'] !== null) {
                    $unitCost = (float) $selectedLot['unit_cost'];
                }

                if ($unitSalePrice === null && $selectedLot !== null && isset($selectedLot['unit_sale_price']) && $selectedLot['unit_sale_price'] !== null) {
                    $unitSalePrice = (float) $selectedLot['unit_sale_price'];
                }

                $totalCost = $unitCost !== null
                    ? round($unitCost * $quantity, 2)
                    : null;
                $totalSaleValue = $unitSalePrice !== null
                    ? round($unitSalePrice * $quantity, 2)
                    : null;

                $insertStatement = $this->pdo->prepare(<<<SQL
                    INSERT INTO bookshop_stock_movements (
                        book_id,
                        stock_lot_id,
                        stock_lot_code_snapshot,
                        sku_snapshot,
                        title_snapshot,
                        author_snapshot,
                        movement_type,
                        quantity,
                        stock_delta,
                        stock_before,
                        stock_after,
                        unit_cost,
                        unit_sale_price,
                        total_cost,
                        total_sale_value,
                        notes,
                        occurred_at,
                        created_by_member_id,
                        created_by_name
                    ) VALUES (
                        :book_id,
                        :stock_lot_id,
                        :stock_lot_code_snapshot,
                        :sku_snapshot,
                        :title_snapshot,
                        :author_snapshot,
                        :movement_type,
                        :quantity,
                        :stock_delta,
                        :stock_before,
                        :stock_after,
                        :unit_cost,
                        :unit_sale_price,
                        :total_cost,
                        :total_sale_value,
                        :notes,
                        :occurred_at,
                        :created_by_member_id,
                        :created_by_name
                    )
                SQL);
                $insertStatement->execute([
                    'book_id' => $bookId,
                    'stock_lot_id' => $stockLotId > 0 ? $stockLotId : null,
                    'stock_lot_code_snapshot' => $this->nullableText($stockLotCodeSnapshot),
                    'sku_snapshot' => (string) ($book['sku'] ?? ''),
                    'title_snapshot' => (string) ($book['title'] ?? ''),
                    'author_snapshot' => $this->nullableText($book['author_name'] ?? null),
                    'movement_type' => $movementType,
                    'quantity' => $quantity,
                    'stock_delta' => $stockDelta,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'unit_cost' => $unitCost !== null ? number_format($unitCost, 2, '.', '') : null,
                    'unit_sale_price' => $unitSalePrice !== null ? number_format($unitSalePrice, 2, '.', '') : null,
                    'total_cost' => $totalCost !== null ? number_format($totalCost, 2, '.', '') : null,
                    'total_sale_value' => $totalSaleValue !== null ? number_format($totalSaleValue, 2, '.', '') : null,
                    'notes' => $this->nullableText($data['notes'] ?? null),
                    'occurred_at' => (string) ($data['occurred_at'] ?? gmdate('Y-m-d H:i:s')),
                    'created_by_member_id' => (int) ($data['created_by_member_id'] ?? 0) > 0
                        ? (int) $data['created_by_member_id']
                        : null,
                    'created_by_name' => $this->nullableText($data['created_by_name'] ?? null),
                ]);
                $movementId = (int) $this->pdo->lastInsertId();

                $updateParams = [
                    'id' => $bookId,
                    'stock_quantity' => $stockAfter,
                ];

                $setClauses = ['stock_quantity = :stock_quantity'];
                if ($stockDelta > 0 && $unitCost !== null && $unitCost > 0) {
                    $setClauses[] = 'cost_price = :cost_price';
                    $updateParams['cost_price'] = number_format($unitCost, 2, '.', '');
                }

                if ($stockDelta > 0 && $unitSalePrice !== null && $unitSalePrice > 0) {
                    $setClauses[] = 'sale_price = :sale_price';
                    $updateParams['sale_price'] = number_format($unitSalePrice, 2, '.', '');
                }

                $updateBookStatement = $this->pdo->prepare(sprintf(
                    'UPDATE bookshop_books SET %s WHERE id = :id LIMIT 1',
                    implode(', ', $setClauses)
                ));
                $updateBookStatement->execute($updateParams);

                if ($movementType === 'entry' || $movementType === 'donation') {
                    $resolvedLotSalePrice = $unitSalePrice !== null
                        ? $unitSalePrice
                        : (float) ($book['sale_price'] ?? 0);
                    $resolvedLotCost = $movementType === 'donation'
                        ? null
                        : ($unitCost !== null
                            ? $unitCost
                            : (isset($book['cost_price']) && $book['cost_price'] !== null
                                ? (float) $book['cost_price']
                                : null));

                    $createdLotId = $this->createStockLot([
                        'book_id' => $bookId,
                        'source_movement_id' => $movementId,
                        'quantity_received' => $quantity,
                        'quantity_available' => $quantity,
                        'unit_cost' => $resolvedLotCost,
                        'unit_sale_price' => $resolvedLotSalePrice,
                        'occurred_at' => (string) ($data['occurred_at'] ?? gmdate('Y-m-d H:i:s')),
                        'notes' => $data['notes'] ?? null,
                    ]);
                    $this->attachStockLotSnapshotToMovement($movementId, $createdLotId);
                } elseif ($movementType === 'adjustment_add') {
                    if ($selectedLot !== null) {
                        $this->incrementStockLotQuantity(
                            (int) ($selectedLot['id'] ?? 0),
                            $quantity,
                            $unitCost,
                            $unitSalePrice
                        );
                    } else {
                        $resolvedLotSalePrice = $unitSalePrice !== null
                            ? $unitSalePrice
                            : (float) ($book['sale_price'] ?? 0);
                        $resolvedLotCost = $unitCost !== null
                            ? $unitCost
                            : (isset($book['cost_price']) && $book['cost_price'] !== null
                                ? (float) $book['cost_price']
                                : null);

                        $createdLotId = $this->createStockLot([
                            'book_id' => $bookId,
                            'source_movement_id' => $movementId,
                            'quantity_received' => $quantity,
                            'quantity_available' => $quantity,
                            'unit_cost' => $resolvedLotCost,
                            'unit_sale_price' => $resolvedLotSalePrice,
                            'occurred_at' => (string) ($data['occurred_at'] ?? gmdate('Y-m-d H:i:s')),
                            'notes' => $data['notes'] ?? null,
                        ]);
                        $this->attachStockLotSnapshotToMovement($movementId, $createdLotId);
                    }
                } elseif (in_array($movementType, ['adjustment_remove', 'loss'], true)) {
                    if ($selectedLot === null) {
                        throw new \RuntimeException(
                            'Selecione um lote válido para "' . (string) ($book['title'] ?? 'Livro') . '".'
                        );
                    }

                    $this->decrementStockLotQuantity((int) ($selectedLot['id'] ?? 0), $quantity);
                }

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

    public function cancelSale(int $id, ?int $memberId = null, ?string $memberName = null): bool
    {
        $operation = function () use ($id, $memberId, $memberName): bool {
            $this->pdo->beginTransaction();

            try {
                $saleStatement = $this->pdo->prepare(<<<SQL
                    SELECT id, status
                    FROM bookshop_sales
                    WHERE id = :id
                    LIMIT 1
                    FOR UPDATE
                SQL);
                $saleStatement->bindValue(':id', $id, \PDO::PARAM_INT);
                $saleStatement->execute();

                $sale = $saleStatement->fetch();
                if (!$sale || (string) ($sale['status'] ?? '') !== 'completed') {
                    $this->pdo->rollBack();

                    return false;
                }

                $itemsStatement = $this->pdo->prepare(<<<SQL
                    SELECT book_id, stock_lot_id, quantity
                    FROM bookshop_sale_items
                    WHERE sale_id = :sale_id
                SQL);
                $itemsStatement->bindValue(':sale_id', $id, \PDO::PARAM_INT);
                $itemsStatement->execute();

                $restoreStatement = $this->pdo->prepare(<<<SQL
                    UPDATE bookshop_books
                    SET stock_quantity = stock_quantity + :quantity
                    WHERE id = :book_id
                    LIMIT 1
                SQL);

                $restoreLotStatement = $this->pdo->prepare(<<<SQL
                    UPDATE bookshop_stock_lots
                    SET quantity_available = quantity_available + :quantity
                    WHERE id = :lot_id
                    LIMIT 1
                SQL);

                foreach ($itemsStatement->fetchAll() ?: [] as $item) {
                    $restoreStatement->execute([
                        'book_id' => (int) ($item['book_id'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 0),
                    ]);

                    if ((int) ($item['stock_lot_id'] ?? 0) > 0) {
                        $restoreLotStatement->execute([
                            'lot_id' => (int) $item['stock_lot_id'],
                            'quantity' => (int) ($item['quantity'] ?? 0),
                        ]);
                    }
                }

                $cancelStatement = $this->pdo->prepare(<<<SQL
                    UPDATE bookshop_sales
                    SET
                        status = 'cancelled',
                        cancelled_at = NOW(),
                        cancelled_by_member_id = :cancelled_by_member_id,
                        cancelled_by_name = :cancelled_by_name
                    WHERE id = :id
                    LIMIT 1
                SQL);
                $cancelStatement->execute([
                    'id' => $id,
                    'cancelled_by_member_id' => $memberId !== null && $memberId > 0 ? $memberId : null,
                    'cancelled_by_name' => $this->nullableText($memberName),
                ]);

                $this->pdo->commit();

                return true;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $exception;
            }
        };

        return $this->withSchemaRetry($operation);
    }

    private function buildBookSelect(): string
    {
        return <<<SQL
            SELECT
                b.id,
                b.sku,
                b.slug,
                b.category_id,
                COALESCE(c.slug, '') AS category_slug,
                COALESCE(c.name, b.category_name) AS category_name,
                b.genre_id,
                COALESCE(g.slug, '') AS genre_slug,
                COALESCE(g.name, b.genre_name) AS genre_name,
                b.collection_id,
                COALESCE(co.slug, '') AS collection_slug,
                COALESCE(co.name, b.collection_name) AS collection_name,
                b.title,
                b.subtitle,
                b.author_name,
                b.publisher_name,
                b.isbn,
                b.barcode,
                b.edition_label,
                b.volume_number,
                b.volume_label,
                b.publication_year,
                b.page_count,
                b.language,
                b.description,
                b.cover_image_path,
                b.cover_image_mime_type,
                b.cover_image_size_bytes,
                b.cost_price,
                b.sale_price,
                b.stock_quantity,
                b.stock_minimum,
                b.status,
                b.location_label,
                b.created_at,
                b.updated_at
            FROM bookshop_books b
            LEFT JOIN bookshop_categories c ON c.id = b.category_id
            LEFT JOIN bookshop_genres g ON g.id = b.genre_id
            LEFT JOIN bookshop_collections co ON co.id = b.collection_id
        SQL;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildBookWriteParams(array $data): array
    {
        $resolvedCategory = $this->resolveCategoryReference($data);
        $resolvedGenre = $this->resolveGenreReference($data);
        $resolvedCollection = $this->resolveCollectionReference($data);

        return [
            'sku' => trim((string) ($data['sku'] ?? '')),
            'slug' => trim((string) ($data['slug'] ?? '')),
            'category_id' => $resolvedCategory['id'],
            'category_name' => $resolvedCategory['name'],
            'genre_id' => $resolvedGenre['id'],
            'genre_name' => $resolvedGenre['name'],
            'collection_id' => $resolvedCollection['id'],
            'collection_name' => $resolvedCollection['name'],
            'title' => BookshopTextNormalizer::normalizeTitle((string) ($data['title'] ?? '')),
            'subtitle' => $this->nullableText($data['subtitle'] ?? null),
            'author_name' => BookshopTextNormalizer::normalizeAuthorName((string) ($data['author_name'] ?? '')),
            'publisher_name' => $this->nullableText($data['publisher_name'] ?? null),
            'isbn' => $this->nullableText($data['isbn'] ?? null),
            'barcode' => $this->nullableText($data['barcode'] ?? null),
            'edition_label' => $this->nullableText($data['edition_label'] ?? null),
            'volume_number' => $this->nullableInteger($data['volume_number'] ?? null),
            'volume_label' => $this->nullableText($data['volume_label'] ?? null),
            'publication_year' => $this->nullableInteger($data['publication_year'] ?? null),
            'page_count' => $this->nullableInteger($data['page_count'] ?? null),
            'language' => $this->nullableText($data['language'] ?? null),
            'description' => $this->nullableText($data['description'] ?? null),
            'cover_image_path' => $this->nullableText($data['cover_image_path'] ?? null),
            'cover_image_mime_type' => $this->nullableText($data['cover_image_mime_type'] ?? null),
            'cover_image_size_bytes' => $this->nullableInteger($data['cover_image_size_bytes'] ?? null),
            'cost_price' => $this->normalizeDecimal($data['cost_price'] ?? '0.00'),
            'sale_price' => $this->normalizeDecimal($data['sale_price'] ?? '0.00'),
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'stock_minimum' => max(0, (int) ($data['stock_minimum'] ?? 0)),
            'status' => trim((string) ($data['status'] ?? 'active')),
            'location_label' => $this->nullableText($data['location_label'] ?? null),
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
            'is_active' => ((int) ($data['is_active'] ?? 0)) === 1 ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildGenreWriteParams(array $data): array
    {
        return [
            'slug' => trim((string) ($data['slug'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => $this->nullableText($data['description'] ?? null),
            'is_active' => ((int) ($data['is_active'] ?? 0)) === 1 ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildCollectionWriteParams(array $data): array
    {
        return [
            'slug' => trim((string) ($data['slug'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => $this->nullableText($data['description'] ?? null),
            'is_active' => ((int) ($data['is_active'] ?? 0)) === 1 ? 1 : 0,
        ];
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

        return (int) $value;
    }

    private function normalizeDecimal(mixed $value): string
    {
        $numeric = is_numeric((string) $value) ? (float) $value : 0.0;

        return number_format($numeric, 2, '.', '');
    }

    /**
     * @param array<string, mixed> $book
     * @return array<string, mixed>
     */
    private function normalizeBook(array $book): array
    {
        $coverImagePath = ltrim((string) ($book['cover_image_path'] ?? ''), '/');
        $coverImageSizeBytes = isset($book['cover_image_size_bytes']) && $book['cover_image_size_bytes'] !== null
            ? (int) $book['cover_image_size_bytes']
            : null;
        $costPrice = (float) ($book['cost_price'] ?? 0);
        $salePrice = (float) ($book['sale_price'] ?? 0);
        $stockQuantity = (int) ($book['stock_quantity'] ?? 0);
        $stockMinimum = (int) ($book['stock_minimum'] ?? 0);
        $stockState = 'ok';

        if ($stockQuantity <= 0) {
            $stockState = 'out';
        } elseif ($stockQuantity <= $stockMinimum) {
            $stockState = 'low';
        }

        return array_merge($book, [
            'volume_number' => isset($book['volume_number']) && $book['volume_number'] !== null
                ? (int) $book['volume_number']
                : null,
            'page_count' => isset($book['page_count']) && $book['page_count'] !== null
                ? (int) $book['page_count']
                : null,
            'cover_image_size_bytes' => $coverImageSizeBytes,
            'cover_image_url' => $coverImagePath !== '' ? '/' . $coverImagePath : '',
            'cost_price' => $costPrice,
            'sale_price' => $salePrice,
            'stock_quantity' => $stockQuantity,
            'stock_minimum' => $stockMinimum,
            'cost_price_label' => $this->formatMoney($costPrice),
            'sale_price_label' => $this->formatMoney($salePrice),
            'status_label' => $this->formatBookStatusLabel((string) ($book['status'] ?? 'active')),
            'stock_state' => $stockState,
            'stock_state_label' => $this->formatStockStateLabel($stockState),
            'inventory_value' => $costPrice * $stockQuantity,
            'inventory_value_label' => $this->formatMoney($costPrice * $stockQuantity),
            'potential_revenue_value' => $salePrice * $stockQuantity,
            'potential_revenue_label' => $this->formatMoney($salePrice * $stockQuantity),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $books
     * @return array<int, array<string, mixed>>
     */
    private function attachAvailableStockLotsToBooks(array $books): array
    {
        if ($books === []) {
            return [];
        }

        $bookIds = array_values(array_filter(array_map(
            static fn (array $book): int => (int) ($book['id'] ?? 0),
            $books
        ), static fn (int $id): bool => $id > 0));

        if ($bookIds === []) {
            return $books;
        }

        $lotsByBookId = $this->findAvailableStockLotsByBookIds($bookIds);

        return array_map(function (array $book) use ($lotsByBookId): array {
            $bookId = (int) ($book['id'] ?? 0);
            $lots = $lotsByBookId[$bookId] ?? [];

            return array_merge($book, [
                'stock_lots' => $lots,
                'stock_lot_count' => count($lots),
                'stock_lot_summary' => $this->buildStockLotSummary($lots),
            ]);
        }, $books);
    }

    /**
     * @param array<int, int> $bookIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function findAvailableStockLotsByBookIds(array $bookIds): array
    {
        $bookIds = array_values(array_unique(array_filter($bookIds, static fn (int $id): bool => $id > 0)));
        if ($bookIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($bookIds as $index => $bookId) {
            $placeholder = ':book_id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $bookId;
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT id, book_id, source_movement_id, quantity_received, quantity_available, unit_cost, unit_sale_price, occurred_at, notes, created_at
             FROM bookshop_stock_lots
             WHERE quantity_available > 0 AND book_id IN (%s)
             ORDER BY occurred_at ASC, id ASC',
            implode(', ', $placeholders)
        ));

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        $lotsByBookId = [];
        foreach ($statement->fetchAll() ?: [] as $lot) {
            $normalizedLot = $this->normalizeStockLot($lot);
            $bookId = (int) ($normalizedLot['book_id'] ?? 0);
            $lotsByBookId[$bookId] ??= [];
            $lotsByBookId[$bookId][] = $normalizedLot;
        }

        return $lotsByBookId;
    }

    /**
     * @param array<string, mixed> $lot
     * @return array<string, mixed>
     */
    private function normalizeStockLot(array $lot): array
    {
        $lotId = (int) ($lot['id'] ?? 0);
        $quantityAvailable = (int) ($lot['quantity_available'] ?? 0);
        $unitCost = isset($lot['unit_cost']) && $lot['unit_cost'] !== null
            ? (float) $lot['unit_cost']
            : null;
        $unitSalePrice = (float) ($lot['unit_sale_price'] ?? 0);
        $labelFragments = [
            $this->formatStockLotCode($lotId),
            $quantityAvailable . ' un',
            'venda ' . $this->formatMoney($unitSalePrice),
        ];

        if ($unitCost !== null) {
            $labelFragments[] = 'custo ' . $this->formatMoney($unitCost);
        }

        return array_merge($lot, [
            'id' => $lotId,
            'book_id' => (int) ($lot['book_id'] ?? 0),
            'source_movement_id' => isset($lot['source_movement_id']) && $lot['source_movement_id'] !== null
                ? (int) $lot['source_movement_id']
                : null,
            'quantity_received' => (int) ($lot['quantity_received'] ?? 0),
            'quantity_available' => $quantityAvailable,
            'unit_cost' => $unitCost,
            'unit_sale_price' => $unitSalePrice,
            'lot_code' => $this->formatStockLotCode($lotId),
            'unit_cost_label' => $unitCost !== null ? $this->formatMoney($unitCost) : '-',
            'unit_sale_price_label' => $this->formatMoney($unitSalePrice),
            'occurred_at_label' => $this->formatDateTime($lot['occurred_at'] ?? null),
            'label' => implode(' · ', $labelFragments),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $lots
     */
    private function buildStockLotSummary(array $lots): string
    {
        if ($lots === []) {
            return '';
        }

        $summary = array_map(static function (array $lot): string {
            $fragments = [
                (string) ($lot['lot_code'] ?? ''),
                (string) ($lot['quantity_available'] ?? 0) . ' un',
                'venda ' . (string) ($lot['unit_sale_price_label'] ?? 'R$ 0,00'),
            ];

            if (($lot['unit_cost_label'] ?? '-') !== '-') {
                $fragments[] = 'custo ' . (string) $lot['unit_cost_label'];
            }

            return implode(' · ', $fragments);
        }, array_slice($lots, 0, 3));

        if (count($lots) > 3) {
            $summary[] = '+' . (count($lots) - 3) . ' lote(s)';
        }

        return implode(' | ', $summary);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createStockLot(array $data): int
    {
        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO bookshop_stock_lots (
                book_id,
                source_movement_id,
                quantity_received,
                quantity_available,
                unit_cost,
                unit_sale_price,
                occurred_at,
                notes
            ) VALUES (
                :book_id,
                :source_movement_id,
                :quantity_received,
                :quantity_available,
                :unit_cost,
                :unit_sale_price,
                :occurred_at,
                :notes
            )
        SQL);

        $statement->execute([
            'book_id' => (int) ($data['book_id'] ?? 0),
            'source_movement_id' => isset($data['source_movement_id']) && (int) $data['source_movement_id'] > 0
                ? (int) $data['source_movement_id']
                : null,
            'quantity_received' => max(0, (int) ($data['quantity_received'] ?? 0)),
            'quantity_available' => max(0, (int) ($data['quantity_available'] ?? 0)),
            'unit_cost' => isset($data['unit_cost']) && $data['unit_cost'] !== null
                ? number_format((float) $data['unit_cost'], 2, '.', '')
                : null,
            'unit_sale_price' => number_format((float) ($data['unit_sale_price'] ?? 0), 2, '.', ''),
            'occurred_at' => (string) ($data['occurred_at'] ?? gmdate('Y-m-d H:i:s')),
            'notes' => $this->nullableText($data['notes'] ?? null),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function incrementStockLotQuantity(
        int $lotId,
        int $quantity,
        ?float $unitCost,
        ?float $unitSalePrice
    ): void {
        $setClauses = [
            'quantity_received = quantity_received + :quantity',
            'quantity_available = quantity_available + :quantity',
        ];
        $params = [
            'lot_id' => $lotId,
            'quantity' => $quantity,
        ];

        if ($unitCost !== null) {
            $setClauses[] = 'unit_cost = :unit_cost';
            $params['unit_cost'] = number_format($unitCost, 2, '.', '');
        }

        if ($unitSalePrice !== null && $unitSalePrice > 0) {
            $setClauses[] = 'unit_sale_price = :unit_sale_price';
            $params['unit_sale_price'] = number_format($unitSalePrice, 2, '.', '');
        }

        $statement = $this->pdo->prepare(sprintf(
            'UPDATE bookshop_stock_lots SET %s WHERE id = :lot_id LIMIT 1',
            implode(', ', $setClauses)
        ));
        $statement->execute($params);
    }

    private function decrementStockLotQuantity(int $lotId, int $quantity): void
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE bookshop_stock_lots
            SET quantity_available = quantity_available - :quantity
            WHERE id = :lot_id
            LIMIT 1
        SQL);
        $statement->execute([
            'lot_id' => $lotId,
            'quantity' => $quantity,
        ]);
    }

    private function attachStockLotSnapshotToMovement(int $movementId, int $stockLotId): void
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE bookshop_stock_movements
            SET stock_lot_id = :stock_lot_id,
                stock_lot_code_snapshot = :stock_lot_code_snapshot
            WHERE id = :id
            LIMIT 1
        SQL);
        $statement->execute([
            'id' => $movementId,
            'stock_lot_id' => $stockLotId,
            'stock_lot_code_snapshot' => $this->formatStockLotCode($stockLotId),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findAvailableSaleLotsForBook(int $bookId): array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT
                id,
                book_id,
                source_movement_id,
                quantity_received,
                quantity_available,
                unit_cost,
                unit_sale_price,
                occurred_at,
                notes,
                created_at
            FROM bookshop_stock_lots
            WHERE book_id = :book_id AND quantity_available > 0
            ORDER BY occurred_at ASC, id ASC
            FOR UPDATE
        SQL);
        $statement->execute([
            'book_id' => $bookId,
        ]);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $availableLots
     * @return array<int, array<string, mixed>>
     */
    private function resolveSaleLotAllocations(
        int $bookId,
        int $quantity,
        int $lotId,
        array &$availableLots
    ): array {
        if ($quantity <= 0) {
            return [];
        }

        $originalLots = $availableLots;

        if ($lotId > 0) {
            foreach ($availableLots as $index => $lot) {
                if ((int) ($lot['id'] ?? 0) !== $lotId || (int) ($lot['book_id'] ?? 0) !== $bookId) {
                    continue;
                }

                if ((int) ($lot['quantity_available'] ?? 0) < $quantity) {
                    $availableLots = $originalLots;

                    return [];
                }

                $availableLots[$index]['quantity_available'] = (int) ($lot['quantity_available'] ?? 0) - $quantity;

                return [array_merge($lot, [
                    'allocated_quantity' => $quantity,
                ])];
            }

            return [];
        }

        $remainingQuantity = $quantity;
        $allocations = [];

        foreach ($availableLots as $index => $lot) {
            if ((int) ($lot['book_id'] ?? 0) !== $bookId) {
                continue;
            }

            $quantityAvailable = max(0, (int) ($lot['quantity_available'] ?? 0));
            if ($quantityAvailable <= 0) {
                continue;
            }

            $allocatedQuantity = min($remainingQuantity, $quantityAvailable);
            if ($allocatedQuantity <= 0) {
                continue;
            }

            $availableLots[$index]['quantity_available'] = $quantityAvailable - $allocatedQuantity;
            $allocations[] = array_merge($lot, [
                'allocated_quantity' => $allocatedQuantity,
            ]);
            $remainingQuantity -= $allocatedQuantity;

            if ($remainingQuantity === 0) {
                break;
            }
        }

        if ($remainingQuantity > 0) {
            $availableLots = $originalLots;

            return [];
        }

        return $allocations;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStockMovementLot(int $bookId, int $lotId, bool $allowEmptyLot = false): ?array
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT
                id,
                book_id,
                source_movement_id,
                quantity_received,
                quantity_available,
                unit_cost,
                unit_sale_price,
                occurred_at,
                notes,
                created_at
             FROM bookshop_stock_lots
             WHERE id = :id AND book_id = :book_id%s
             LIMIT 1
             FOR UPDATE',
            $allowEmptyLot ? '' : ' AND quantity_available > 0'
        ));
        $statement->execute([
            'id' => $lotId,
            'book_id' => $bookId,
        ]);

        $lot = $statement->fetch();

        return $lot ?: null;
    }

    private function formatStockLotCode(int $lotId): string
    {
        return 'LOT-' . str_pad((string) max(0, $lotId), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $category
     * @return array<string, mixed>
     */
    private function normalizeCategory(array $category): array
    {
        return array_merge($category, [
            'id' => (int) ($category['id'] ?? 0),
            'is_active' => (int) ($category['is_active'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $genre
     * @return array<string, mixed>
     */
    private function normalizeGenre(array $genre): array
    {
        return array_merge($genre, [
            'id' => (int) ($genre['id'] ?? 0),
            'is_active' => (int) ($genre['is_active'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $collection
     * @return array<string, mixed>
     */
    private function normalizeCollection(array $collection): array
    {
        return array_merge($collection, [
            'id' => (int) ($collection['id'] ?? 0),
            'is_active' => (int) ($collection['is_active'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id: int|null, name: string|null}
     */
    private function resolveCategoryReference(array $data): array
    {
        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId > 0) {
            $category = $this->findCategoryRecordById($categoryId);
            if ($category !== null) {
                return [
                    'id' => (int) ($category['id'] ?? 0),
                    'name' => $this->nullableText($category['name'] ?? null),
                ];
            }
        }

        $categoryName = trim((string) ($data['category_name'] ?? ''));
        if ($categoryName === '') {
            return [
                'id' => null,
                'name' => null,
            ];
        }

        $category = $this->findOrCreateCategoryByName($categoryName);

        return [
            'id' => (int) ($category['id'] ?? 0) ?: null,
            'name' => $this->nullableText($category['name'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id: int|null, name: string|null}
     */
    private function resolveGenreReference(array $data): array
    {
        $genreId = (int) ($data['genre_id'] ?? 0);
        if ($genreId > 0) {
            $genre = $this->findGenreRecordById($genreId);
            if ($genre !== null) {
                return [
                    'id' => (int) ($genre['id'] ?? 0),
                    'name' => $this->nullableText($genre['name'] ?? null),
                ];
            }
        }

        $genreName = trim((string) ($data['genre_name'] ?? ''));
        if ($genreName === '') {
            return [
                'id' => null,
                'name' => null,
            ];
        }

        $genre = $this->findOrCreateGenreByName($genreName);

        return [
            'id' => (int) ($genre['id'] ?? 0) ?: null,
            'name' => $this->nullableText($genre['name'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id: int|null, name: string|null}
     */
    private function resolveCollectionReference(array $data): array
    {
        $collectionId = (int) ($data['collection_id'] ?? 0);
        if ($collectionId > 0) {
            $collection = $this->findCollectionRecordById($collectionId);
            if ($collection !== null) {
                return [
                    'id' => (int) ($collection['id'] ?? 0),
                    'name' => $this->nullableText($collection['name'] ?? null),
                ];
            }
        }

        $collectionName = trim((string) ($data['collection_name'] ?? ''));
        if ($collectionName === '') {
            return [
                'id' => null,
                'name' => null,
            ];
        }

        $collection = $this->findOrCreateCollectionByName($collectionName);

        return [
            'id' => (int) ($collection['id'] ?? 0) ?: null,
            'name' => $this->nullableText($collection['name'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCategoryRecordById(int $id): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, slug, name, description, is_active, created_at, updated_at
            FROM bookshop_categories
            WHERE id = :id
            LIMIT 1
        SQL);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $category = $statement->fetch();

        return $category ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCategoryRecordByName(string $name): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, slug, name, description, is_active, created_at, updated_at
            FROM bookshop_categories
            WHERE LOWER(name) = LOWER(:name)
            LIMIT 1
        SQL);
        $statement->bindValue(':name', trim($name), \PDO::PARAM_STR);
        $statement->execute();

        $category = $statement->fetch();

        return $category ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findGenreRecordById(int $id): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, slug, name, description, is_active, created_at, updated_at
            FROM bookshop_genres
            WHERE id = :id
            LIMIT 1
        SQL);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $genre = $statement->fetch();

        return $genre ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findGenreRecordByName(string $name): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, slug, name, description, is_active, created_at, updated_at
            FROM bookshop_genres
            WHERE LOWER(name) = LOWER(:name)
            LIMIT 1
        SQL);
        $statement->bindValue(':name', trim($name), \PDO::PARAM_STR);
        $statement->execute();

        $genre = $statement->fetch();

        return $genre ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCollectionRecordById(int $id): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, slug, name, description, is_active, created_at, updated_at
            FROM bookshop_collections
            WHERE id = :id
            LIMIT 1
        SQL);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        $collection = $statement->fetch();

        return $collection ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCollectionRecordByName(string $name): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, slug, name, description, is_active, created_at, updated_at
            FROM bookshop_collections
            WHERE LOWER(name) = LOWER(:name)
            LIMIT 1
        SQL);
        $statement->bindValue(':name', trim($name), \PDO::PARAM_STR);
        $statement->execute();

        $collection = $statement->fetch();

        return $collection ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrCreateCategoryByName(string $name): array
    {
        $normalizedName = trim($name);
        $existingCategory = $this->findCategoryRecordByName($normalizedName);
        if ($existingCategory !== null) {
            return $existingCategory;
        }

        $slugBase = $this->slugify($normalizedName);
        if ($slugBase === '') {
            $slugBase = 'categoria';
        }

        $resolvedSlug = $this->resolveUniqueCategorySlug($slugBase);

        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO bookshop_categories (
                slug,
                name,
                description,
                is_active
            ) VALUES (
                :slug,
                :name,
                NULL,
                1
            )
        SQL);
        $statement->execute([
            'slug' => $resolvedSlug,
            'name' => $normalizedName,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $normalizedName,
            'slug' => $resolvedSlug,
            'description' => null,
            'is_active' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrCreateGenreByName(string $name): array
    {
        $normalizedName = trim($name);
        $existingGenre = $this->findGenreRecordByName($normalizedName);
        if ($existingGenre !== null) {
            return $existingGenre;
        }

        $slugBase = $this->slugify($normalizedName);
        if ($slugBase === '') {
            $slugBase = 'genero';
        }

        $resolvedSlug = $this->resolveUniqueGenreSlug($slugBase);

        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO bookshop_genres (
                slug,
                name,
                description,
                is_active
            ) VALUES (
                :slug,
                :name,
                NULL,
                1
            )
        SQL);
        $statement->execute([
            'slug' => $resolvedSlug,
            'name' => $normalizedName,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $normalizedName,
            'slug' => $resolvedSlug,
            'description' => null,
            'is_active' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrCreateCollectionByName(string $name): array
    {
        $normalizedName = trim($name);
        $existingCollection = $this->findCollectionRecordByName($normalizedName);
        if ($existingCollection !== null) {
            return $existingCollection;
        }

        $slugBase = $this->slugify($normalizedName);
        if ($slugBase === '') {
            $slugBase = 'colecao';
        }

        $resolvedSlug = $this->resolveUniqueCollectionSlug($slugBase);

        $statement = $this->pdo->prepare(<<<SQL
            INSERT INTO bookshop_collections (
                slug,
                name,
                description,
                is_active
            ) VALUES (
                :slug,
                :name,
                NULL,
                1
            )
        SQL);
        $statement->execute([
            'slug' => $resolvedSlug,
            'name' => $normalizedName,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $normalizedName,
            'slug' => $resolvedSlug,
            'description' => null,
            'is_active' => 1,
        ];
    }

    private function resolveUniqueCategorySlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->categorySlugExists($slug)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function resolveUniqueGenreSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->genreSlugExists($slug)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function resolveUniqueCollectionSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->collectionSlugExists($slug)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function categorySlugExists(string $slug): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM bookshop_categories WHERE slug = :slug'
        );
        $statement->execute(['slug' => $slug]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function genreSlugExists(string $slug): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM bookshop_genres WHERE slug = :slug'
        );
        $statement->execute(['slug' => $slug]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function collectionSlugExists(string $slug): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM bookshop_collections WHERE slug = :slug'
        );
        $statement->execute(['slug' => $slug]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function slugify(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9-]+/', '-', $normalized) ?? $normalized;

        return trim($normalized, '-');
    }

    /**
     * @param array<string, mixed> $sale
     * @return array<string, mixed>
     */
    private function normalizeSale(array $sale): array
    {
        $subtotal = (float) ($sale['subtotal_amount'] ?? 0);
        $discount = (float) ($sale['discount_amount'] ?? 0);
        $total = (float) ($sale['total_amount'] ?? 0);
        $received = isset($sale['received_amount']) && $sale['received_amount'] !== null
            ? (float) $sale['received_amount']
            : null;
        $change = isset($sale['change_amount']) && $sale['change_amount'] !== null
            ? (float) $sale['change_amount']
            : null;

        return array_merge($sale, [
            'item_count' => (int) ($sale['item_count'] ?? 0),
            'customer_phone_display' => $this->formatPhoneDisplay((string) ($sale['customer_phone'] ?? '')),
            'customer_cpf_display' => $this->formatCpfDisplay((string) ($sale['customer_cpf'] ?? '')),
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'received_amount' => $received,
            'change_amount' => $change,
            'subtotal_amount_label' => $this->formatMoney($subtotal),
            'discount_amount_label' => $this->formatMoney($discount),
            'total_amount_label' => $this->formatMoney($total),
            'received_amount_label' => $received !== null ? $this->formatMoney($received) : '',
            'change_amount_label' => $change !== null ? $this->formatMoney($change) : '',
            'payment_method_label' => $this->formatPaymentMethodLabel((string) ($sale['payment_method'] ?? 'other')),
            'status_label' => $this->formatSaleStatusLabel((string) ($sale['status'] ?? 'completed')),
            'sold_at_label' => $this->formatDateTime($sale['sold_at'] ?? null),
            'cancelled_at_label' => $this->formatDateTime($sale['cancelled_at'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeSaleItem(array $item): array
    {
        $unitCost = isset($item['unit_cost_snapshot']) && $item['unit_cost_snapshot'] !== null
            ? (float) $item['unit_cost_snapshot']
            : null;
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $lineTotal = (float) ($item['line_total'] ?? 0);

        return array_merge($item, [
            'stock_lot_id' => isset($item['stock_lot_id']) && $item['stock_lot_id'] !== null
                ? (int) $item['stock_lot_id']
                : null,
            'quantity' => (int) ($item['quantity'] ?? 0),
            'book_stock_quantity' => (int) ($item['book_stock_quantity'] ?? 0),
            'unit_cost_snapshot' => $unitCost,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'unit_cost_snapshot_label' => $unitCost !== null ? $this->formatMoney($unitCost) : '-',
            'unit_price_label' => $this->formatMoney($unitPrice),
            'line_total_label' => $this->formatMoney($lineTotal),
        ]);
    }

    /**
     * @param array<string, mixed> $movement
     * @return array<string, mixed>
     */
    private function normalizeStockMovement(array $movement): array
    {
        $unitCost = isset($movement['unit_cost']) && $movement['unit_cost'] !== null
            ? (float) $movement['unit_cost']
            : null;
        $unitSalePrice = isset($movement['unit_sale_price']) && $movement['unit_sale_price'] !== null
            ? (float) $movement['unit_sale_price']
            : null;
        $totalCost = isset($movement['total_cost']) && $movement['total_cost'] !== null
            ? (float) $movement['total_cost']
            : null;
        $totalSaleValue = isset($movement['total_sale_value']) && $movement['total_sale_value'] !== null
            ? (float) $movement['total_sale_value']
            : null;
        $stockDelta = (int) ($movement['stock_delta'] ?? 0);

        return array_merge($movement, [
            'movement_code' => 'MOV-' . str_pad((string) ((int) ($movement['id'] ?? 0)), 6, '0', STR_PAD_LEFT),
            'stock_lot_id' => isset($movement['stock_lot_id']) && $movement['stock_lot_id'] !== null
                ? (int) $movement['stock_lot_id']
                : null,
            'quantity' => (int) ($movement['quantity'] ?? 0),
            'stock_delta' => $stockDelta,
            'stock_delta_label' => sprintf('%+d', $stockDelta),
            'stock_before' => (int) ($movement['stock_before'] ?? 0),
            'stock_after' => (int) ($movement['stock_after'] ?? 0),
            'current_stock_quantity' => (int) ($movement['current_stock_quantity'] ?? 0),
            'unit_cost' => $unitCost,
            'unit_sale_price' => $unitSalePrice,
            'total_cost' => $totalCost,
            'total_sale_value' => $totalSaleValue,
            'unit_cost_label' => $unitCost !== null ? $this->formatMoney($unitCost) : '-',
            'unit_sale_price_label' => $unitSalePrice !== null ? $this->formatMoney($unitSalePrice) : '-',
            'total_cost_label' => $totalCost !== null ? $this->formatMoney($totalCost) : '-',
            'total_sale_value_label' => $totalSaleValue !== null ? $this->formatMoney($totalSaleValue) : '-',
            'movement_type_label' => $this->formatStockMovementTypeLabel((string) ($movement['movement_type'] ?? '')),
            'occurred_at_label' => $this->formatDateTime($movement['occurred_at'] ?? null),
        ]);
    }

    private function formatMoney(float $amount): string
    {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }

    private function formatBookStatusLabel(string $status): string
    {
        $map = [
            'active' => 'Ativo',
            'inactive' => 'Inativo',
        ];

        return $map[$status] ?? ucfirst($status);
    }

    private function formatSaleStatusLabel(string $status): string
    {
        $map = [
            'completed' => 'Concluída',
            'cancelled' => 'Cancelada',
        ];

        return $map[$status] ?? ucfirst($status);
    }

    private function formatPaymentMethodLabel(string $paymentMethod): string
    {
        $map = [
            'cash' => 'Dinheiro',
            'pix' => 'PIX',
            'debit' => 'Cartão de débito',
            'credit' => 'Cartão de crédito',
            'transfer' => 'Transferência',
            'other' => 'Outro',
        ];

        return $map[$paymentMethod] ?? ucfirst($paymentMethod);
    }

    private function formatStockMovementTypeLabel(string $movementType): string
    {
        $map = [
            'entry' => 'Compra / reposição',
            'donation' => 'Doação recebida',
            'adjustment_add' => 'Ajuste positivo',
            'adjustment_remove' => 'Ajuste negativo',
            'loss' => 'Perda ou avaria',
        ];

        return $map[$movementType] ?? ucfirst(str_replace('_', ' ', $movementType));
    }

    private function formatStockStateLabel(string $state): string
    {
        $map = [
            'ok' => 'Em estoque',
            'low' => 'Estoque baixo',
            'out' => 'Sem estoque',
        ];

        return $map[$state] ?? ucfirst($state);
    }

    private function formatDateTime(mixed $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($normalized, new \DateTimeZone(self::SALE_STORAGE_TIMEZONE));

            return $date
                ->setTimezone(new \DateTimeZone(self::SALE_DISPLAY_TIMEZONE))
                ->format('d/m/Y H:i');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    private function formatPhoneDisplay(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return trim($value);
    }

    private function formatCpfDisplay(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 11) {
            return sprintf(
                '%s.%s.%s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 3),
                substr($digits, 9, 2)
            );
        }

        return trim($value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBookForSale(int $bookId): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, sku, title, author_name, sale_price, stock_quantity, status
            FROM bookshop_books
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        SQL);
        $statement->bindValue(':id', $bookId, \PDO::PARAM_INT);
        $statement->execute();

        $book = $statement->fetch();
        if (!$book) {
            return null;
        }

        if ((string) ($book['status'] ?? 'active') !== 'active') {
            return null;
        }

        return $book;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBookForStockMovement(int $bookId): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, sku, title, author_name, cost_price, sale_price, stock_quantity, status, location_label
            FROM bookshop_books
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        SQL);
        $statement->bindValue(':id', $bookId, \PDO::PARAM_INT);
        $statement->execute();

        $book = $statement->fetch();

        return $book ?: null;
    }

    private function generateSaleCode(): string
    {
        try {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
        } catch (\Throwable $exception) {
            $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 4));
        }

        return 'PDV-' . date('Ymd-His') . '-' . $suffix;
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

            $this->ensureBookshopSchemaCompatibility();

            return $operation();
        }
    }

    private function ensureBookshopSchemaCompatibility(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS bookshop_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(120) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bookshop_categories_name (name),
                INDEX idx_bookshop_categories_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS bookshop_genres (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(120) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bookshop_genres_name (name),
                INDEX idx_bookshop_genres_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS bookshop_collections (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(120) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bookshop_collections_name (name),
                INDEX idx_bookshop_collections_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS bookshop_books (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(80) NOT NULL UNIQUE,
                slug VARCHAR(180) NOT NULL UNIQUE,
                category_id BIGINT UNSIGNED NULL,
                category_name VARCHAR(160) NULL,
                genre_id BIGINT UNSIGNED NULL,
                genre_name VARCHAR(160) NULL,
                collection_id BIGINT UNSIGNED NULL,
                collection_name VARCHAR(160) NULL,
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255) NULL,
                author_name VARCHAR(255) NOT NULL,
                publisher_name VARCHAR(255) NULL,
                isbn VARCHAR(40) NULL UNIQUE,
                barcode VARCHAR(60) NULL,
                edition_label VARCHAR(120) NULL,
                volume_number SMALLINT UNSIGNED NULL,
                volume_label VARCHAR(120) NULL,
                publication_year SMALLINT UNSIGNED NULL,
                page_count INT UNSIGNED NULL,
                language VARCHAR(80) NULL,
                description TEXT NULL,
                cover_image_path VARCHAR(255) NULL,
                cover_image_mime_type VARCHAR(120) NULL,
                cover_image_size_bytes BIGINT UNSIGNED NULL,
                cost_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                sale_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                stock_quantity INT NOT NULL DEFAULT 0,
                stock_minimum INT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                location_label VARCHAR(120) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bookshop_books_title (title),
                INDEX idx_bookshop_books_author (author_name),
                INDEX idx_bookshop_books_category_id (category_id),
                INDEX idx_bookshop_books_category (category_name),
                INDEX idx_bookshop_books_genre_id (genre_id),
                INDEX idx_bookshop_books_genre (genre_name),
                INDEX idx_bookshop_books_collection_id (collection_id),
                INDEX idx_bookshop_books_collection (collection_name),
                INDEX idx_bookshop_books_barcode (barcode),
                INDEX idx_bookshop_books_status (status),
                INDEX idx_bookshop_books_stock (stock_quantity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS bookshop_sales (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sale_code VARCHAR(40) NOT NULL UNIQUE,
                sold_at DATETIME NOT NULL,
                customer_name VARCHAR(160) NULL,
                customer_phone VARCHAR(20) NULL,
                customer_email VARCHAR(160) NULL,
                customer_cpf VARCHAR(14) NULL,
                payment_method VARCHAR(30) NOT NULL,
                item_count INT UNSIGNED NOT NULL DEFAULT 0,
                subtotal_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                received_amount DECIMAL(10, 2) NULL,
                change_amount DECIMAL(10, 2) NULL,
                notes TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'completed',
                created_by_member_id BIGINT UNSIGNED NULL,
                created_by_name VARCHAR(160) NULL,
                cancelled_at DATETIME NULL,
                cancelled_by_member_id BIGINT UNSIGNED NULL,
                cancelled_by_name VARCHAR(160) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bookshop_sales_sold_at (sold_at),
                INDEX idx_bookshop_sales_status (status),
                INDEX idx_bookshop_sales_payment (payment_method)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS bookshop_stock_lots (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                book_id BIGINT UNSIGNED NOT NULL,
                source_movement_id BIGINT UNSIGNED NULL,
                quantity_received INT UNSIGNED NOT NULL DEFAULT 1,
                quantity_available INT UNSIGNED NOT NULL DEFAULT 1,
                unit_cost DECIMAL(10, 2) NULL,
                unit_sale_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                occurred_at DATETIME NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_bookshop_stock_lots_book
                    FOREIGN KEY (book_id) REFERENCES bookshop_books(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                INDEX idx_bookshop_stock_lots_book (book_id),
                INDEX idx_bookshop_stock_lots_movement (source_movement_id),
                INDEX idx_bookshop_stock_lots_available (quantity_available),
                INDEX idx_bookshop_stock_lots_occurred_at (occurred_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS bookshop_sale_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sale_id BIGINT UNSIGNED NOT NULL,
                book_id BIGINT UNSIGNED NOT NULL,
                stock_lot_id BIGINT UNSIGNED NULL,
                stock_lot_code_snapshot VARCHAR(40) NULL,
                sku_snapshot VARCHAR(80) NOT NULL,
                title_snapshot VARCHAR(255) NOT NULL,
                author_snapshot VARCHAR(255) NULL,
                unit_cost_snapshot DECIMAL(10, 2) NULL,
                unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                line_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_bookshop_sale_items_sale
                    FOREIGN KEY (sale_id) REFERENCES bookshop_sales(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                CONSTRAINT fk_bookshop_sale_items_book
                    FOREIGN KEY (book_id) REFERENCES bookshop_books(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                INDEX idx_bookshop_sale_items_sale (sale_id),
                INDEX idx_bookshop_sale_items_book (book_id),
                INDEX idx_bookshop_sale_items_lot (stock_lot_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS bookshop_stock_movements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                book_id BIGINT UNSIGNED NOT NULL,
                stock_lot_id BIGINT UNSIGNED NULL,
                stock_lot_code_snapshot VARCHAR(40) NULL,
                sku_snapshot VARCHAR(80) NOT NULL,
                title_snapshot VARCHAR(255) NOT NULL,
                author_snapshot VARCHAR(255) NULL,
                movement_type VARCHAR(30) NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                stock_delta INT NOT NULL DEFAULT 0,
                stock_before INT NOT NULL DEFAULT 0,
                stock_after INT NOT NULL DEFAULT 0,
                unit_cost DECIMAL(10, 2) NULL,
                unit_sale_price DECIMAL(10, 2) NULL,
                total_cost DECIMAL(10, 2) NULL,
                total_sale_value DECIMAL(10, 2) NULL,
                notes TEXT NULL,
                occurred_at DATETIME NOT NULL,
                created_by_member_id BIGINT UNSIGNED NULL,
                created_by_name VARCHAR(160) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_bookshop_stock_movements_book
                    FOREIGN KEY (book_id) REFERENCES bookshop_books(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                INDEX idx_bookshop_stock_movements_book (book_id),
                INDEX idx_bookshop_stock_movements_type (movement_type),
                INDEX idx_bookshop_stock_movements_occurred_at (occurred_at),
                INDEX idx_bookshop_stock_movements_created_by (created_by_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->ensureColumn(
            'bookshop_sales',
            'customer_phone',
            'ALTER TABLE bookshop_sales ADD COLUMN customer_phone VARCHAR(20) NULL AFTER customer_name'
        );
        $this->ensureColumn(
            'bookshop_sales',
            'customer_email',
            'ALTER TABLE bookshop_sales ADD COLUMN customer_email VARCHAR(160) NULL AFTER customer_phone'
        );
        $this->ensureColumn(
            'bookshop_sales',
            'customer_cpf',
            'ALTER TABLE bookshop_sales ADD COLUMN customer_cpf VARCHAR(14) NULL AFTER customer_email'
        );
        $this->ensureColumn(
            'bookshop_sales',
            'received_amount',
            'ALTER TABLE bookshop_sales ADD COLUMN received_amount DECIMAL(10, 2) NULL AFTER total_amount'
        );
        $this->ensureColumn(
            'bookshop_sales',
            'change_amount',
            'ALTER TABLE bookshop_sales ADD COLUMN change_amount DECIMAL(10, 2) NULL AFTER received_amount'
        );
        $this->ensureColumn(
            'bookshop_sale_items',
            'stock_lot_id',
            'ALTER TABLE bookshop_sale_items ADD COLUMN stock_lot_id BIGINT UNSIGNED NULL AFTER book_id'
        );
        $this->ensureColumn(
            'bookshop_sale_items',
            'stock_lot_code_snapshot',
            'ALTER TABLE bookshop_sale_items ADD COLUMN stock_lot_code_snapshot VARCHAR(40) NULL AFTER stock_lot_id'
        );
        $this->ensureColumn(
            'bookshop_sale_items',
            'unit_cost_snapshot',
            'ALTER TABLE bookshop_sale_items ADD COLUMN unit_cost_snapshot DECIMAL(10, 2) NULL AFTER author_snapshot'
        );
        $this->ensureColumn(
            'bookshop_stock_movements',
            'stock_lot_id',
            'ALTER TABLE bookshop_stock_movements ADD COLUMN stock_lot_id BIGINT UNSIGNED NULL AFTER book_id'
        );
        $this->ensureColumn(
            'bookshop_stock_movements',
            'stock_lot_code_snapshot',
            'ALTER TABLE bookshop_stock_movements ADD COLUMN stock_lot_code_snapshot VARCHAR(40) NULL AFTER stock_lot_id'
        );
        $this->ensureColumn(
            'bookshop_stock_movements',
            'unit_sale_price',
            'ALTER TABLE bookshop_stock_movements ADD COLUMN unit_sale_price DECIMAL(10, 2) NULL AFTER unit_cost'
        );
        $this->ensureColumn(
            'bookshop_stock_movements',
            'total_sale_value',
            'ALTER TABLE bookshop_stock_movements ADD COLUMN total_sale_value DECIMAL(10, 2) NULL AFTER total_cost'
        );
        $this->ensureColumn(
            'bookshop_books',
            'category_id',
            'ALTER TABLE bookshop_books ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER slug'
        );
        $this->ensureColumn(
            'bookshop_books',
            'cover_image_path',
            'ALTER TABLE bookshop_books ADD COLUMN cover_image_path VARCHAR(255) NULL AFTER description'
        );
        $this->ensureColumn(
            'bookshop_books',
            'genre_id',
            'ALTER TABLE bookshop_books ADD COLUMN genre_id BIGINT UNSIGNED NULL AFTER category_name'
        );
        $this->ensureColumn(
            'bookshop_books',
            'genre_name',
            'ALTER TABLE bookshop_books ADD COLUMN genre_name VARCHAR(160) NULL AFTER genre_id'
        );
        $this->ensureColumn(
            'bookshop_books',
            'collection_id',
            'ALTER TABLE bookshop_books ADD COLUMN collection_id BIGINT UNSIGNED NULL AFTER genre_name'
        );
        $this->ensureColumn(
            'bookshop_books',
            'collection_name',
            'ALTER TABLE bookshop_books ADD COLUMN collection_name VARCHAR(160) NULL AFTER collection_id'
        );
        $this->ensureColumn(
            'bookshop_books',
            'barcode',
            'ALTER TABLE bookshop_books ADD COLUMN barcode VARCHAR(60) NULL AFTER isbn'
        );
        $this->ensureColumn(
            'bookshop_books',
            'volume_number',
            'ALTER TABLE bookshop_books ADD COLUMN volume_number SMALLINT UNSIGNED NULL AFTER edition_label'
        );
        $this->ensureColumn(
            'bookshop_books',
            'volume_label',
            'ALTER TABLE bookshop_books ADD COLUMN volume_label VARCHAR(120) NULL AFTER volume_number'
        );
        $this->ensureColumn(
            'bookshop_books',
            'cover_image_mime_type',
            'ALTER TABLE bookshop_books ADD COLUMN cover_image_mime_type VARCHAR(120) NULL AFTER cover_image_path'
        );
        $this->ensureColumn(
            'bookshop_books',
            'cover_image_size_bytes',
            'ALTER TABLE bookshop_books ADD COLUMN cover_image_size_bytes BIGINT UNSIGNED NULL AFTER cover_image_mime_type'
        );
        $this->ensureColumn(
            'bookshop_books',
            'page_count',
            'ALTER TABLE bookshop_books ADD COLUMN page_count INT UNSIGNED NULL AFTER publication_year'
        );
        $this->ensureIndex(
            'bookshop_books',
            'idx_bookshop_books_category_id',
            'ALTER TABLE bookshop_books ADD INDEX idx_bookshop_books_category_id (category_id)'
        );
        $this->ensureIndex(
            'bookshop_books',
            'idx_bookshop_books_genre_id',
            'ALTER TABLE bookshop_books ADD INDEX idx_bookshop_books_genre_id (genre_id)'
        );
        $this->ensureIndex(
            'bookshop_books',
            'idx_bookshop_books_genre',
            'ALTER TABLE bookshop_books ADD INDEX idx_bookshop_books_genre (genre_name)'
        );
        $this->ensureIndex(
            'bookshop_books',
            'idx_bookshop_books_collection_id',
            'ALTER TABLE bookshop_books ADD INDEX idx_bookshop_books_collection_id (collection_id)'
        );
        $this->ensureIndex(
            'bookshop_books',
            'idx_bookshop_books_collection',
            'ALTER TABLE bookshop_books ADD INDEX idx_bookshop_books_collection (collection_name)'
        );
        $this->ensureIndex(
            'bookshop_books',
            'idx_bookshop_books_barcode',
            'ALTER TABLE bookshop_books ADD INDEX idx_bookshop_books_barcode (barcode)'
        );
        $this->ensureIndex(
            'bookshop_stock_movements',
            'idx_bookshop_stock_movements_lot',
            'ALTER TABLE bookshop_stock_movements ADD INDEX idx_bookshop_stock_movements_lot (stock_lot_id)'
        );
        $this->ensureIndex(
            'bookshop_sale_items',
            'idx_bookshop_sale_items_lot',
            'ALTER TABLE bookshop_sale_items ADD INDEX idx_bookshop_sale_items_lot (stock_lot_id)'
        );
        $this->syncLegacyBookCategories();
        $this->syncMissingBookCategoryNames();
        $this->syncMissingBookGenreNames();
        $this->syncMissingBookCollectionNames();
        $this->syncMissingBookStockLots();

        $this->schemaEnsured = true;
    }

    private function ensureColumn(string $tableName, string $columnName, string $alterSql): void
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name '
            . 'AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        if ((int) $statement->fetchColumn() === 0) {
            $this->pdo->exec($alterSql);
        }
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

    private function syncLegacyBookCategories(): void
    {
        $statement = $this->pdo->query(<<<SQL
            SELECT DISTINCT TRIM(category_name) AS category_name
            FROM bookshop_books
            WHERE category_name IS NOT NULL
              AND TRIM(category_name) <> ''
        SQL);

        $updateStatement = $this->pdo->prepare(<<<SQL
            UPDATE bookshop_books
            SET category_id = :category_id
            WHERE (category_id IS NULL OR category_id = 0)
              AND TRIM(category_name) = :category_name
        SQL);

        foreach ($statement->fetchAll() ?: [] as $row) {
            $categoryName = trim((string) ($row['category_name'] ?? ''));
            if ($categoryName === '') {
                continue;
            }

            $category = $this->findOrCreateCategoryByName($categoryName);
            $updateStatement->execute([
                'category_id' => (int) ($category['id'] ?? 0) ?: null,
                'category_name' => $categoryName,
            ]);
        }
    }

    private function syncMissingBookCategoryNames(): void
    {
        $this->pdo->exec(<<<SQL
            UPDATE bookshop_books b
            INNER JOIN bookshop_categories c ON c.id = b.category_id
            SET b.category_name = c.name
            WHERE b.category_id IS NOT NULL
              AND (b.category_name IS NULL OR TRIM(b.category_name) = '')
        SQL);
    }

    private function syncMissingBookGenreNames(): void
    {
        $this->pdo->exec(<<<SQL
            UPDATE bookshop_books b
            INNER JOIN bookshop_genres g ON g.id = b.genre_id
            SET b.genre_name = g.name
            WHERE b.genre_id IS NOT NULL
              AND (b.genre_name IS NULL OR TRIM(b.genre_name) = '')
        SQL);
    }

    private function syncMissingBookCollectionNames(): void
    {
        $this->pdo->exec(<<<SQL
            UPDATE bookshop_books b
            INNER JOIN bookshop_collections c ON c.id = b.collection_id
            SET b.collection_name = c.name
            WHERE b.collection_id IS NOT NULL
              AND (b.collection_name IS NULL OR TRIM(b.collection_name) = '')
        SQL);
    }

    private function syncMissingBookStockLots(): void
    {
        $statement = $this->pdo->query(<<<SQL
            SELECT
                b.id,
                b.stock_quantity,
                b.cost_price,
                b.sale_price,
                COALESCE(b.updated_at, b.created_at, NOW()) AS occurred_at,
                COALESCE(SUM(l.quantity_available), 0) AS lot_quantity_available
            FROM bookshop_books b
            LEFT JOIN bookshop_stock_lots l ON l.book_id = b.id
            WHERE b.stock_quantity > 0
            GROUP BY
                b.id,
                b.stock_quantity,
                b.cost_price,
                b.sale_price,
                COALESCE(b.updated_at, b.created_at, NOW())
            HAVING COALESCE(SUM(l.quantity_available), 0) < b.stock_quantity
        SQL);

        foreach ($statement->fetchAll() ?: [] as $row) {
            $missingQuantity = max(
                0,
                (int) ($row['stock_quantity'] ?? 0) - (int) ($row['lot_quantity_available'] ?? 0)
            );

            if ($missingQuantity <= 0) {
                continue;
            }

            $this->createStockLot([
                'book_id' => (int) ($row['id'] ?? 0),
                'source_movement_id' => null,
                'quantity_received' => $missingQuantity,
                'quantity_available' => $missingQuantity,
                'unit_cost' => isset($row['cost_price']) ? (float) $row['cost_price'] : null,
                'unit_sale_price' => (float) ($row['sale_price'] ?? 0),
                'occurred_at' => (string) ($row['occurred_at'] ?? gmdate('Y-m-d H:i:s')),
                'notes' => 'Lote de saldo inicial gerado automaticamente para compatibilidade do estoque.',
            ]);
        }
    }

    private function syncBookCategoryNamesFromCategoryId(int $categoryId): void
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE bookshop_books b
            INNER JOIN bookshop_categories c ON c.id = b.category_id
            SET b.category_name = c.name
            WHERE c.id = :category_id
        SQL);
        $statement->bindValue(':category_id', $categoryId, \PDO::PARAM_INT);
        $statement->execute();
    }

    private function syncBookGenreNamesFromGenreId(int $genreId): void
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE bookshop_books b
            INNER JOIN bookshop_genres g ON g.id = b.genre_id
            SET b.genre_name = g.name
            WHERE g.id = :genre_id
        SQL);
        $statement->bindValue(':genre_id', $genreId, \PDO::PARAM_INT);
        $statement->execute();
    }

    private function syncBookCollectionNamesFromCollectionId(int $collectionId): void
    {
        $statement = $this->pdo->prepare(<<<SQL
            UPDATE bookshop_books b
            INNER JOIN bookshop_collections c ON c.id = b.collection_id
            SET b.collection_name = c.name
            WHERE c.id = :collection_id
        SQL);
        $statement->bindValue(':collection_id', $collectionId, \PDO::PARAM_INT);
        $statement->execute();
    }
}
