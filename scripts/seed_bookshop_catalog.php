<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Bookshop\MySqlBookshopRepository;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_SEED_COUNT = 100;
const MAX_SEED_COUNT = 100;

$options = parseSeedOptions($argv);
$catalog = buildSeedCatalog($options['count']);

if ($options['help']) {
    renderHelp();
    exit(0);
}

if (!$options['apply']) {
    renderDryRunSummary($catalog);
    exit(0);
}

$projectRoot = dirname(__DIR__);
Dotenv::createImmutable($projectRoot)->safeLoad();

$pdo = createPdoFromEnvironment();
$repository = new MySqlBookshopRepository($pdo);

$pdo->beginTransaction();

try {
    $categoryMap = ensureCategories($repository, categoryBlueprints());
    $genreMap = ensureGenres($repository, genreBlueprints());
    $collectionMap = ensureCollections($repository, collectionBlueprints());

    $createdBooks = 0;
    $updatedBooks = 0;

    foreach ($catalog as $book) {
        $payload = $book;
        $payload['category_id'] = $categoryMap[$book['category_name']] ?? null;
        $payload['genre_id'] = $genreMap[$book['genre_name']] ?? null;
        $payload['collection_id'] = $book['collection_name'] !== ''
            ? ($collectionMap[$book['collection_name']] ?? null)
            : null;
        unset($payload['seed_index']);

        $existingBook = $repository->findBookBySku((string) $book['sku']);

        if ($existingBook !== null) {
            $repository->updateBook((int) $existingBook['id'], $payload);
            $updatedBooks++;
            continue;
        }

        $repository->createBook($payload);
        $createdBooks++;
    }

    $pdo->commit();

    $categoryCount = count($categoryMap);
    $genreCount = count($genreMap);
    $collectionCount = count($collectionMap);
    $bookCount = count($catalog);

    echo "Seed da livraria aplicado com sucesso.\n";
    echo "Categorias ativas/reaproveitadas: {$categoryCount}\n";
    echo "Generos ativos/reaproveitados: {$genreCount}\n";
    echo "Colecoes ativas/reaproveitadas: {$collectionCount}\n";
    echo "Livros criados: {$createdBooks}\n";
    echo "Livros atualizados: {$updatedBooks}\n";
    echo "Total processado: {$bookCount}\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Falha ao aplicar o seed da livraria: {$exception->getMessage()}\n");
    exit(1);
}

/**
 * @return array{apply: bool, help: bool, count: int}
 */
