<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Library;

use App\Domain\Library\LibraryRepository;

class MySqlLibraryRepository implements LibraryRepository
{
    private \PDO $pdo;

    private bool $schemaEnsured = false;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findPublishedBooks(): array
    {
        $operation = function (): array {
            $sql = $this->buildBaseSelect() . <<<SQL
                WHERE b.status = 'published'
                  AND c.is_active = 1
                ORDER BY b.title ASC, b.author_name ASC
            SQL;

            $statement = $this->pdo->query($sql);

            return array_map([$this, 'normalizeBook'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllBooksForAdmin(): array
    {
        $operation = function (): array {
            $sql = $this->buildBaseSelect() . <<<SQL
                ORDER BY b.title ASC, b.author_name ASC
            SQL;

            $statement = $this->pdo->query($sql);

            return array_map([$this, 'normalizeBook'], $statement->fetchAll() ?: []);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findBookByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $sql = $this->buildBaseSelect() . <<<SQL
                WHERE b.id = :id
                LIMIT 1
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $book = $statement->fetch();

            if (!$book) {
                return null;
            }

            return $this->normalizeBook($book);
        };

        return $this->withSchemaRetry($operation);
    }

    public function createBook(array $data): int
    {
        $operation = function () use ($data): int {
            $sql = <<<SQL
                INSERT INTO library_books (
                    category_id,
                    slug,
                    title,
                    subtitle,
                    author_name,
                    organizer_name,
                    translator_name,
                    publisher_name,
                    publication_city,
                    publication_year,
                    edition_label,
                    isbn,
                    page_count,
                    language,
                    description,
                    cover_image_path,
                    cover_image_mime_type,
                    cover_image_size_bytes,
                    pdf_path,
                    pdf_mime_type,
                    pdf_size_bytes,
                    status
                ) VALUES (
                    :category_id,
                    :slug,
                    :title,
                    :subtitle,
                    :author_name,
                    :organizer_name,
                    :translator_name,
                    :publisher_name,
                    :publication_city,
                    :publication_year,
                    :edition_label,
                    :isbn,
                    :page_count,
                    :language,
                    :description,
                    :cover_image_path,
                    :cover_image_mime_type,
                    :cover_image_size_bytes,
                    :pdf_path,
                    :pdf_mime_type,
                    :pdf_size_bytes,
                    :status
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
                UPDATE library_books
                SET
                    category_id = :category_id,
                    slug = :slug,
                    title = :title,
                    subtitle = :subtitle,
                    author_name = :author_name,
                    organizer_name = :organizer_name,
                    translator_name = :translator_name,
                    publisher_name = :publisher_name,
                    publication_city = :publication_city,
                    publication_year = :publication_year,
                    edition_label = :edition_label,
                    isbn = :isbn,
                    page_count = :page_count,
                    language = :language,
                    description = :description,
                    cover_image_path = :cover_image_path,
                    cover_image_mime_type = :cover_image_mime_type,
                    cover_image_size_bytes = :cover_image_size_bytes,
                    pdf_path = :pdf_path,
                    pdf_mime_type = :pdf_mime_type,
                    pdf_size_bytes = :pdf_size_bytes,
                    status = :status
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
            $statement = $this->pdo->prepare('DELETE FROM library_books WHERE id = :id LIMIT 1');

            return $statement->execute(['id' => $id]);
        };

        return $this->withSchemaRetry($operation);
    }

    public function findActiveCategories(): array
    {
        $operation = function (): array {
            $sql = <<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    color
                FROM library_categories
                WHERE is_active = 1
                ORDER BY name ASC
            SQL;

            $statement = $this->pdo->query($sql);

            return $statement->fetchAll() ?: [];
        };

        return $this->withSchemaRetry($operation);
    }

    public function findAllCategoriesForAdmin(): array
    {
        $operation = function (): array {
            $sql = <<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    color,
                    is_active,
                    created_at,
                    updated_at
                FROM library_categories
                ORDER BY name ASC
            SQL;

            $statement = $this->pdo->query($sql);

            return $statement->fetchAll() ?: [];
        };

        return $this->withSchemaRetry($operation);
    }

    public function findCategoryByIdForAdmin(int $id): ?array
    {
        $operation = function () use ($id): ?array {
            $sql = <<<SQL
                SELECT
                    id,
                    slug,
                    name,
                    description,
                    color,
                    is_active
                FROM library_categories
                WHERE id = :id
                LIMIT 1
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();

            $category = $statement->fetch();

            if (!$category) {
                return null;
            }

            return $category;
        };

        return $this->withSchemaRetry($operation);
    }

    public function createCategory(array $data): int
    {
        $operation = function () use ($data): int {
            $sql = <<<SQL
                INSERT INTO library_categories (
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
            SQL;

            $statement = $this->pdo->prepare($sql);
            $statement->execute($this->buildCategoryWriteParams($data));

            return (int) $this->pdo->lastInsertId();
        };

        return $this->withSchemaRetry($operation);
    }

    public function updateCategory(int $id, array $data): bool
    {
        $operation = function () use ($id, $data): bool {
            $sql = <<<SQL
                UPDATE library_categories
                SET
                    slug = :slug,
                    name = :name,
                    description = :description,
                    color = :color,
                    is_active = :is_active
                WHERE id = :id
                LIMIT 1
            SQL;

            $statement = $this->pdo->prepare($sql);
            $params = $this->buildCategoryWriteParams($data);
            $params['id'] = $id;

            return $statement->execute($params);
        };

        return $this->withSchemaRetry($operation);
    }

    public function setCategoryActive(int $id, bool $isActive): bool
    {
        $operation = function () use ($id, $isActive): bool {
            $sql = <<<SQL
                UPDATE library_categories
                SET is_active = :is_active
                WHERE id = :id
                LIMIT 1
            SQL;

            $statement = $this->pdo->prepare($sql);

            return $statement->execute([
                'id' => $id,
                'is_active' => $isActive ? 1 : 0,
            ]);
        };

        return $this->withSchemaRetry($operation);
    }

    private function buildBaseSelect(): string
    {
        return <<<SQL
            SELECT
                b.id,
                b.category_id,
                b.slug,
                b.title,
                b.subtitle,
                b.author_name,
                b.organizer_name,
                b.translator_name,
                b.publisher_name,
                b.publication_city,
                b.publication_year,
                b.edition_label,
                b.isbn,
                b.page_count,
                b.language,
                b.description,
                b.cover_image_path,
                b.cover_image_mime_type,
                b.cover_image_size_bytes,
                b.pdf_path,
                b.pdf_mime_type,
                b.pdf_size_bytes,
                b.status,
                b.created_at,
                b.updated_at,
                c.slug AS category_slug,
                c.name AS category_name,
                c.color AS category_color,
                c.is_active AS category_is_active
            FROM library_books b
            INNER JOIN library_categories c ON c.id = b.category_id
        SQL;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildBookWriteParams(array $data): array
    {
        return [
            'category_id' => (int) ($data['category_id'] ?? 0),
            'slug' => (string) ($data['slug'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'subtitle' => $this->nullableText($data['subtitle'] ?? null),
            'author_name' => (string) ($data['author_name'] ?? ''),
            'organizer_name' => $this->nullableText($data['organizer_name'] ?? null),
            'translator_name' => $this->nullableText($data['translator_name'] ?? null),
            'publisher_name' => $this->nullableText($data['publisher_name'] ?? null),
            'publication_city' => $this->nullableText($data['publication_city'] ?? null),
            'publication_year' => $this->nullableInteger($data['publication_year'] ?? null),
            'edition_label' => $this->nullableText($data['edition_label'] ?? null),
            'isbn' => $this->nullableText($data['isbn'] ?? null),
            'page_count' => $this->nullableInteger($data['page_count'] ?? null),
            'language' => $this->nullableText($data['language'] ?? null),
            'description' => $this->nullableText($data['description'] ?? null),
            'cover_image_path' => $this->nullableText($data['cover_image_path'] ?? null),
            'cover_image_mime_type' => $this->nullableText($data['cover_image_mime_type'] ?? null),
            'cover_image_size_bytes' => $this->nullableInteger($data['cover_image_size_bytes'] ?? null),
            'pdf_path' => (string) ($data['pdf_path'] ?? ''),
            'pdf_mime_type' => $this->nullableText($data['pdf_mime_type'] ?? null),
            'pdf_size_bytes' => $this->nullableInteger($data['pdf_size_bytes'] ?? null),
            'status' => (string) ($data['status'] ?? 'draft'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildCategoryWriteParams(array $data): array
    {
        return [
            'slug' => (string) ($data['slug'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'description' => $this->nullableText($data['description'] ?? null),
            'color' => $this->nullableText($data['color'] ?? null),
            'is_active' => (int) ($data['is_active'] ?? 1),
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

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    /**
     * @param array<string, mixed> $book
     * @return array<string, mixed>
     */
    private function normalizeBook(array $book): array
    {
        $coverImagePath = ltrim((string) ($book['cover_image_path'] ?? ''), '/');
        $pdfPath = ltrim((string) ($book['pdf_path'] ?? ''), '/');
        $publicationYear = isset($book['publication_year']) && $book['publication_year'] !== null
            ? (int) $book['publication_year']
            : null;
        $pageCount = isset($book['page_count']) && $book['page_count'] !== null
            ? (int) $book['page_count']
            : null;
        $coverImageSizeBytes = isset($book['cover_image_size_bytes']) && $book['cover_image_size_bytes'] !== null
            ? (int) $book['cover_image_size_bytes']
            : null;
        $pdfSizeBytes = isset($book['pdf_size_bytes']) && $book['pdf_size_bytes'] !== null
            ? (int) $book['pdf_size_bytes']
            : null;

        return array_merge($book, [
            'publication_year' => $publicationYear,
            'page_count' => $pageCount,
            'cover_image_size_bytes' => $coverImageSizeBytes,
            'cover_image_url' => $coverImagePath !== '' ? '/' . $coverImagePath : '',
            'pdf_size_bytes' => $pdfSizeBytes,
            'pdf_url' => $pdfPath !== '' ? '/' . $pdfPath : '',
            'pdf_size_label' => $pdfSizeBytes !== null ? $this->formatBytes($pdfSizeBytes) : '',
            'status_label' => $this->formatStatusLabel((string) ($book['status'] ?? 'draft')),
            'editorial_reference' => $this->buildEditorialReference($book),
        ]);
    }

    /**
     * @param array<string, mixed> $book
     */
    private function buildEditorialReference(array $book): string
    {
        $parts = array_filter([
            trim((string) ($book['publisher_name'] ?? '')),
            trim((string) ($book['publication_city'] ?? '')),
            !empty($book['publication_year']) ? (string) $book['publication_year'] : '',
            trim((string) ($book['edition_label'] ?? '')),
        ], static fn (string $value): bool => $value !== '');

        return implode(' • ', $parts);
    }

    private function formatStatusLabel(string $status): string
    {
        $map = [
            'draft' => 'Rascunho',
            'published' => 'Publicado',
        ];

        return $map[$status] ?? ucfirst($status);
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

        $precision = $unitIndex === 0 ? 0 : 1;

        return number_format($size, $precision, ',', '.') . ' ' . $units[$unitIndex];
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

            $this->ensureLibrarySchemaCompatibility();

            return $operation();
        }
    }

    private function ensureLibrarySchemaCompatibility(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS library_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(120) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                color VARCHAR(20) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_library_categories_name (name),
                INDEX idx_library_categories_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS library_books (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NOT NULL,
                slug VARCHAR(180) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255) NULL,
                author_name VARCHAR(255) NOT NULL,
                organizer_name VARCHAR(255) NULL,
                translator_name VARCHAR(255) NULL,
                publisher_name VARCHAR(255) NULL,
                publication_city VARCHAR(160) NULL,
                publication_year SMALLINT UNSIGNED NULL,
                edition_label VARCHAR(120) NULL,
                isbn VARCHAR(40) NULL,
                page_count INT UNSIGNED NULL,
                language VARCHAR(80) NULL,
                description TEXT NULL,
                cover_image_path VARCHAR(255) NULL,
                cover_image_mime_type VARCHAR(120) NULL,
                cover_image_size_bytes BIGINT UNSIGNED NULL,
                pdf_path VARCHAR(255) NOT NULL,
                pdf_mime_type VARCHAR(120) NULL,
                pdf_size_bytes BIGINT UNSIGNED NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_library_books_category
                    FOREIGN KEY (category_id) REFERENCES library_categories(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,
                INDEX idx_library_books_status (status),
                INDEX idx_library_books_category (category_id),
                INDEX idx_library_books_title (title),
                INDEX idx_library_books_author (author_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->pdo->exec(<<<SQL
            INSERT INTO library_categories (slug, name, description, color, is_active)
            VALUES
                ('obras-basicas', 'Obras Básicas', 'Codificação e obras fundamentais para estudo da Doutrina Espírita.', '#c78d24', 1),
                ('evangelho', 'Evangelho', 'Obras de estudo e reflexão evangélica.', '#1f6f5f', 1),
                ('estudo-doutrinario', 'Estudo Doutrinário', 'Livros de apoio ao estudo sistematizado e à formação doutrinária.', '#1f5fa8', 1),
                ('autores-espiritas', 'Autores Espíritas', 'Títulos complementares de autores espíritas e pesquisadores.', '#7b4bb7', 1),
                ('infantil-e-juvenil', 'Infantil e Juvenil', 'Publicações voltadas para infância, adolescência e evangelização.', '#d26b2d', 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                color = VALUES(color),
                is_active = VALUES(is_active)
        SQL);

        $this->ensureColumn(
            'library_books',
            'cover_image_path',
            'ALTER TABLE library_books ADD COLUMN cover_image_path VARCHAR(255) NULL AFTER description'
        );
        $this->ensureColumn(
            'library_books',
            'cover_image_mime_type',
            'ALTER TABLE library_books ADD COLUMN cover_image_mime_type VARCHAR(120) NULL AFTER cover_image_path'
        );
        $this->ensureColumn(
            'library_books',
            'cover_image_size_bytes',
            'ALTER TABLE library_books ADD COLUMN cover_image_size_bytes BIGINT UNSIGNED NULL AFTER cover_image_mime_type'
        );

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

        $exists = (int) $statement->fetchColumn() > 0;

        if (!$exists) {
            $this->pdo->exec($alterSql);
        }
    }
}
