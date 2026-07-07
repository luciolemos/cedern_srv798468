<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_LIBRARY_PDF_DIRECTORY = 'var/storage/library/docs';
const DEFAULT_LIBRARY_PDF_PUBLIC_PREFIX = 'media/biblioteca/docs';
const DEFAULT_LIBRARY_COVER_DIRECTORY = 'var/storage/library/covers';
const DEFAULT_LIBRARY_COVER_PUBLIC_PREFIX = 'media/biblioteca/capas';
const LEGACY_LIBRARY_PDF_DIRECTORY = 'public/assets/docs/library';
const LEGACY_LIBRARY_PDF_PUBLIC_PREFIX = 'assets/docs/library';
const LEGACY_LIBRARY_COVER_DIRECTORY = 'public/assets/img/library-covers';
const LEGACY_LIBRARY_COVER_PUBLIC_PREFIX = 'assets/img/library-covers';

$options = parseOptions($argv);

if ($options['help']) {
    renderHelp();
    exit(0);
}

$projectRoot = dirname(__DIR__);
loadEnvironment($projectRoot);

$pdo = createPdoFromEnvironment();
$storages = resolveLibraryStorages($projectRoot);

if ($options['apply']) {
    foreach ($storages as $storage) {
        if (!ensureWritableDirectory($storage['target_directory'])) {
            fwrite(
                STDERR,
                sprintf(
                    "Diretorio de destino sem escrita para %s: %s\n",
                    $storage['label'],
                    $storage['target_directory']
                )
            );
            exit(1);
        }
    }
}

$statement = $pdo->query(
    "SELECT id, title, pdf_path, cover_image_path
     FROM library_books
     WHERE pdf_path LIKE '" . LEGACY_LIBRARY_PDF_PUBLIC_PREFIX . "%'
        OR cover_image_path LIKE '" . LEGACY_LIBRARY_COVER_PUBLIC_PREFIX . "%'
     ORDER BY id ASC"
);

$rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
$summary = [
    'rows_found' => count($rows),
    'rows_updated' => 0,
    'pdf' => createBucketSummary(),
    'cover' => createBucketSummary(),
];
$notes = [];

