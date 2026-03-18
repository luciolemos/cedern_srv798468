CREATE TABLE IF NOT EXISTS activity_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    color VARCHAR(20) NULL,
    icon VARCHAR(80) NULL,
    audience_default VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(160) NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id BIGINT UNSIGNED NULL,
    status ENUM('pending', 'active', 'blocked') NOT NULL DEFAULT 'pending',
    phone_mobile VARCHAR(30) NULL,
    phone_landline VARCHAR(30) NULL,
    birth_date DATE NULL,
    birth_place VARCHAR(140) NULL,
    institutional_role VARCHAR(120) NULL,
    member_type VARCHAR(20) NULL,
    profile_photo_path VARCHAR(255) NULL,
    privacy_notice_version VARCHAR(40) NULL,
    privacy_notice_accepted_at DATETIME NULL,
    profile_completed TINYINT(1) NOT NULL DEFAULT 0,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_member_users_status (status),
    INDEX idx_member_users_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_visit_daily (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(190) NOT NULL,
    visit_date DATE NOT NULL,
    page_views INT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_page_visit_daily (page_key, visit_date),
    KEY idx_page_visit_daily_date (visit_date),
    KEY idx_page_visit_daily_page (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_visit_uniques (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(190) NOT NULL,
    visit_date DATE NOT NULL,
    visitor_token_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_page_visit_uniques (page_key, visit_date, visitor_token_hash),
    KEY idx_page_visit_uniques_date (visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_visit_baselines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    started_at DATETIME NOT NULL,
    created_by_member_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_page_visit_baselines_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS institutional_managements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    starts_at DATE NULL,
    ends_at DATE NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_management_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    management_id BIGINT UNSIGNED NOT NULL,
    member_user_id BIGINT UNSIGNED NOT NULL,
    role_name VARCHAR(120) NOT NULL,
    starts_at DATE NULL,
    ends_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_management_unique_member (management_id, member_user_id),
    KEY idx_member_management_role_name (management_id, role_name),
    CONSTRAINT fk_member_management_roles_management
        FOREIGN KEY (management_id) REFERENCES institutional_managements(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_member_management_roles_member
        FOREIGN KEY (member_user_id) REFERENCES member_users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS institutional_contents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    updated_by_member_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agenda_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    theme VARCHAR(180) NULL,
    location_name VARCHAR(180) NULL,
    location_address VARCHAR(255) NULL,
    mode ENUM('presencial', 'online', 'hibrido') NOT NULL DEFAULT 'presencial',
    meeting_url VARCHAR(255) NULL,
    audience ENUM('Jovens', 'Adultos', 'Crianças', 'Público interno', 'Livre') NOT NULL DEFAULT 'Livre',
    notes TEXT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    status ENUM('draft', 'published', 'cancelled') NOT NULL DEFAULT 'published',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_agenda_events_category
        FOREIGN KEY (category_id) REFERENCES activity_categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    INDEX idx_agenda_events_starts_at (starts_at),
    INDEX idx_agenda_events_status (status),
    INDEX idx_agenda_events_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE agenda_events
SET audience = 'Livre'
WHERE audience IS NULL OR TRIM(audience) = '';

UPDATE agenda_events
SET audience = 'Livre'
WHERE audience IN ('Público geral', 'Publico geral');

UPDATE agenda_events
SET audience = 'Jovens'
WHERE audience LIKE 'Jovens%';

UPDATE agenda_events
SET audience = 'Adultos'
WHERE audience LIKE 'Adultos%';

UPDATE agenda_events
SET audience = 'Crianças'
WHERE audience LIKE 'Crian%';

UPDATE agenda_events
SET audience = 'Público interno'
WHERE audience LIKE 'Público interno%' OR audience LIKE 'Publico interno%';

ALTER TABLE agenda_events
    MODIFY COLUMN audience ENUM('Jovens', 'Adultos', 'Crianças', 'Público interno', 'Livre') NOT NULL DEFAULT 'Livre';

INSERT INTO activity_categories (slug, name, color, audience_default)
VALUES
    ('estudo', 'Estudo', '#2563eb', 'Adultos'),
    ('palestra', 'Palestra', '#d97706', 'Público geral'),
    ('juventude', 'Juventude', '#16a34a', 'Jovens'),
    ('campanha', 'Campanha', '#dc2626', 'Público geral'),
    ('curso', 'Curso', '#7c3aed', 'Adultos e jovens'),
    ('simposio', 'Simpósio', '#0ea5e9', 'Público geral'),
    ('seminario', 'Seminário', '#9333ea', 'Público geral'),
    ('estagio', 'Estágio', '#f59e0b', 'Trabalhadores e colaboradores'),
    ('outros', 'Outros', '#64748b', 'Público geral')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    color = VALUES(color),
    audience_default = VALUES(audience_default);

INSERT INTO roles (role_key, name, description)
VALUES
    ('member', 'Membro', 'Acesso à área de membro e recursos básicos.'),
    ('operator', 'Operador', 'Operação de funcionalidades internas específicas.'),
    ('manager', 'Gerente', 'Coordenação de conteúdos e fluxos internos.'),
    ('admin', 'Administrador', 'Gestão completa de usuários e permissões.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

INSERT INTO institutional_managements (name, starts_at, is_current)
SELECT 'Gestão Atual', CURRENT_DATE, 1
WHERE NOT EXISTS (
    SELECT 1 FROM institutional_managements WHERE is_current = 1
);

INSERT INTO agenda_events (
    category_id,
    slug,
    title,
    description,
    theme,
    location_name,
    location_address,
    mode,
    meeting_url,
    audience,
    notes,
    starts_at,
    ends_at,
    status,
    is_featured
)
VALUES
    (
        (SELECT id FROM activity_categories WHERE slug = 'estudo' LIMIT 1),
        'estudo-do-evangelho-segunda-2026-03-16',
        'Estudo do Evangelho',
        'Reflexões sobre os ensinamentos morais de Jesus à luz do Espiritismo.',
        'A prática do Evangelho no cotidiano familiar',
        'CEDE - Sala de Estudos',
        'Rua Exemplo, 123 - Natal/RN',
        'presencial',
        NULL,
        'Adultos',
        'Chegue 15 minutos antes para acolhimento inicial.',
        '2026-03-16 20:00:00',
        '2026-03-16 21:30:00',
        'published',
        1
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'palestra' LIMIT 1),
        'palestra-publica-quarta-2026-03-18',
        'Palestra Pública',
        'Exposição doutrinária seguida de passes magnéticos para os participantes.',
        'Esperança e renovação espiritual',
        'CEDE - Auditório Principal',
        'Rua Exemplo, 123 - Natal/RN',
        'presencial',
        NULL,
        'Livre',
        'Aberta a visitantes. Atendimento fraterno após a palestra.',
        '2026-03-18 19:30:00',
        '2026-03-18 21:00:00',
        'published',
        1
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'juventude' LIMIT 1),
        'juventude-espirita-sabado-2026-03-21',
        'Juventude Espírita',
        'Encontro de estudo, diálogo e dinâmicas para jovens.',
        'Autoconhecimento e projeto de vida',
        'CEDE - Espaço Jovem',
        'Rua Exemplo, 123 - Natal/RN',
        'hibrido',
        'https://meet.exemplo.org/juventude-cede',
        'Público interno',
        'Responsáveis são bem-vindos no acolhimento inicial.',
        '2026-03-21 16:00:00',
        '2026-03-21 17:30:00',
        'published',
        0
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'estudo' LIMIT 1),
        'estudo-do-evangelho-segunda-2026-03-23',
        'Estudo do Evangelho',
        'Estudo dialogado com leitura comentada e participação do grupo.',
        'Caridade e transformação íntima',
        'CEDE - Sala de Estudos',
        'Rua Exemplo, 123 - Natal/RN',
        'presencial',
        NULL,
        'Adultos',
        'Leve seu caderno para anotações e dúvidas.',
        '2026-03-23 20:00:00',
        '2026-03-23 21:30:00',
        'published',
        0
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'palestra' LIMIT 1),
        'palestra-publica-quarta-2026-03-25',
        'Palestra Pública',
        'Momento de estudo doutrinário e acolhimento fraterno.',
        'Fé raciocinada e vida prática',
        'CEDE - Auditório Principal',
        'Rua Exemplo, 123 - Natal/RN',
        'presencial',
        NULL,
        'Livre',
        'Haverá atendimento fraterno após a atividade.',
        '2026-03-25 19:30:00',
        '2026-03-25 21:00:00',
        'published',
        0
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'juventude' LIMIT 1),
        'juventude-espirita-sabado-2026-03-28',
        'Juventude Espírita',
        'Vivência em grupo com estudo e dinâmica de integração.',
        'Amizade, propósito e serviço',
        'CEDE - Espaço Jovem',
        'Rua Exemplo, 123 - Natal/RN',
        'hibrido',
        'https://meet.exemplo.org/juventude-cede-20260328',
        'Crianças',
        'Encontro com atividade prática colaborativa.',
        '2026-03-28 16:00:00',
        '2026-03-28 17:30:00',
        'published',
        1
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'estudo' LIMIT 1),
        'estudo-do-evangelho-segunda-2026-03-30',
        'Estudo do Evangelho',
        'Roda de leitura, reflexão e aplicação prática dos conteúdos.',
        'Perdão e reconciliação',
        'CEDE - Sala de Estudos',
        'Rua Exemplo, 123 - Natal/RN',
        'presencial',
        NULL,
        'Adultos',
        'Recepção a partir das 19h45.',
        '2026-03-30 20:00:00',
        '2026-03-30 21:30:00',
        'published',
        0
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'palestra' LIMIT 1),
        'palestra-publica-quarta-2026-04-01',
        'Palestra Pública',
        'Palestra aberta com tema doutrinário e momento de prece.',
        'Consolo e esperança',
        'CEDE - Auditório Principal',
        'Rua Exemplo, 123 - Natal/RN',
        'presencial',
        NULL,
        'Livre',
        'Chegue cedo para melhor acomodação.',
        '2026-04-01 19:30:00',
        '2026-04-01 21:00:00',
        'published',
        0
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'juventude' LIMIT 1),
        'juventude-espirita-sabado-2026-04-04',
        'Juventude Espírita',
        'Encontro de estudo e convivência para jovens participantes.',
        'Autocuidado e espiritualidade',
        'CEDE - Espaço Jovem',
        'Rua Exemplo, 123 - Natal/RN',
        'online',
        'https://meet.exemplo.org/juventude-cede-20260404',
        'Jovens',
        'Link disponível no grupo institucional.',
        '2026-04-04 16:00:00',
        '2026-04-04 17:30:00',
        'published',
        0
    ),
    (
        (SELECT id FROM activity_categories WHERE slug = 'estudo' LIMIT 1),
        'estudo-do-evangelho-segunda-2026-04-06',
        'Estudo do Evangelho',
        'Continuidade do ciclo semanal de estudo e debate fraterno.',
        'Família e educação moral',
        'CEDE - Sala de Estudos',
        'Rua Exemplo, 123 - Natal/RN',
        'presencial',
        NULL,
        'Adultos',
        'Atividade com abertura para perguntas ao final.',
        '2026-04-06 20:00:00',
        '2026-04-06 21:30:00',
        'published',
        0
    )
ON DUPLICATE KEY UPDATE
    category_id = VALUES(category_id),
    title = VALUES(title),
    description = VALUES(description),
    theme = VALUES(theme),
    location_name = VALUES(location_name),
    location_address = VALUES(location_address),
    mode = VALUES(mode),
    meeting_url = VALUES(meeting_url),
    audience = VALUES(audience),
    notes = VALUES(notes),
    starts_at = VALUES(starts_at),
    ends_at = VALUES(ends_at),
    status = VALUES(status),
    is_featured = VALUES(is_featured);
