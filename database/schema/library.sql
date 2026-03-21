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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    is_active = VALUES(is_active);
