<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
$envFileFromServer = '';
$appEnvFileFromGetenv = getenv('APP_ENV_FILE');
if ($appEnvFileFromGetenv !== false) {
    $envFileFromServer = trim((string) $appEnvFileFromGetenv);
} elseif (isset($_SERVER['APP_ENV_FILE'])) {
    $envFileFromServer = trim((string) $_SERVER['APP_ENV_FILE']);
} elseif (isset($_ENV['APP_ENV_FILE'])) {
    $envFileFromServer = trim((string) $_ENV['APP_ENV_FILE']);
}

$dotenvLoaded = false;
if ($envFileFromServer !== '') {
    $resolvedEnvFilePath = str_starts_with($envFileFromServer, '/')
        ? $envFileFromServer
        : $projectRoot . '/' . ltrim($envFileFromServer, '/');

    if (is_file($resolvedEnvFilePath)) {
        Dotenv::createImmutable(dirname($resolvedEnvFilePath), basename($resolvedEnvFilePath))->safeLoad();
        $dotenvLoaded = true;
    }
}

if (!$dotenvLoaded && is_file($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    (string) ($_ENV['DB_HOST'] ?? 'localhost'),
    (string) ($_ENV['DB_PORT'] ?? '3306'),
    (string) ($_ENV['DB_NAME'] ?? ''),
    (string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4')
);

$pdo = new PDO(
    $dsn,
    (string) ($_ENV['DB_USER'] ?? ''),
    (string) ($_ENV['DB_PASS'] ?? ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

function resolveProjectPath(string $projectRoot, string $path): string
{
    $normalizedPath = str_replace('\\', '/', trim($path));
    if ($normalizedPath === '') {
        return $projectRoot;
    }

    if (str_starts_with($normalizedPath, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $normalizedPath) === 1) {
        return rtrim($normalizedPath, '/');
    }

    return rtrim($projectRoot . '/' . ltrim($normalizedPath, '/'), '/');
}

function resolveManagedStorageDefaultDirectory(string $projectRoot, string $defaultDirectory): string
{
    $managedStorageRoot = trim((string) ($_ENV['APP_MANAGED_STORAGE_ROOT'] ?? ''));
    if ($managedStorageRoot === '') {
        return resolveProjectPath($projectRoot, $defaultDirectory);
    }

    $resolvedRoot = resolveProjectPath($projectRoot, $managedStorageRoot);
    $normalizedDefaultDirectory = ltrim(str_replace('\\', '/', $defaultDirectory), '/');
    $storagePrefix = 'var/storage/';

    if (!str_starts_with($normalizedDefaultDirectory, $storagePrefix)) {
        return resolveProjectPath($projectRoot, $defaultDirectory);
    }

    return $resolvedRoot . '/' . ltrim(substr($normalizedDefaultDirectory, strlen($storagePrefix)), '/');
}

function resolveManagedDirectory(string $projectRoot, string $envKey, string $defaultDirectory): string
{
    $configuredDirectory = trim((string) ($_ENV[$envKey] ?? ''));

    if ($configuredDirectory !== '') {
        return resolveConfiguredManagedDirectory($projectRoot, $configuredDirectory);
    }

    return resolveManagedStorageDefaultDirectory($projectRoot, $defaultDirectory);
}

function resolveConfiguredManagedDirectory(string $projectRoot, string $path): string
{
    $normalizedPath = str_replace('\\', '/', trim($path));
    while (str_starts_with($normalizedPath, './')) {
        $normalizedPath = substr($normalizedPath, 2);
    }

    $managedStorageRoot = trim((string) ($_ENV['APP_MANAGED_STORAGE_ROOT'] ?? ''));
    $storagePrefix = 'var/storage/';
    $normalizedRelativePath = ltrim($normalizedPath, '/');

    if (
        $managedStorageRoot !== ''
        && !str_starts_with($normalizedPath, '/')
        && preg_match('/^[A-Za-z]:[\\\\\\/]/', $normalizedPath) !== 1
        && str_starts_with($normalizedRelativePath, $storagePrefix)
    ) {
        $resolvedRoot = resolveProjectPath($projectRoot, $managedStorageRoot);

        return $resolvedRoot . '/' . ltrim(substr($normalizedRelativePath, strlen($storagePrefix)), '/');
    }

    return resolveProjectPath($projectRoot, $normalizedPath);
}

/**
 * @return array<int, array{label: string, table: string, id_column: string, title_column: string, path_column: string, prefix: string, directory: string}>
 */
function managedStorageChecks(string $projectRoot): array
{
    return [
        [
            'label' => 'Livraria / capas',
            'table' => 'bookshop_books',
            'id_column' => 'id',
            'title_column' => 'title',
            'path_column' => 'cover_image_path',
            'prefix' => 'media/livraria/capas/',
            'directory' => resolveManagedDirectory($projectRoot, 'BOOKSHOP_COVER_UPLOAD_DIR', 'var/storage/bookshop/covers'),
        ],
        [
            'label' => 'Biblioteca / PDFs',
            'table' => 'library_books',
            'id_column' => 'id',
            'title_column' => 'title',
            'path_column' => 'pdf_path',
            'prefix' => 'media/biblioteca/docs/',
            'directory' => resolveManagedDirectory($projectRoot, 'LIBRARY_UPLOAD_DIR', 'var/storage/library/docs'),
        ],
        [
            'label' => 'Biblioteca / capas',
            'table' => 'library_books',
            'id_column' => 'id',
            'title_column' => 'title',
            'path_column' => 'cover_image_path',
            'prefix' => 'media/biblioteca/capas/',
            'directory' => resolveManagedDirectory($projectRoot, 'LIBRARY_COVER_UPLOAD_DIR', 'var/storage/library/covers'),
        ],
        [
            'label' => 'Membros / fotos',
            'table' => 'member_users',
            'id_column' => 'id',
            'title_column' => 'full_name',
            'path_column' => 'profile_photo_path',
            'prefix' => 'media/membros/fotos/',
            'directory' => resolveManagedDirectory($projectRoot, 'MEMBER_PROFILE_PHOTO_UPLOAD_DIR', 'var/storage/member-photos'),
        ],
        [
            'label' => 'Patrimonio / documentos de compra',
            'table' => 'patrimony_assets',
            'id_column' => 'id',
            'title_column' => 'name',
            'path_column' => 'purchase_document_path',
            'prefix' => 'media/patrimonio/docs/',
            'directory' => resolveManagedDirectory($projectRoot, 'PATRIMONY_DOCUMENT_UPLOAD_DIR', 'var/storage/patrimony/docs'),
        ],
        [
            'label' => 'Patrimonio / imagens',
            'table' => 'patrimony_assets',
            'id_column' => 'id',
            'title_column' => 'name',
            'path_column' => 'main_photo_path',
            'prefix' => 'media/patrimonio/img/',
            'directory' => resolveManagedDirectory($projectRoot, 'PATRIMONY_IMAGE_UPLOAD_DIR', 'var/storage/patrimony/img'),
        ],
    ];
}

$exitCode = 0;

foreach (managedStorageChecks($projectRoot) as $check) {
    echo PHP_EOL . '== ' . $check['label'] . ' ==' . PHP_EOL;
    echo 'Diretorio resolvido: ' . $check['directory'] . PHP_EOL;

    if (!is_dir($check['directory'])) {
        echo 'Status do diretorio: ausente' . PHP_EOL;
        $exitCode = 1;
    } else {
        echo 'Status do diretorio: presente' . PHP_EOL;
    }

    $sql = sprintf(
        'SELECT %s AS record_id, %s AS record_title, %s AS record_path
         FROM %s
         WHERE %s LIKE :prefix
         ORDER BY %s',
        $check['id_column'],
        $check['title_column'],
        $check['path_column'],
        $check['table'],
        $check['path_column'],
        $check['id_column']
    );

    $statement = $pdo->prepare($sql);
    $statement->execute([
        ':prefix' => $check['prefix'] . '%',
    ]);

    $rows = $statement->fetchAll();
    echo 'Registros gerenciados: ' . count($rows) . PHP_EOL;

    $missingRows = [];

    foreach ($rows as $row) {
        $fileName = basename((string) $row['record_path']);
        $absolutePath = rtrim($check['directory'], '/') . '/' . $fileName;

        if (!is_file($absolutePath)) {
            $missingRows[] = [
                'id' => (string) $row['record_id'],
                'title' => (string) $row['record_title'],
                'path' => (string) $row['record_path'],
            ];
        }
    }

    echo 'Arquivos ausentes: ' . count($missingRows) . PHP_EOL;

    foreach (array_slice($missingRows, 0, 20) as $missingRow) {
        echo ' - #' . $missingRow['id'] . ' | ' . $missingRow['title'] . ' | ' . $missingRow['path'] . PHP_EOL;
    }

    if (count($missingRows) > 20) {
        echo ' - ... e mais ' . (count($missingRows) - 20) . ' registro(s)' . PHP_EOL;
    }

    if ($missingRows !== []) {
        $exitCode = 1;
    }
}

exit($exitCode);