if (!$options['apply']) {
    foreach ($rows as $row) {
        foreach ($storages as $bucketKey => $storage) {
            inspectLegacyReference($row, $storage, $summary[$bucketKey], $notes);
        }
    }

    echo json_encode([
        'mode' => 'dry-run',
        'storages' => summarizeStorages($storages),
        'summary' => $summary,
        'notes' => array_slice($notes, 0, 20),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$update = $pdo->prepare(
    'UPDATE library_books
     SET pdf_path = :pdf_path,
         cover_image_path = :cover_image_path
     WHERE id = :id'
);

$pdo->beginTransaction();

try {
    foreach ($rows as $row) {
        $bookId = (int) ($row['id'] ?? 0);
        if ($bookId <= 0) {
            continue;
        }

        $updatedFields = [];

        foreach ($storages as $bucketKey => $storage) {
            $newRelativePath = migrateLegacyReference($row, $storage, $summary[$bucketKey], $notes);
            if ($newRelativePath !== null) {
                $updatedFields[$storage['column']] = $newRelativePath;
            }
        }

        if ($updatedFields === []) {
            continue;
        }

        $update->execute([
            'id' => $bookId,
            'pdf_path' => $updatedFields['pdf_path'] ?? (string) ($row['pdf_path'] ?? ''),
            'cover_image_path' => array_key_exists('cover_image_path', $updatedFields)
                ? $updatedFields['cover_image_path']
                : nullableString($row['cover_image_path'] ?? null),
        ]);
        $summary['rows_updated']++;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Falha ao migrar arquivos legados da biblioteca: {$exception->getMessage()}\n");
    exit(1);
}

echo json_encode([
    'mode' => 'apply',
    'storages' => summarizeStorages($storages),
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
    echo "  php scripts/migrate_library_storage_paths.php [--apply]\n\n";
    echo "Comportamento:\n";
    echo "  Sem --apply: analisa livros ainda apontando para assets/docs/library e assets/img/library-covers.\n";
    echo "  Com --apply: copia PDFs/capas legados para o storage gerenciado configurado e atualiza a tabela library_books.\n";
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
 * @return array{
 *   pdf: array{label: string, column: string, legacy_prefix: string, managed_prefix: string, target_directory: string, source_directory: string},
 *   cover: array{label: string, column: string, legacy_prefix: string, managed_prefix: string, target_directory: string, source_directory: string}
 * }
 */
function resolveLibraryStorages(string $projectRoot): array
{
    return [
        'pdf' => [
            'label' => 'PDFs da biblioteca',
            'column' => 'pdf_path',
            'legacy_prefix' => LEGACY_LIBRARY_PDF_PUBLIC_PREFIX,
            'managed_prefix' => resolveManagedPublicPrefix('LIBRARY_UPLOAD_PUBLIC_PREFIX', DEFAULT_LIBRARY_PDF_PUBLIC_PREFIX),
            'target_directory' => resolveManagedDirectory($projectRoot, 'LIBRARY_UPLOAD_DIR', DEFAULT_LIBRARY_PDF_DIRECTORY),
            'source_directory' => resolveProjectPath($projectRoot, LEGACY_LIBRARY_PDF_DIRECTORY),
        ],
        'cover' => [
            'label' => 'capas da biblioteca',
            'column' => 'cover_image_path',
            'legacy_prefix' => LEGACY_LIBRARY_COVER_PUBLIC_PREFIX,
            'managed_prefix' => resolveManagedPublicPrefix('LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX', DEFAULT_LIBRARY_COVER_PUBLIC_PREFIX),
            'target_directory' => resolveManagedDirectory($projectRoot, 'LIBRARY_COVER_UPLOAD_DIR', DEFAULT_LIBRARY_COVER_DIRECTORY),
            'source_directory' => resolveProjectPath($projectRoot, LEGACY_LIBRARY_COVER_DIRECTORY),
        ],
    ];
}

/**
 * @param array<string, mixed> $row
 * @param array{label: string, column: string, legacy_prefix: string, managed_prefix: string, target_directory: string, source_directory: string} $storage
 * @param array{found: int, updated: int, copied: int, already_in_target: int, missing_source: int, copy_failed: int} $summary
 * @param array<int, string> $notes
 */
function inspectLegacyReference(array $row, array $storage, array &$summary, array &$notes): void
{
    $relativePath = ltrim((string) ($row[$storage['column']] ?? ''), '/');
    if ($relativePath === '' || !str_starts_with($relativePath, $storage['legacy_prefix'] . '/')) {
        return;
    }

    $summary['found']++;
    $fileName = basename($relativePath);
    $targetPath = $storage['target_directory'] . '/' . $fileName;

    if ($fileName === '') {
        $summary['missing_source']++;
        $notes[] = sprintf(
            'Livro %s: caminho legado invalido em %s (%s)',
            (string) ($row['title'] ?? 'sem-titulo'),
            $storage['column'],
            $relativePath
        );
        return;
    }

    if (is_file($targetPath)) {
        $summary['already_in_target']++;
        return;
    }

    $sourcePath = $storage['source_directory'] . '/' . $fileName;

    if (!is_file($sourcePath) || !is_readable($sourcePath)) {
        $summary['missing_source']++;
        $notes[] = sprintf(
            'Livro %s: arquivo legado nao encontrado para %s',
            (string) ($row['title'] ?? 'sem-titulo'),
            $relativePath
        );
        return;
    }

    $summary['copied']++;
}

/**
 * @param array<string, mixed> $row
 * @param array{label: string, column: string, legacy_prefix: string, managed_prefix: string, target_directory: string, source_directory: string} $storage
 * @param array{found: int, updated: int, copied: int, already_in_target: int, missing_source: int, copy_failed: int} $summary
 * @param array<int, string> $notes
 */
function migrateLegacyReference(array $row, array $storage, array &$summary, array &$notes): ?string
{
    $relativePath = ltrim((string) ($row[$storage['column']] ?? ''), '/');
    if ($relativePath === '' || !str_starts_with($relativePath, $storage['legacy_prefix'] . '/')) {
        return null;
    }

    $summary['found']++;
    $fileName = basename($relativePath);

    if ($fileName === '') {
        $summary['missing_source']++;
        $notes[] = sprintf(
            'Livro %s: caminho legado invalido em %s (%s)',
            (string) ($row['title'] ?? 'sem-titulo'),
            $storage['column'],
            $relativePath
        );
        return null;
    }

    $targetPath = $storage['target_directory'] . '/' . $fileName;
    $newRelativePath = trim($storage['managed_prefix'], '/') . '/' . $fileName;

    if (is_file($targetPath)) {
        $summary['already_in_target']++;
        $summary['updated']++;
        return $newRelativePath;
    }

    $sourcePath = $storage['source_directory'] . '/' . $fileName;
    if (!is_file($sourcePath) || !is_readable($sourcePath)) {
        $summary['missing_source']++;
        $notes[] = sprintf(
            'Livro %s: arquivo legado nao encontrado para %s',
            (string) ($row['title'] ?? 'sem-titulo'),
            $relativePath
        );
        return null;
    }

    if (!@copy($sourcePath, $targetPath)) {
        $summary['copy_failed']++;
        $notes[] = sprintf(
            'Livro %s: falha ao copiar %s para %s',
            (string) ($row['title'] ?? 'sem-titulo'),
            $sourcePath,
            $targetPath
        );
        return null;
    }

    @chmod($targetPath, 0664);
    $summary['copied']++;
    $summary['updated']++;

    return $newRelativePath;
}

/**
 * @return array{found: int, updated: int, copied: int, already_in_target: int, missing_source: int, copy_failed: int}
 */
function createBucketSummary(): array
{
    return [
        'found' => 0,
        'updated' => 0,
        'copied' => 0,
        'already_in_target' => 0,
        'missing_source' => 0,
        'copy_failed' => 0,
    ];
}

/**
 * @param array{
 *   pdf: array{label: string, column: string, legacy_prefix: string, managed_prefix: string, target_directory: string, source_directory: string},
 *   cover: array{label: string, column: string, legacy_prefix: string, managed_prefix: string, target_directory: string, source_directory: string}
 * } $storages
 * @return array<string, array{column: string, legacy_prefix: string, managed_prefix: string, target_directory: string, source_directory: string}>
 */
function summarizeStorages(array $storages): array
{
    $summary = [];

    foreach ($storages as $bucketKey => $storage) {
        $summary[$bucketKey] = [
            'column' => $storage['column'],
            'legacy_prefix' => $storage['legacy_prefix'],
            'managed_prefix' => $storage['managed_prefix'],
            'target_directory' => $storage['target_directory'],
            'source_directory' => $storage['source_directory'],
        ];
    }

    return $summary;
}

function nullableString(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    return (string) $value;
}

function resolveManagedDirectory(string $projectRoot, string $envKey, string $defaultDirectory): string
{
    $configuredDirectory = trim((string) ($_ENV[$envKey] ?? ''));

    if ($configuredDirectory !== '') {
        return resolveConfiguredManagedDirectory($projectRoot, $configuredDirectory);
    }

    return resolveManagedStorageDefaultDirectory($projectRoot, $defaultDirectory);
}

function resolveManagedPublicPrefix(string $envKey, string $defaultPublicPrefix): string
{
    $configuredPrefix = trim((string) ($_ENV[$envKey] ?? ''));

    if ($configuredPrefix !== '') {
        return trim(str_replace('\\', '/', $configuredPrefix), '/');
    }

    return trim(str_replace('\\', '/', $defaultPublicPrefix), '/');
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
        && !isAbsolutePath($normalizedPath)
        && str_starts_with($normalizedRelativePath, $storagePrefix)
    ) {
        $resolvedRoot = resolveProjectPath($projectRoot, $managedStorageRoot);

        return $resolvedRoot . '/' . ltrim(substr($normalizedRelativePath, strlen($storagePrefix)), '/');
    }

    return resolveProjectPath($projectRoot, $normalizedPath);
}

function resolveProjectPath(string $projectRoot, string $path): string
{
    $normalizedPath = str_replace('\\', '/', trim($path));
    if ($normalizedPath === '') {
        return $projectRoot;
    }

    if (isAbsolutePath($normalizedPath)) {
        return rtrim($normalizedPath, '/');
    }

    return rtrim($projectRoot . '/' . ltrim($normalizedPath, '/'), '/');
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
