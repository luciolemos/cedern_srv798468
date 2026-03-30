<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Bookshop\MySqlBookshopRepository;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = parseOptions($argv);

if ($options['help']) {
    renderHelp();
    exit(0);
}

$projectRoot = dirname(__DIR__);
Dotenv::createImmutable($projectRoot)->safeLoad();

$pdo = createPdoFromEnvironment();
$repository = new MySqlBookshopRepository($pdo);
$summary = fetchBookSkuSummary($pdo);

if (!$options['apply']) {
    echo json_encode([
        'mode' => 'dry-run',
        'summary' => $summary,
        'next_generated_sku' => $repository->generateNextBookSku(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$updated = $repository->renumberBookSkusSequentially();
$after = fetchBookSkuSummary($pdo);

echo json_encode([
    'mode' => 'apply',
    'updated_books' => $updated,
    'summary' => $after,
    'next_generated_sku' => $repository->generateNextBookSku(),
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
    echo "  php scripts/renumber_bookshop_skus.php [--apply]\n\n";
    echo "Comportamento:\n";
    echo "  Sem --apply: mostra a faixa atual de SKUs e o próximo código gerado.\n";
    echo "  Com --apply: renumera o acervo em ordem de ID, atualiza snapshots e calcula o próximo SKU.\n";
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
 * @return array<string, mixed>
 */
function fetchBookSkuSummary(PDO $pdo): array
{
    $totals = $pdo->query(
        'SELECT COUNT(*) AS total_books, MIN(id) AS min_id, MAX(id) AS max_id FROM bookshop_books'
    )->fetch() ?: [];

    $firstRows = $pdo->query(
        'SELECT id, sku, title FROM bookshop_books ORDER BY id ASC LIMIT 5'
    )->fetchAll() ?: [];

    $lastRows = $pdo->query(
        'SELECT id, sku, title FROM bookshop_books ORDER BY id DESC LIMIT 5'
    )->fetchAll() ?: [];

    return [
        'total_books' => (int) ($totals['total_books'] ?? 0),
        'min_id' => isset($totals['min_id']) ? (int) $totals['min_id'] : null,
        'max_id' => isset($totals['max_id']) ? (int) $totals['max_id'] : null,
        'first_rows' => $firstRows,
        'last_rows' => $lastRows,
    ];
}
