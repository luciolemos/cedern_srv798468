<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Bookshop\MySqlBookshopRepository;
use App\Support\BookshopTextNormalizer;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_INPUT_PATH = __DIR__ . '/../var/exports/bookshop-seed-from-relatorio-produtos.csv';

const HEADER_ALIASES = [
    'sku' => ['sku', 'codigo', 'codigo interno', 'código', 'id'],
    'title' => ['titulo', 'título', 'title', 'nome'],
    'author_name' => ['autor', 'author', 'author_name'],
    'category_name' => ['categoria', 'category', 'secao', 'seção', 'category_name'],
    'genre_name' => ['genero', 'gênero', 'genre', 'genre_name'],
    'collection_name' => ['colecao', 'coleção', 'collection', 'serie', 'série', 'collection_name'],
    'publisher_name' => ['editora', 'publisher', 'publisher_name'],
    'isbn' => ['isbn'],
    'barcode' => ['codigo_barra', 'código_barra', 'codigo de barras', 'código de barras', 'ean', 'barcode'],
    'edition_label' => ['edicao', 'edição', 'edition', 'edicao_label', 'edition_label'],
    'volume_number' => ['numero_volume', 'número_volume', 'numero do volume', 'número do volume', 'volume', 'volume_number'],
    'volume_label' => ['rotulo_volume', 'rótulo_volume', 'rotulo do volume', 'rótulo do volume', 'volume_label'],
    'publication_year' => ['ano', 'ano_publicacao', 'ano de publicacao', 'publication_year'],
    'page_count' => ['paginas', 'páginas', 'n_paginas', 'número de páginas', 'numero de paginas', 'page_count'],
    'language' => ['idioma', 'language'],
    'description' => ['descricao', 'descrição', 'sinopse', 'description'],
    'sale_price' => ['preco', 'preço', 'preco_venda', 'preço_venda', 'valor_venda', 'sale_price'],
    'stock_minimum' => ['estoque_minimo', 'estoque mínimo', 'estoque_mínimo', 'minimo', 'mínimo', 'stock_minimum'],
    'status' => ['status', 'situacao', 'situação'],
    'location_label' => ['localizacao', 'localização', 'prateleira', 'location', 'location_label'],
    'subtitle' => ['subtitulo', 'subtítulo', 'subtitle'],
    'slug' => ['slug'],
];

const ROMANCE_LANGUAGE_OPTIONS = [
    'Português',
    'Espanhol',
    'Francês',
    'Italiano',
    'Romeno',
    'Catalão',
    'Galego',
];

$options = parseOptions($argv);

if ($options['help']) {
    renderHelp();
    exit(0);
}

$projectRoot = dirname(__DIR__);
Dotenv::createImmutable($projectRoot)->safeLoad();

$rows = parseCsvFile($options['input']);

if (!$options['apply']) {
    renderDryRunSummary($rows, $options['input']);
    exit(0);
}

$pdo = createPdoFromEnvironment();
$repository = new MySqlBookshopRepository($pdo);
$categoryMap = buildEntityMap($repository->findAllCategoriesForAdmin());
$genreMap = buildEntityMap($repository->findAllGenresForAdmin());
$collectionMap = buildEntityMap($repository->findAllCollectionsForAdmin());

$summary = [
    'processed' => 0,
    'created' => 0,
    'updated' => 0,
    'errors' => 0,
];
$errors = [];

$pdo->beginTransaction();

