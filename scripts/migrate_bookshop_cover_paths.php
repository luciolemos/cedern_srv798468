<?php

declare(strict_types=1);

use App\Support\ManagedUploadStorage;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_BOOKSHOP_COVER_DIRECTORY = 'var/storage/bookshop/covers';
const DEFAULT_BOOKSHOP_COVER_PUBLIC_PREFIX = 'media/livraria/capas';
const LEGACY_BOOKSHOP_COVER_DIRECTORY = 'public/assets/img/bookshop-covers';
const LEGACY_BOOKSHOP_COVER_PUBLIC_PREFIX = 'assets/img/bookshop-covers';

$options = parseOptions($argv);

if ($options['help']) {
    renderHelp();
    exit(0);
}

$projectRoot = dirname(__DIR__);
loadEnvironment($projectRoot);

$pdo = createPdoFromEnvironment();
$storage = resolveBookshopStorage($projectRoot);

if ($options['apply'] && !ensureWritableDirectory($storage['target_directory'])) {
    fwrite(
        STDERR,
        sprintf("Diretorio de destino sem escrita para capas da livraria: %s\n", $storage['target_directory'])
    );
    exit(1);
}

$statement = $pdo->query(
    "SELECT id, sku, title, cover_image_path
     FROM bookshop_books
     WHERE cover_image_path LIKE '" . LEGACY_BOOKSHOP_COVER_PUBLIC_PREFIX . "%'
     ORDER BY id ASC"
);

$rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
$summary = createSummary(count($rows));
$notes = [];