function parseSeedOptions(array $argv): array
{
    $options = [
        'apply' => false,
        'help' => false,
        'count' => DEFAULT_SEED_COUNT,
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

        if (strpos($argument, '--count=') === 0) {
            $value = substr($argument, 8);
            $count = (int) $value;

            if ($count < 1 || $count > MAX_SEED_COUNT) {
                fwrite(STDERR, "--count deve estar entre 1 e " . MAX_SEED_COUNT . ".\n");
                exit(1);
            }

            $options['count'] = $count;
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
    echo "  php scripts/seed_bookshop_catalog.php [--count=100] [--apply]\n\n";
    echo "Comportamento:\n";
    echo "  Sem --apply: apenas mostra um resumo do seed, sem gravar no banco.\n";
    echo "  Com --apply: cria ou atualiza o seed da livraria no banco configurado no .env.\n";
}

/**
 * @param array<int, array<string, mixed>> $catalog
 */
function renderDryRunSummary(array $catalog): void
{
    $categories = [];
    $genres = [];
    $collections = [];

    foreach ($catalog as $book) {
        $categories[(string) $book['category_name']] = true;
        $genres[(string) $book['genre_name']] = true;

        if ((string) ($book['collection_name'] ?? '') !== '') {
            $collections[(string) $book['collection_name']] = true;
        }
    }

    echo "Dry-run do seed da livraria.\n";
    echo "Nenhuma alteracao foi gravada no banco.\n";
    echo "Livros preparados: " . count($catalog) . "\n";
    echo "Categorias envolvidas: " . count($categories) . "\n";
    echo "Generos envolvidos: " . count($genres) . "\n\n";
    echo "Colecoes envolvidas: " . count($collections) . "\n\n";
    echo "Primeiros itens:\n";

    foreach (array_slice($catalog, 0, 5) as $book) {
        echo sprintf(
            "- %s | %s | %s | %s | %s | %s\n",
            (string) $book['sku'],
            (string) $book['title'],
            (string) $book['author_name'],
            (string) $book['category_name'],
            (string) $book['genre_name'],
            (string) ($book['collection_name'] !== ''
                ? $book['collection_name']
                    . (($book['volume_number'] ?? null) !== null ? ' · Vol. ' . $book['volume_number'] : '')
                    . ((string) ($book['volume_label'] ?? '') !== '' ? ' · ' . $book['volume_label'] : '')
                : 'Avulso')
        );
    }

    echo "\nPara gravar no banco:\n";
    echo "  php scripts/seed_bookshop_catalog.php --apply\n";
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
 * @return array<int, array{slug: string, name: string, description: string}>
 */
function categoryBlueprints(): array
{
    return [
        ['slug' => 'estudo-basico', 'name' => 'Estudo Básico', 'description' => 'Titulos introdutorios e leituras de base para novos estudantes.'],
        ['slug' => 'estudo-intermediario', 'name' => 'Estudo Intermediário', 'description' => 'Obras para aprofundamento progressivo em grupos de estudo.'],
        ['slug' => 'estudo-avancado', 'name' => 'Estudo Avançado', 'description' => 'Leituras de maior densidade para estudo continuado.'],
        ['slug' => 'evangelho-no-lar', 'name' => 'Evangelho no Lar', 'description' => 'Titulos de apoio para rotina de estudo e prece em familia.'],
        ['slug' => 'formacao-de-trabalhadores', 'name' => 'Formação de Trabalhadores', 'description' => 'Materiais de apoio para equipes e servico voluntario.'],
        ['slug' => 'infancia-e-juventude', 'name' => 'Infância e Juventude', 'description' => 'Livros voltados para publico infantil e juvenil.'],
        ['slug' => 'familia-e-relacionamentos', 'name' => 'Família e Relacionamentos', 'description' => 'Leituras sobre convivencia, educacao e cuidado no lar.'],
        ['slug' => 'mediunidade-pratica', 'name' => 'Mediunidade Prática', 'description' => 'Obras para estudo serio da mediunidade e da disciplina mediunica.'],
        ['slug' => 'pesquisa-e-referencia', 'name' => 'Pesquisa e Referência', 'description' => 'Material de consulta, apoio a pesquisa e referencia doutrinaria.'],
        ['slug' => 'acolhimento-e-servico', 'name' => 'Acolhimento e Serviço', 'description' => 'Livros para acolhimento fraterno, servico e consolacao.'],
    ];
}

/**
 * @return array<int, array{slug: string, name: string, description: string}>
 */
function genreBlueprints(): array
{
    return [
        ['slug' => 'doutrinario', 'name' => 'Doutrinário', 'description' => 'Obras de estudo e fundamentacao doutrinaria.'],
        ['slug' => 'romance', 'name' => 'Romance', 'description' => 'Narrativas longas com enfase em experiencia humana e espiritual.'],
        ['slug' => 'biografia', 'name' => 'Biografia', 'description' => 'Relatos de vida, memoria e trajetorias inspiradoras.'],
        ['slug' => 'infantojuvenil', 'name' => 'Infantojuvenil', 'description' => 'Livros de linguagem acessivel para criancas e jovens.'],
        ['slug' => 'cronica', 'name' => 'Crônica', 'description' => 'Textos breves, observacoes do cotidiano e reflexoes.'],
        ['slug' => 'conto', 'name' => 'Conto', 'description' => 'Narrativas curtas para leitura individual ou em grupo.'],
        ['slug' => 'mensagens', 'name' => 'Mensagens', 'description' => 'Coletaneas de paginas inspirativas e consoladoras.'],
        ['slug' => 'poesia', 'name' => 'Poesia', 'description' => 'Textos poeticos com acento contemplativo e sensivel.'],
        ['slug' => 'referencia', 'name' => 'Referência', 'description' => 'Guias, consultas e material de referencia para estudos.'],
        ['slug' => 'estudo-tematico', 'name' => 'Estudo Temático', 'description' => 'Obras organizadas por tema, assunto ou modulo de estudo.'],
    ];
}

/**
 * @return array<int, array{slug: string, name: string, description: string}>
 */
function collectionBlueprints(): array
{
    return [
        ['slug' => 'estudos-da-codificacao', 'name' => 'Estudos da Codificação', 'description' => 'Coleção dedicada ao estudo progressivo dos princípios fundamentais da Doutrina Espírita.'],
        ['slug' => 'roteiros-da-mediunidade-crista', 'name' => 'Roteiros da Mediunidade Cristã', 'description' => 'Série para estudo responsável da mediunidade, disciplina e serviço no centro espírita.'],
        ['slug' => 'evangelho-no-lar-e-no-coracao', 'name' => 'Evangelho no Lar e no Coração', 'description' => 'Volumes voltados à vivência do Evangelho no lar, na família e na convivência diária.'],
        ['slug' => 'cadernos-de-reforma-intima', 'name' => 'Cadernos de Reforma Íntima', 'description' => 'Coleção de apoio à autoeducação moral, vigilância e crescimento espiritual.'],
        ['slug' => 'biblioteca-da-vida-espiritual', 'name' => 'Biblioteca da Vida Espiritual', 'description' => 'Série com leituras sobre imortalidade, reencarnação e continuidade da vida.'],
        ['slug' => 'caminhos-da-caridade', 'name' => 'Caminhos da Caridade', 'description' => 'Coleção voltada ao serviço, acolhimento fraterno e prática do bem.'],
        ['slug' => 'trilhas-do-trabalhador-espirita', 'name' => 'Trilhas do Trabalhador Espírita', 'description' => 'Materiais em sequência para formação e sustentação do serviço voluntário.'],
        ['slug' => 'consolacao-e-esperanca', 'name' => 'Consolação e Esperança', 'description' => 'Coleção de leitura edificante, consolo espiritual e fortalecimento da fé raciocinada.'],
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function buildSeedCatalog(int $count): array
{
    $titleOpeners = [
        'Estudos sobre',
        'Roteiros de',
        'Lições sobre',
        'Reflexões de',
        'Temas de',
        'Caminhos para',
        'Apontamentos de',
        'Diálogos sobre',
        'Cadernos de',
        'Semeando',
    ];

    $titleThemes = [
        'O Evangelho',
        'A Mediunidade',
        'A Reencarnação',
        'A Caridade',
        'A Reforma Íntima',
        'A Vida Espiritual',
        'A Prece',
        'A Lei de Causa e Efeito',
        'O Passe',
        'A Fé Raciocinada',
    ];

    $subtitleTemplates = [
        'Estudos dirigidos para grupos doutrinários',
        'Leitura comentada para o cotidiano cristão',
        'Apontamentos para formação de trabalhadores da casa espírita',
        'Caderno de apoio para reuniões de estudo e serviço',
        'Reflexões para o lar, o centro e a convivência fraterna',
        'Notas práticas para leitura individual e estudo em grupo',
    ];

    $authorFirstNames = [
        'Ana',
        'Bruno',
        'Clara',
        'Daniel',
        'Elisa',
        'Fabio',
        'Helena',
        'Marcos',
        'Paula',
        'Renato',
    ];

    $authorLastNames = [
        'Valença',
        'Moraes',
        'Tavares',
        'Campos',
        'Azevedo',
        'Nogueira',
        'Farias',
        'Barreto',
        'Monteiro',
        'Ribeiro',
    ];

    $publishers = [
        'Casa Editora Boa Nova',
        'Instituto Luz e Verdade',
        'Editora Caminho de Paz',
        'Semeador Espírita',
        'Roteiro Cristão Edições',
        'Cadernos da Doutrina',
        'Editora Vinha de Luz',
        'Oficina do Livro Espírita',
        'Aurora da Alma Editorial',
        'Editora Consolador',
        'Ponte Fraterna Editora',
        'Casa do Estudo Espírita',
    ];

    $languages = [
        'Português',
        'Português',
        'Português',
        'Português',
        'Português',
        'Português',
        'Português',
        'Espanhol',
        'Francês',
        'Italiano',
        'Galego',
        'Catalão',
        'Romeno',
    ];

    $categories = categoryBlueprints();
    $genres = genreBlueprints();
    $collections = collectionBlueprints();

    $books = [];
    $seedIndex = 1;

    foreach ($titleOpeners as $openerIndex => $opener) {
        foreach ($titleThemes as $themeIndex => $theme) {
            if ($seedIndex > $count) {
                break 2;
            }

            $zeroBasedIndex = $seedIndex - 1;
            $author = $authorFirstNames[$zeroBasedIndex % count($authorFirstNames)]
                . ' '
                . $authorLastNames[intdiv($zeroBasedIndex, count($authorFirstNames)) % count($authorLastNames)];

            $category = $categories[$zeroBasedIndex % count($categories)];
            $genre = $genres[($zeroBasedIndex * 3) % count($genres)];
            $publisher = $publishers[($zeroBasedIndex * 5) % count($publishers)];
            $language = $languages[($zeroBasedIndex * 7) % count($languages)];
            $editionNumber = ($zeroBasedIndex % 5) + 1;
            $year = 2011 + (($zeroBasedIndex * 2) % 14);
            $stockQuantity = [0, 2, 4, 6, 8, 10, 12, 15, 18, 3][($zeroBasedIndex * 2) % 10];
            $stockMinimum = [1, 2, 2, 3, 4][($zeroBasedIndex + 1) % 5];
            $costPrice = round(14.90 + (($zeroBasedIndex % 10) * 2.35) + (intdiv($zeroBasedIndex, 10) * 0.55), 2);
            $salePrice = round($costPrice * (1.55 + (($zeroBasedIndex % 4) * 0.06)), 2);
            $subtitle = $zeroBasedIndex % 4 === 0
                ? null
                : $subtitleTemplates[$zeroBasedIndex % count($subtitleTemplates)];
            $location = sprintf(
                'Estante %s%d',
                chr(65 + (intdiv($zeroBasedIndex, 20) % 5)),
                ($zeroBasedIndex % 5) + 1
            );
            $collectionIndex = intdiv($zeroBasedIndex, 4);
            $collection = $collections[$collectionIndex % count($collections)];
            $hasCollection = $zeroBasedIndex % 10 < 6;
            $volumeNumber = $hasCollection ? (($zeroBasedIndex % 4) + 1) : null;
            $volumeLabel = $hasCollection
                ? ['Caderno Doutrinário', 'Leitura Complementar', 'Roteiro de Estudo', 'Síntese Fraterna'][$zeroBasedIndex % 4]
                : null;
            $title = $opener . ' ' . $theme;

            $description = sprintf(
                '%s conduz o leitor por reflexões sobre %s, com linguagem acolhedora, foco em %s e aplicações práticas ligadas a %s. A obra foi pensada para leitura pessoal, estudo em grupo e conversas fraternas no contexto do CEDE.',
                $author,
                $theme,
                $category['name'],
                $genre['name']
            );

            $books[] = [
                'seed_index' => $seedIndex,
                'sku' => sprintf('SEED-LIV-%03d', $seedIndex),
                'slug' => sprintf('seed-livraria-item-%03d', $seedIndex),
                'category_name' => $category['name'],
                'genre_name' => $genre['name'],
                'collection_name' => $hasCollection ? $collection['name'] : '',
                'title' => $title,
                'subtitle' => $subtitle,
                'author_name' => $author,
                'publisher_name' => $publisher,
                'isbn' => sprintf('9786500%06d', $seedIndex),
                'barcode' => sprintf('7898200%06d', $seedIndex),
                'edition_label' => sprintf('%da edição', $editionNumber),
                'volume_number' => $volumeNumber,
                'volume_label' => $volumeLabel,
                'publication_year' => $year,
                'language' => $language,
                'description' => $description,
                'cost_price' => number_format($costPrice, 2, '.', ''),
                'sale_price' => number_format($salePrice, 2, '.', ''),
                'stock_quantity' => $stockQuantity,
                'stock_minimum' => $stockMinimum,
                'status' => 'active',
                'location_label' => $location,
            ];

            $seedIndex++;
        }
    }

    return $books;
}

/**
 * @param array<int, array{slug: string, name: string, description: string}> $blueprints
 * @return array<string, int>
 */
function ensureCategories(MySqlBookshopRepository $repository, array $blueprints): array
{
    $existingCategories = $repository->findAllCategoriesForAdmin();
    $categoryMap = [];
    $categoryMapBySlug = [];

    foreach ($existingCategories as $category) {
        $name = trim((string) ($category['name'] ?? ''));
        $slug = trim((string) ($category['slug'] ?? ''));
        $id = (int) ($category['id'] ?? 0);

        if ($slug !== '' && $id > 0) {
            $categoryMapBySlug[$slug] = $id;
        }

        if ($name === '') {
            continue;
        }

        $categoryMap[$name] = $id;
    }

    foreach ($blueprints as $category) {
        $name = $category['name'];
        $slug = $category['slug'];

        if (isset($categoryMap[$name]) && $categoryMap[$name] > 0) {
            continue;
        }

        if (isset($categoryMapBySlug[$slug]) && $categoryMapBySlug[$slug] > 0) {
            $categoryMap[$name] = $categoryMapBySlug[$slug];
            continue;
        }

        $categoryId = $repository->createCategory([
            'slug' => $slug,
            'name' => $name,
            'description' => $category['description'],
            'is_active' => 1,
        ]);

        $categoryMap[$name] = $categoryId;
        $categoryMapBySlug[$slug] = $categoryId;
    }

    return $categoryMap;
}

/**
 * @param array<int, array{slug: string, name: string, description: string}> $blueprints
 * @return array<string, int>
 */
function ensureGenres(MySqlBookshopRepository $repository, array $blueprints): array
{
    $existingGenres = $repository->findAllGenresForAdmin();
    $genreMap = [];
    $genreMapBySlug = [];

    foreach ($existingGenres as $genre) {
        $name = trim((string) ($genre['name'] ?? ''));
        $slug = trim((string) ($genre['slug'] ?? ''));
        $id = (int) ($genre['id'] ?? 0);

        if ($slug !== '' && $id > 0) {
            $genreMapBySlug[$slug] = $id;
        }

        if ($name === '') {
            continue;
        }

        $genreMap[$name] = $id;
    }

    foreach ($blueprints as $genre) {
        $name = $genre['name'];
        $slug = $genre['slug'];

        if (isset($genreMap[$name]) && $genreMap[$name] > 0) {
            continue;
        }

        if (isset($genreMapBySlug[$slug]) && $genreMapBySlug[$slug] > 0) {
            $genreMap[$name] = $genreMapBySlug[$slug];
            continue;
        }

        $genreId = $repository->createGenre([
            'slug' => $slug,
            'name' => $name,
            'description' => $genre['description'],
            'is_active' => 1,
        ]);

        $genreMap[$name] = $genreId;
        $genreMapBySlug[$slug] = $genreId;
    }

    return $genreMap;
}

/**
 * @param array<int, array{slug: string, name: string, description: string}> $blueprints
 * @return array<string, int>
 */
function ensureCollections(MySqlBookshopRepository $repository, array $blueprints): array
{
    $existingCollections = $repository->findAllCollectionsForAdmin();
    $collectionMap = [];
    $collectionMapBySlug = [];

    foreach ($existingCollections as $collection) {
        $name = trim((string) ($collection['name'] ?? ''));
        $slug = trim((string) ($collection['slug'] ?? ''));
        $id = (int) ($collection['id'] ?? 0);

        if ($slug !== '' && $id > 0) {
            $collectionMapBySlug[$slug] = $id;
        }

        if ($name === '') {
            continue;
        }

        $collectionMap[$name] = $id;
    }

    foreach ($blueprints as $collection) {
        $name = $collection['name'];
        $slug = $collection['slug'];

        if (isset($collectionMap[$name]) && $collectionMap[$name] > 0) {
            continue;
        }

        if (isset($collectionMapBySlug[$slug]) && $collectionMapBySlug[$slug] > 0) {
            $collectionMap[$name] = $collectionMapBySlug[$slug];
            continue;
        }

        $collectionId = $repository->createCollection([
            'slug' => $slug,
            'name' => $name,
            'description' => $collection['description'],
            'is_active' => 1,
        ]);

        $collectionMap[$name] = $collectionId;
        $collectionMapBySlug[$slug] = $collectionId;
    }

    return $collectionMap;
}
