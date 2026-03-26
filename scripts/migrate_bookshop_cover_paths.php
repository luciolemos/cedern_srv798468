<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

const LEGACY_BOOKSHOP_COVER_PREFIX = 'media/livraria/capas/';
const PUBLIC_BOOKSHOP_COVER_PREFIX = 'assets/img/bookshop-covers/';
const PUBLIC_BOOKSHOP_COVER_DIRECTORY = '/public/assets/img/bookshop-covers';
const FALLBACK_BOOKSHOP_COVER_DIRECTORY = '/var/cache/bookshop-covers';
const TEMP_BOOKSHOP_COVER_SUBDIRECTORY = '/natalcode/bookshop-covers';

$options = parseOptions($argv);

if ($options['help']) {
    renderHelp();
    exit(0);
}

$projectRoot = dirname(__DIR__);
Dotenv::createImmutable($projectRoot)->safeLoad();

$pdo = createPdoFromEnvironment();
$sourceDirectories = resolveLegacySourceDirectories($projectRoot);
$targetDirectory = $projectRoot . PUBLIC_BOOKSHOP_COVER_DIRECTORY;

if (!ensureWritableDirectory($targetDirectory)) {
    fwrite(STDERR, "Diretório público de capas da livraria sem escrita: {$targetDirectory}\n");
    exit(1);
}

$statement = $pdo->query(
    "SELECT id, sku, title, cover_image_path
     FROM bookshop_books
     WHERE cover_image_path LIKE '" . LEGACY_BOOKSHOP_COVER_PREFIX . "%'
     ORDER BY id ASC"
);

$rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
$summary = [
    'found' => count($rows),
    'updated' => 0,
    'already_public' => 0,
    'missing_source' => 0,
    'copy_failed' => 0,
];
$notes = [];

if (!$options['apply']) {
    foreach ($rows as $row) {
        $relativePath = ltrim((string) ($row['cover_image_path'] ?? ''), '/');
        $fileName = basename($relativePath);
        $sourcePath = findFirstExistingPath($fileName, $sourceDirectories);
        $targetPath = $targetDirectory . '/' . $fileName;

        if (is_file($targetPath)) {
            $summary['already_public']++;
            continue;
        }

        if ($sourcePath === null) {
            $summary['missing_source']++;
            $notes[] = sprintf(
                'Livro %s (%s): arquivo antigo não encontrado para %s',
                (string) ($row['sku'] ?? 'sem-sku'),
                (string) ($row['title'] ?? 'sem-titulo'),
                $relativePath
            );
            continue;
        }
    }

    echo json_encode([
        'mode' => 'dry-run',
        'legacy_prefix' => LEGACY_BOOKSHOP_COVER_PREFIX,
        'public_prefix' => PUBLIC_BOOKSHOP_COVER_PREFIX,
        'source_directories' => $sourceDirectories,
        'target_directory' => $targetDirectory,
        'summary' => $summary,
        'notes' => array_slice($notes, 0, 12),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$update = $pdo->prepare(
    'UPDATE bookshop_books SET cover_image_path = :cover_image_path WHERE id = :id'
);

$pdo->beginTransaction();

try {
    foreach ($rows as $row) {
        $bookId = (int) ($row['id'] ?? 0);
        $relativePath = ltrim((string) ($row['cover_image_path'] ?? ''), '/');
        $fileName = basename($relativePath);
        $targetPath = $targetDirectory . '/' . $fileName;
        $newRelativePath = PUBLIC_BOOKSHOP_COVER_PREFIX . $fileName;

        if ($fileName === '' || $bookId <= 0) {
            continue;
        }

        if (!is_file($targetPath)) {
            $sourcePath = findFirstExistingPath($fileName, $sourceDirectories);

            if ($sourcePath === null) {
                $summary['missing_source']++;
                $notes[] = sprintf(
                    'Livro %s (%s): arquivo antigo não encontrado para %s',
                    (string) ($row['sku'] ?? 'sem-sku'),
                    (string) ($row['title'] ?? 'sem-titulo'),
                    $relativePath
                );
                continue;
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
                continue;
            }

            @chmod($targetPath, 0664);
        } else {
            $summary['already_public']++;
        }

        $update->execute([
            'cover_image_path' => $newRelativePath,
            'id' => $bookId,
        ]);
        $summary['updated']++;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Falha ao migrar capas antigas da livraria: {$exception->getMessage()}\n");
    exit(1);
}

echo json_encode([
    'mode' => 'apply',
    'legacy_prefix' => LEGACY_BOOKSHOP_COVER_PREFIX,
    'public_prefix' => PUBLIC_BOOKSHOP_COVER_PREFIX,
    'summary' => $summary,
    'notes' => array_slice($notes, 0, 12),
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
    echo "  Sem --apply: lista quantos livros ainda apontam para media/livraria/capas.\n";
    echo "  Com --apply: copia as capas antigas para public/assets/img/bookshop-covers e atualiza o banco.\n";
}

function createPdoFromEnvironment(): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) ($_ENV['DB_HOST'] ?? ''),
            (int) ($_ENV['DB_PORT'] ?? 3306),
            (string) ($_ENV['DB_NAME'] ?? '')
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
 * @return array<int, string>
 */
function resolveLegacySourceDirectories(string $projectRoot): array
{
    $directories = [
        $projectRoot . FALLBACK_BOOKSHOP_COVER_DIRECTORY,
        rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . TEMP_BOOKSHOP_COVER_SUBDIRECTORY,
        $projectRoot . PUBLIC_BOOKSHOP_COVER_DIRECTORY,
    ];

    return array_values(array_unique(array_filter(
        array_map(static fn (string $path): string => rtrim($path, '/'), $directories),
        static fn (string $path): bool => $path !== ''
    )));
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

/**
 * @param array<int, string> $directories
 */
function findFirstExistingPath(string $fileName, array $directories): ?string
{
    foreach ($directories as $directory) {
        $candidate = $directory . '/' . $fileName;
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}