try {
    foreach ($rows as $lineNumber => $row) {
        $summary['processed']++;

        try {
            $payload = normalizeImportRow($row);
            $rowErrors = validateImportPayload($payload);

            if ($rowErrors !== []) {
                throw new RuntimeException(implode(' ', $rowErrors));
            }

            $payload['category_id'] = resolveEntityId($payload['category_name'], $categoryMap);
            $payload['genre_id'] = resolveEntityId($payload['genre_name'], $genreMap);
            $payload['collection_id'] = resolveEntityId($payload['collection_name'], $collectionMap);

            $existingBook = $repository->findBookBySku((string) $payload['sku']);
            if ($existingBook === null && (string) ($payload['isbn'] ?? '') !== '') {
                $existingBook = $repository->findBookByIsbn((string) $payload['isbn']);
            }

            if ($existingBook !== null) {
                $payload['cost_price'] = (string) ($existingBook['cost_price'] ?? '0.00');
                $payload['stock_quantity'] = (int) ($existingBook['stock_quantity'] ?? 0);
                $repository->updateBook((int) $existingBook['id'], $payload);
                $summary['updated']++;
                continue;
            }

            $payload['cost_price'] = '0.00';
            $payload['stock_quantity'] = 0;
            $repository->createBook($payload);
            $summary['created']++;
        } catch (Throwable $exception) {
            $summary['errors']++;
            $errors[] = 'Linha ' . $lineNumber . ': ' . trim($exception->getMessage());
        }
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Falha ao importar o CSV da livraria: {$exception->getMessage()}\n");
    exit(1);
}

echo "Importação da livraria concluída.\n";
echo 'Arquivo: ' . $options['input'] . "\n";
echo 'Linhas processadas: ' . $summary['processed'] . "\n";
echo 'Livros criados: ' . $summary['created'] . "\n";
echo 'Livros atualizados: ' . $summary['updated'] . "\n";
echo 'Erros: ' . $summary['errors'] . "\n";

if ($errors !== []) {
    echo "\nPrimeiros erros:\n";

    foreach (array_slice($errors, 0, 12) as $error) {
        echo '- ' . $error . "\n";
    }
}

/**
 * @return array{input: string, apply: bool, help: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'input' => realpath(DEFAULT_INPUT_PATH) ?: DEFAULT_INPUT_PATH,
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

        if (strpos($argument, '--input=') === 0) {
            $options['input'] = substr($argument, 8);
            continue;
        }

        fwrite(STDERR, "Opcao invalida: {$argument}\n");
        exit(1);
    }

    if (!is_file($options['input'])) {
        fwrite(STDERR, "CSV nao encontrado: {$options['input']}\n");
        exit(1);
    }

    return $options;
}

function renderHelp(): void
{
    echo "Uso:\n";
    echo "  php scripts/import_bookshop_csv.php [--input=/caminho/arquivo.csv] [--apply]\n\n";
    echo "Comportamento:\n";
    echo "  Sem --apply: mostra um resumo seco do CSV.\n";
    echo "  Com --apply: importa o CSV da livraria no banco configurado no .env.\n";
}

/**
 * @return array<int, array<string, string>>
 */
function parseCsvFile(string $path): array
{
    $contents = (string) file_get_contents($path);
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

    $temporaryFile = tempnam(sys_get_temp_dir(), 'bookshop_csv_');
    if ($temporaryFile === false) {
        throw new RuntimeException('Não foi possível preparar a leitura temporária do CSV.');
    }

    file_put_contents($temporaryFile, $contents);
    $handle = fopen($temporaryFile, 'rb');

    if (!is_resource($handle)) {
        @unlink($temporaryFile);
        throw new RuntimeException('Não foi possível ler o CSV informado.');
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        @unlink($temporaryFile);
        throw new RuntimeException('O CSV informado está vazio.');
    }

    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!is_array($headers)) {
        fclose($handle);
        @unlink($temporaryFile);
        throw new RuntimeException('Não foi possível interpretar o cabeçalho do CSV.');
    }

    $mappedHeaders = mapHeaders($headers);
    $rows = [];
    $lineNumber = 1;

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $lineNumber++;
        $row = [];

        foreach ($mappedHeaders as $field => $index) {
            $row[$field] = trim((string) ($data[$index] ?? ''));
        }

        if (rowIsEmpty($row)) {
            continue;
        }

        $rows[$lineNumber] = $row;
    }

    fclose($handle);
    @unlink($temporaryFile);

    if ($rows === []) {
        throw new RuntimeException('Nenhuma linha utilizável foi encontrada no CSV.');
    }

    return $rows;
}

/**
 * @param array<int, string> $headers
 * @return array<string, int>
 */
function mapHeaders(array $headers): array
{
    $normalizedHeaders = [];

    foreach ($headers as $index => $header) {
        $normalizedHeaders[$index] = normalizeHeader((string) $header);
    }

    $mapped = [];

    foreach (HEADER_ALIASES as $field => $aliases) {
        foreach ($normalizedHeaders as $index => $header) {
            if (in_array($header, array_map('normalizeHeader', $aliases), true)) {
                $mapped[$field] = $index;
                break;
            }
        }
    }

    return $mapped;
}

