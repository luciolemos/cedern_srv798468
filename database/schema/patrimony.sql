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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_patrimony_assets_code (asset_code),
    INDEX idx_patrimony_assets_name (name),
    INDEX idx_patrimony_assets_category (category_id),
    INDEX idx_patrimony_assets_location (current_location_id),
    INDEX idx_patrimony_assets_status (current_status),
    INDEX idx_patrimony_assets_warranty (warranty_expires_at),
    INDEX idx_patrimony_assets_acquisition_date (acquisition_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    is_active = VALUES(is_active);

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
    sort_order = VALUES(sort_order);
