<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root . '/.env';

if (!is_file($envPath)) {
    fwrite(STDERR, ".env não encontrado em {$envPath}\n");
    exit(1);
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
    $key = trim($key);
    $value = trim($value);

    if (
        $value !== ''
        && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === '\'' && substr($value, -1) === '\''))
    ) {
        $value = substr($value, 1, -1);
    }

    $env[$key] = $value;
}

$oldPrefix = strtoupper(trim((string) ($argv[1] ?? 'WEB-LIV-')));
$newPrefix = strtoupper(trim((string) ($argv[2] ?? 'CEDE-LIV-')));

if ($oldPrefix === '' || $newPrefix === '') {
    fwrite(STDERR, "Informe prefixos válidos. Ex.: php scripts/migrate_bookshop_sku_prefix.php WEB-LIV- CEDE-LIV-\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) ($env['DB_HOST'] ?? ''),
            (int) ($env['DB_PORT'] ?? 3306),
            (string) ($env['DB_NAME'] ?? '')
        ),
        (string) ($env['DB_USER'] ?? ''),
        (string) ($env['DB_PASS'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $statement = $pdo->prepare('SELECT COUNT(*) FROM bookshop_books WHERE sku LIKE :pattern');
    $statement->execute(['pattern' => $oldPrefix . '%']);
    $before = (int) $statement->fetchColumn();

    $update = $pdo->prepare(
        'UPDATE bookshop_books
         SET sku = REPLACE(sku, :old_prefix, :new_prefix)
         WHERE sku LIKE :pattern'
    );
    $update->execute([
        'old_prefix' => $oldPrefix,
        'new_prefix' => $newPrefix,
        'pattern' => $oldPrefix . '%',
    ]);

    $statement->execute(['pattern' => $oldPrefix . '%']);
    $after = (int) $statement->fetchColumn();

    $sample = $pdo->prepare('SELECT sku FROM bookshop_books WHERE sku LIKE :pattern ORDER BY id ASC LIMIT 5');
    $sample->execute(['pattern' => $newPrefix . '%']);

    echo json_encode([
        'updated_from_prefix' => $oldPrefix,
        'updated_to_prefix' => $newPrefix,
        'before' => $before,
        'after' => $after,
        'sample' => $sample->fetchAll(PDO::FETCH_COLUMN),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}