function normalizeHeader(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
    $normalized = str_replace(['"', '\''], '', $normalized);

    return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
}

/**
 * @param array<string, string> $row
 * @return array<string, mixed>
 */
function normalizeImportRow(array $row): array
{
    $title = BookshopTextNormalizer::normalizeTitle((string) ($row['title'] ?? ''));
    $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
    $isbn = trim((string) ($row['isbn'] ?? ''));

    if ($sku === '' && $isbn !== '') {
        $sku = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '', $isbn) ?? $isbn);
    }

    $slugInput = trim((string) ($row['slug'] ?? ''));
    $slug = slugify($slugInput !== '' ? $slugInput : ($title . '-' . strtolower($sku)));
    $status = strtolower(trim((string) ($row['status'] ?? 'active')));
    $status = in_array($status, ['inactive', 'inativo'], true) ? 'inactive' : 'active';

    $publicationYear = trim((string) ($row['publication_year'] ?? ''));
    $pageCount = trim((string) ($row['page_count'] ?? ''));
    $volumeNumber = trim((string) ($row['volume_number'] ?? ''));

    return [
        'sku' => $sku,
        'slug' => $slug,
        'category_name' => trim((string) ($row['category_name'] ?? '')),
        'genre_name' => trim((string) ($row['genre_name'] ?? '')),
        'collection_name' => trim((string) ($row['collection_name'] ?? '')),
        'title' => $title,
        'subtitle' => trim((string) ($row['subtitle'] ?? '')),
        'author_name' => BookshopTextNormalizer::normalizeAuthorName((string) ($row['author_name'] ?? '')),
        'publisher_name' => trim((string) ($row['publisher_name'] ?? '')),
        'isbn' => $isbn,
        'barcode' => trim((string) ($row['barcode'] ?? '')),
        'edition_label' => trim((string) ($row['edition_label'] ?? '')),
        'volume_number' => ctype_digit($volumeNumber) ? (int) $volumeNumber : null,
        'volume_label' => trim((string) ($row['volume_label'] ?? '')),
        'publication_year' => ctype_digit($publicationYear) ? (int) $publicationYear : null,
        'page_count' => ctype_digit($pageCount) && (int) $pageCount > 0 ? (int) $pageCount : null,
        'language' => normalizeBookshopLanguage($row['language'] ?? ''),
        'description' => trim((string) ($row['description'] ?? '')),
        'sale_price' => normalizeMoneyInput($row['sale_price'] ?? '0'),
        'stock_minimum' => max(0, normalizeIntegerInput($row['stock_minimum'] ?? 0, 0)),
        'status' => $status,
        'location_label' => trim((string) ($row['location_label'] ?? '')),
    ];
}

/**
 * @param array<string, mixed> $payload
 * @return array<int, string>
 */
function validateImportPayload(array $payload): array
{
    $errors = [];

    if ((string) ($payload['title'] ?? '') === '') {
        $errors[] = 'Título obrigatório.';
    }

    if ((string) ($payload['author_name'] ?? '') === '') {
        $errors[] = 'Autor obrigatório.';
    }

    if ((string) ($payload['sku'] ?? '') === '') {
        $errors[] = 'SKU obrigatório ou ISBN utilizável para gerar SKU.';
    }

    if ((string) ($payload['slug'] ?? '') === '') {
        $errors[] = 'Slug inválido.';
    }

    if (
        ((string) ($payload['collection_name'] ?? '') === '')
        && (
            ($payload['volume_number'] ?? null) !== null
            || (string) ($payload['volume_label'] ?? '') !== ''
        )
    ) {
        $errors[] = 'Informe a coleção para usar campos de volume.';
    }

    $pageCount = $payload['page_count'] ?? null;
    if ($pageCount !== null && (int) $pageCount <= 0) {
        $errors[] = 'Quantidade de páginas inválida.';
    }

    return $errors;
}

/**
 * @param array<string, string> $row
 */
function rowIsEmpty(array $row): bool
{
    foreach ($row as $value) {
        if (trim($value) !== '') {
            return false;
        }
    }

    return true;
}

