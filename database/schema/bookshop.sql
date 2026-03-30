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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookshop_genres (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    color VARCHAR(20) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bookshop_genres_name (name),
    INDEX idx_bookshop_genres_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    created_by_member_id BIGINT UNSIGNED NULL,
    created_by_name VARCHAR(160) NULL,
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
    INDEX idx_bookshop_books_stock (stock_quantity),
    INDEX idx_bookshop_books_created_by (created_by_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_bookshop_stock_movements_lot (stock_lot_id),
    INDEX idx_bookshop_stock_movements_type (movement_type),
    INDEX idx_bookshop_stock_movements_occurred_at (occurred_at),
    INDEX idx_bookshop_stock_movements_created_by (created_by_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