if (!$options['apply']) {
    foreach ($rows as $row) {
        inspectLegacyReference($row, $storage, $summary, $notes);
    }

    echo json_encode([
        'mode' => 'dry-run',
        'storage' => summarizeStorage($storage),
        'summary' => $summary,
        'notes' => array_slice($notes, 0, 20),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$update = $pdo->prepare(
    'UPDATE bookshop_books
     SET cover_image_path = :cover_image_path
     WHERE id = :id'
);

$pdo->beginTransaction();

try {
    foreach ($rows as $row) {
        $bookId = (int) ($row['id'] ?? 0);
        if ($bookId <= 0) {
            continue;
        }

        $newRelativePath = migrateLegacyReference($row, $storage, $summary, $notes);
        if ($newRelativePath === null) {
            continue;
        }

        $update->execute([
            'id' => $bookId,
            'cover_image_path' => $newRelativePath,
        ]);
        $summary['updated']++;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Falha ao migrar capas legadas da livraria: {$exception->getMessage()}\n");
    exit(1);
}

echo json_encode([
    'mode' => 'apply',
    'storage' => summarizeStorage($storage),
    'summary' => $summary,
    'notes' => array_slice($notes, 0, 20),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

/**
 * @return array{apply: bool, help: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'apply' => false,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--apply') {
            $options['apply'] = true;
            continue;
        }

        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }

        fwrite(STDERR, "Opcao invalida: {$argument}\n");
        exit(1);
    }

    return $options;
}

function renderHelp(): void
{
    echo "Uso:\n";
    echo "  php scripts/migrate_bookshop_cover_paths.php [--apply]\n\n";
    echo "Comportamento:\n";
    echo "  Sem --apply: analisa livros ainda apontando para assets/img/bookshop-covers.\n";
    echo "  Com --apply: copia as capas legadas para o storage gerenciado e atualiza bookshop_books.cover_image_path para media/livraria/capas.\n";
}

function loadEnvironment(string $projectRoot): void
{
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
        $resolvedEnvFilePath = isAbsolutePath($envFileFromServer)
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
}

function createPdoFromEnvironment(): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($_ENV['DB_HOST'] ?? 'localhost'),
            (int) ($_ENV['DB_PORT'] ?? 3306),
            (string) ($_ENV['DB_NAME'] ?? ''),
            (string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4')
        ),
        (string) ($_ENV['DB_USER'] ?? ''),
        (string) ($_ENV['DB_PASS'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

/**
 * @return array{legacy_prefix: string, managed_prefix: string, source_directory: string, target_directory: string}
 */
function resolveBookshopStorage(string $projectRoot): array
{
    return [
        'legacy_prefix' => LEGACY_BOOKSHOP_COVER_PUBLIC_PREFIX,
        'managed_prefix' => resolveManagedPublicPrefix(
            'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
            DEFAULT_BOOKSHOP_COVER_PUBLIC_PREFIX
        ),
        'source_directory' => resolveProjectPath($projectRoot, LEGACY_BOOKSHOP_COVER_DIRECTORY),
        'target_directory' => resolveManagedDirectory(
            $projectRoot,
            'BOOKSHOP_COVER_UPLOAD_DIR',
            DEFAULT_BOOKSHOP_COVER_DIRECTORY
        ),
    ];
}

/**
 * @return array{found: int, updated: int, copied: int, already_in_target: int, missing_source: int, copy_failed: int}
 */
function createSummary(int $found): array
{
    return [
        'found' => $found,
        'updated' => 0,
        'copied' => 0,
        'already_in_target' => 0,
        'missing_source' => 0,
        'copy_failed' => 0,
    ];
}

/**
 * @param array<string, mixed> $row
 * @param array{legacy_prefix: string, managed_prefix: string, source_directory: string, target_directory: string} $storage
 * @param array{found: int, updated: int, copied: int, already_in_target: int, missing_source: int, copy_failed: int} $summary
 * @param array<int, string> $notes
 */
function inspectLegacyReference(array $row, array $storage, array &$summary, array &$notes): void
{
    $relativePath = ltrim((string) ($row['cover_image_path'] ?? ''), '/');
    $fileName = basename($relativePath);

    if ($fileName === '') {
        $summary['missing_source']++;
        $notes[] = sprintf(
            'Livro %s (%s): caminho legado invalido (%s)',
            (string) ($row['sku'] ?? 'sem-sku'),
            (string) ($row['title'] ?? 'sem-titulo'),
            $relativePath
        );
        return;
    }

    $targetPath = $storage['target_directory'] . '/' . $fileName;
    if (is_file($targetPath)) {
        $summary['already_in_target']++;
        return;
    }

    $sourcePath = $storage['source_directory'] . '/' . $fileName;
    if (!is_file($sourcePath) || !is_readable($sourcePath)) {
        $summary['missing_source']++;
        $notes[] = sprintf(
            'Livro %s (%s): arquivo legado nao encontrado para %s',
            (string) ($row['sku'] ?? 'sem-sku'),
            (string) ($row['title'] ?? 'sem-titulo'),
            $relativePath
        );
        return;
    }

    $summary['copied']++;
}

/**
 * @param array<string, mixed> $row
 * @param array{legacy_prefix: string, managed_prefix: string, source_directory: string, target_directory: string} $storage
 * @param array{found: int, updated: int, copied: int, already_in_target: int, missing_source: int, copy_failed: int} $summary
 * @param array<int, string> $notes
 */
function migrateLegacyReference(array $row, array $storage, array &$summary, array &$notes): ?string
{
    $relativePath = ltrim((string) ($row['cover_image_path'] ?? ''), '/');
    $fileName = basename($relativePath);

    if ($fileName === '') {
        $summary['missing_source']++;
        $notes[] = sprintf(
            'Livro %s (%s): caminho legado invalido (%s)',
            (string) ($row['sku'] ?? 'sem-sku'),
            (string) ($row['title'] ?? 'sem-titulo'),
            $relativePath
        );
        return null;
    }

    $targetPath = $storage['target_directory'] . '/' . $fileName;
    $newRelativePath = trim($storage['managed_prefix'], '/') . '/' . $fileName;

    if (is_file($targetPath)) {
        $summary['already_in_target']++;
        return $newRelativePath;
    }

    $sourcePath = $storage['source_directory'] . '/' . $fileName;
    if (!is_file($sourcePath) || !is_readable($sourcePath)) {
        $summary['missing_source']++;
        $notes[] = sprintf(
            'Livro %s (%s): arquivo legado nao encontrado para %s',
            (string) ($row['sku'] ?? 'sem-sku'),
            (string) ($row['title'] ?? 'sem-titulo'),
            $relativePath
        );
        return null;
    }

    if (!@copy($sourcePath, $targetPath)) {
        $summary['copy_failed']++;
        $notes[] = sprintf(
            'Livro %s (%s): falha ao copiar %s para %s',
            (string) ($row['sku'] ?? 'sem-sku'),
            (string) ($row['title'] ?? 'sem-titulo'),
            $sourcePath,
            $targetPath
        );
        return null;
    }

    @chmod($targetPath, 0664);
    $summary['copied']++;

    return $newRelativePath;
}

/**
 * @param array{legacy_prefix: string, managed_prefix: string, source_directory: string, target_directory: string} $storage
 * @return array{legacy_prefix: string, managed_prefix: string, source_directory: string, target_directory: string}
 */
function summarizeStorage(array $storage): array
{
    return $storage;
}

function resolveManagedDirectory(string $projectRoot, string $envKey, string $defaultDirectory): string
{
    return managedUploadStorage($projectRoot)->resolveUploadDirectory($envKey, $defaultDirectory);
}

function resolveManagedPublicPrefix(string $envKey, string $defaultPublicPrefix): string
{
    return managedUploadStorage(dirname(__DIR__))->resolveUploadPublicPrefix($envKey, $defaultPublicPrefix);
}

function resolveManagedStorageDefaultDirectory(string $projectRoot, string $defaultDirectory): string
{
    return managedUploadStorage($projectRoot)->resolveManagedStorageDefaultDirectory($defaultDirectory);
}

function resolveConfiguredManagedDirectory(string $projectRoot, string $path): string
{
    return managedUploadStorage($projectRoot)->resolveManagedStorageDirectory($path);
}

function resolveProjectPath(string $projectRoot, string $path): string
{
    return managedUploadStorage($projectRoot)->resolveProjectPath($path);
}

function managedUploadStorage(string $projectRoot): ManagedUploadStorage
{
    static $instances = [];

    if (!isset($instances[$projectRoot])) {
        $instances[$projectRoot] = new ManagedUploadStorage($projectRoot, $_ENV);
    }

    return $instances[$projectRoot];
}

function ensureWritableDirectory(string $directory): bool
{
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    if (is_writable($directory)) {
        return true;
    }

    @chmod($directory, 0775);
    clearstatcache(true, $directory);

    return is_writable($directory);
}

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, '/')
        || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
}