function slugify(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = (string) preg_replace('/[^a-z0-9-]+/', '-', $normalized);

    return trim($normalized, '-');
}

function normalizeMoneyInput(mixed $value): string
{
    $normalized = trim((string) $value);
    $normalized = str_replace(['R$', ' '], '', $normalized);

    if ($normalized === '') {
        return '0.00';
    }

    $hasComma = strpos($normalized, ',') !== false;
    $hasDot = strpos($normalized, '.') !== false;

    if ($hasComma && $hasDot) {
        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }
    } elseif ($hasComma) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    }

    if (!is_numeric($normalized)) {
        return '0.00';
    }

    return number_format((float) $normalized, 2, '.', '');
}

function normalizeIntegerInput(mixed $value, int $default = 0): int
{
    $normalized = trim((string) $value);
    if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
        return $default;
    }

    return (int) $normalized;
}

function normalizeBookshopLanguage(mixed $value): string
{
    $language = trim((string) $value);
    $language = trim($language, " \t\n\r\0\x0B|");
    if ($language === '') {
        return '';
    }

    $normalizedLanguage = normalizeBookshopLanguageKey($language);

    foreach (ROMANCE_LANGUAGE_OPTIONS as $option) {
        if (normalizeBookshopLanguageKey($option) === $normalizedLanguage) {
            return $option;
        }
    }

    return $language;
}

function normalizeBookshopLanguageKey(string $value): string
{
    $normalized = mb_strtolower(trim($value), 'UTF-8');
    $normalized = strtr($normalized, [
        'á' => 'a',
        'à' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ó' => 'o',
        'ò' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ç' => 'c',
    ]);
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

    return trim($normalized);
}

function createPdoFromEnvironment(): PDO
{
    $host = trim((string) ($_ENV['DB_HOST'] ?? ''));
    $name = trim((string) ($_ENV['DB_NAME'] ?? ''));
    $user = trim((string) ($_ENV['DB_USER'] ?? ''));
    $pass = (string) ($_ENV['DB_PASS'] ?? '');
    $port = (int) ($_ENV['DB_PORT'] ?? 3306);
    $charset = trim((string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));
    $timezone = trim((string) ($_ENV['DB_TIMEZONE'] ?? '+00:00'));

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('Configuracao de banco incompleta no .env.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $port,
        $name,
        $charset
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec(sprintf("SET time_zone = '%s'", str_replace("'", "''", $timezone)));

    return $pdo;
}

/**
 * @param array<int, array<string, mixed>> $entities
 * @return array<string, int>
 */
function buildEntityMap(array $entities): array
{
    $map = [];

    foreach ($entities as $entity) {
        $name = trim((string) ($entity['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $map[normalizeEntityLookupKey($name)] = (int) ($entity['id'] ?? 0);
    }

    return $map;
}

/**
 * @param array<string, int> $entityMap
 */
function resolveEntityId(string $name, array $entityMap): ?int
{
    $normalizedName = trim($name);
    if ($normalizedName === '') {
        return null;
    }

    $lookupKey = normalizeEntityLookupKey($normalizedName);

    return $entityMap[$lookupKey] ?? null;
}

function normalizeEntityLookupKey(string $value): string
{
    $normalized = mb_strtolower(trim($value), 'UTF-8');
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

    return trim($normalized);
}

/**
 * @param array<int, array<string, string>> $rows
 */
function renderDryRunSummary(array $rows, string $input): void
{
    echo "Dry-run do import da livraria.\n";
    echo 'Arquivo: ' . $input . "\n";
    echo 'Linhas utilizáveis: ' . count($rows) . "\n";

    $sampleCount = 0;
    echo "\nPrimeiras linhas:\n";

    foreach ($rows as $row) {
        echo sprintf(
            "- %s | %s | %s\n",
            (string) ($row['sku'] ?? ''),
            (string) ($row['title'] ?? ''),
            (string) ($row['author_name'] ?? '')
        );

        $sampleCount++;
        if ($sampleCount >= 5) {
            break;
        }
    }

    echo "\nPara importar de verdade:\n";
    echo "  php scripts/import_bookshop_csv.php --apply\n";
}
