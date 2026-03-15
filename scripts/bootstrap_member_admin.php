<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);
Dotenv::createImmutable($projectRoot)->safeLoad();

$email = strtolower(trim((string) ($argv[1] ?? 'admin@exemplo.com')));
$password = (string) ($argv[2] ?? 'Cede@2026!Temp');
$fullName = trim((string) ($argv[3] ?? 'Administrador CEDE'));

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "A senha deve ter ao menos 8 caracteres.\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    (string) ($_ENV['DB_HOST'] ?? ''),
    (string) ($_ENV['DB_PORT'] ?? 3306),
    (string) ($_ENV['DB_NAME'] ?? ''),
    (string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4')
);

$pdo = new PDO($dsn, (string) ($_ENV['DB_USER'] ?? ''), (string) ($_ENV['DB_PASS'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS member_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(160) NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    phone_mobile VARCHAR(30) NULL,
    phone_landline VARCHAR(30) NULL,
    birth_date DATE NULL,
    birth_place VARCHAR(140) NULL,
    profile_photo_path VARCHAR(255) NULL,
    profile_completed TINYINT(1) NOT NULL DEFAULT 0,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_member_users_role_id (role_id),
    CONSTRAINT fk_member_users_role_id
      FOREIGN KEY (role_id) REFERENCES roles(id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$pdo->exec(<<<SQL
INSERT INTO roles (role_key, name, description)
VALUES
    ('member', 'Membro', 'Acesso básico.'),
    ('operator', 'Operador', 'Acesso operacional.'),
    ('manager', 'Gerente', 'Acesso de gestão.'),
    ('admin', 'Administrador', 'Acesso administrativo completo.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description)
SQL);

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$sql = <<<SQL
INSERT INTO member_users (
    full_name,
    email,
    password_hash,
    role_id,
    status,
    profile_completed,
    approved_at
)
SELECT
    :full_name,
    :email,
    :password_hash,
    r.id,
    'active',
    1,
    NOW()
FROM roles r
WHERE r.role_key = 'admin'
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    role_id = VALUES(role_id),
    status = 'active',
    profile_completed = 1,
    approved_at = COALESCE(member_users.approved_at, VALUES(approved_at))
SQL;

$statement = $pdo->prepare($sql);
$statement->execute([
    'full_name' => $fullName,
    'email' => $email,
    'password_hash' => $passwordHash,
]);

echo "Bootstrap concluído com sucesso.\n";
echo "E-mail admin: {$email}\n";
echo "Senha admin: {$password}\n";
