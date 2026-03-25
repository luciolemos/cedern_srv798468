<?php

declare(strict_types=1);

use App\Support\BookshopTextNormalizer;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_INPUT_PATH = __DIR__ . '/../relatorio_produtos.xlsx';
const DEFAULT_OUTPUT_PATH = __DIR__ . '/../var/exports/bookshop-seed-from-relatorio-produtos.csv';
const DEFAULT_REVIEW_PATH = __DIR__ . '/../var/exports/bookshop-seed-from-relatorio-produtos-review.csv';
const DEFAULT_JSON_PATH = __DIR__ . '/../var/exports/bookshop-seed-from-relatorio-produtos.json';
const DEFAULT_CACHE_DIR = __DIR__ . '/../var/cache/book-metadata-seed';
const DEFAULT_SLEEP_MS = 120;
const DEFAULT_MATCH_THRESHOLD = 0.90;
const DEFAULT_REVIEW_THRESHOLD = 0.72;
const MANUAL_SKIPPED_TITLES = [
    'BAZAR',
    'CAMISA',
    'DOAÇÃO',
];
const MANUAL_METADATA_OVERRIDES = [
    'A ALMA DOS ANIMAIS' => [
        'title' => 'A Alma dos Animais',
        'authors' => ['Celso Martins'],
        'source_name' => 'Invisibillis',
        'source_url' => 'https://www.invisibillis.com/livros/a-alma-dos-animais/',
        'confidence' => 1.0,
    ],
    'AS LEIS MORAIS' => [
        'title' => 'As Leis Morais',
        'authors' => ['Rodolfo Calligaris'],
        'source_name' => 'FERGS Livraria e Editora',
        'source_url' => 'https://www.livrariaespirita.org.br/leis-morais-as',
        'confidence' => 1.0,
    ],
    'AS 5 FACES DO PERDÃO' => [
        'title' => 'As 5 Faces do Perdão',
        'authors' => ['Rossandro Klinjey'],
        'publisher' => 'Letramais',
        'isbn' => '9788563808684',
        'publication_year' => 2017,
        'page_count' => 223,
        'source_name' => 'Sebo do Messias',
        'source_url' => 'https://sebodomessias.com.br/livro/auto-ajuda/as-5-faces-do-perdao-8',
        'confidence' => 1.0,
    ],
    'CAMINHO, VERDADE E VIDA (CAPA DURA)' => [
        'title' => 'Caminho, Verdade e Vida',
        'authors' => ['Francisco Cândido Xavier', 'Emmanuel (Espírito)'],
        'publisher' => 'FEB',
        'isbn' => '9786555704136',
        'page_count' => 400,
        'source_name' => 'Editora e Distribuidora Chico Xavier',
        'source_url' => 'https://www.editorachicoxavier.com.br/livros-revistas-hqis/caminho-verdade-e-vida-capa-dura',
        'confidence' => 1.0,
    ],
    'CRISTIANISMO: A MENSAGEM ESQUECIDA' => [
        'title' => 'Cristianismo: A Mensagem Esquecida',
        'authors' => ['Hermínio C. Miranda'],
        'publisher' => 'O Clarim',
        'isbn' => '9786588278208',
        'source_name' => 'O Clarim',
        'source_url' => 'https://www.oclarim.com.br/cristianismo-a-mensagem-esquecida/p',
        'confidence' => 1.0,
    ],
    'ABAIXO A DEPRESSÃO' => [
        'title' => 'Abaixo a Depressão',
        'authors' => ['Richard Simonetti'],
        'source_name' => 'Sampi / Jornal da Cidade',
        'source_url' => 'https://sampi.net.br/bauru/noticias/2586398/geral/2004/04/richard-simonetti-lanca-livro-sobre-depressao',
        'confidence' => 1.0,
    ],
    'A CONSTRUÇÃO DA CONSCIÊNCIA' => [
        'title' => 'A Construção da Consciência',
        'authors' => ['Ramatís', 'Mariléa de Castro'],
        'publisher' => 'Editora do Conhecimento',
        'isbn' => '9786557271254',
        'publication_year' => 2022,
        'page_count' => 132,
        'source_name' => 'Editora do Conhecimento',
        'source_url' => 'https://edconhecimento.com.br/wp-content/uploads/2022/02/A-Construcao-da-Consciencia-1-20.pdf',
        'confidence' => 1.0,
    ],
    'A PRATICA DO AMOR' => [
        'title' => 'A Prática do Amor',
        'authors' => ['Ronel Alvares Barbosa'],
        'publisher' => 'Nova Visão',
        'isbn' => '9786588033135',
        'publication_year' => 2022,
        'page_count' => 128,
        'source_name' => 'Google Books',
        'source_url' => 'https://books.google.com/books/about/A_Pr%C3%A1tica_do_Amor.html?id=x3X2zwEACAAJ',
        'confidence' => 1.0,
    ],
    'ALQUIMIA DA MENTE' => [
        'title' => 'Alquimia da Mente',
        'authors' => ['Hermínio Corrêa de Miranda'],
        'publisher' => 'Lachatre',
        'isbn' => '9788565518727',
        'publication_year' => 2021,
        'page_count' => 320,
        'source_name' => 'Editora e Distribuidora Chico Xavier',
        'source_url' => 'https://editoraedistribuidorachicoxavier.corpsuite.com.br/livros/alquimia-da-mente',
        'confidence' => 1.0,
    ],
    'ANTES QUE O GALO CANTE' => [
        'title' => 'Antes Que o Galo Cante',
        'authors' => ['Richard Simonetti'],
        'publisher' => 'CEAC',
        'isbn' => '9788586359446',
        'publication_year' => 2003,
        'page_count' => 160,
        'source_name' => 'Mensagem Espírita',
        'source_url' => 'https://www.mensagemespirita.com.br/livro/1808247/antes-que-o-galo-cante-richard-simonetti',
        'confidence' => 1.0,
    ],
    'CURE-SE E CURE PELOS PASSES' => [
        'title' => 'Cure-se e Cure pelos Passes',
        'authors' => ['Jacob Melo'],
        'source_name' => 'Centro Benção de Paz',
        'source_url' => 'https://centrobencaodepaz.com.br/upload/paginas/135216982527794710.pdf',
        'confidence' => 1.0,
    ],
    'DEPRESSÃO E MEDIUNIDADE' => [
        'title' => 'Depressão e Mediunidade',
        'authors' => ['Celio Alan Kardec', 'Jairo Avellar', 'Wander Luiz de Lemos', 'Wanderley Soares de Oliveira'],
        'source_name' => 'FEIG',
        'source_url' => 'https://www.feig.org.br/2021/06/11/depressao-e-mediunidade/',
        'confidence' => 1.0,
    ],
    'HOMOSSEXUALIDADE, REENCARNAÇÃO E VIDA MENTAL' => [
        'title' => 'Homossexualidade, Reencarnação e Vida Mental',
        'authors' => ['Walter Barcelos'],
        'source_name' => 'Didier',
        'source_url' => 'https://www.editoradidier.com.br/autores-diversos/walter-barcelos/homossexualidade-reencarnacao-e-vida-mental-walter-barcelos',
        'confidence' => 1.0,
    ],
    'MEDIUNIDADE SEM LÁGRIMAS' => [
        'title' => 'Mediunidade Sem Lágrimas',
        'authors' => ['Eliseu Rigonatti'],
        'source_name' => 'Livraria Pública',
        'source_url' => 'https://livrariapublica.com.br/autor/eliseu-rigonatti/',
        'confidence' => 1.0,
    ],
    'PINEAL, A GLÂNDULA DA VIDA ESPIRITUAL' => [
        'title' => 'Pineal, a Glândula da Vida Espiritual',
        'authors' => ['Eduardo Augusto Lourenço'],
        'source_name' => 'CEASA',
        'source_url' => 'https://ceasa.org.br/livraria/produto/pineal-a-glandula-da-vida-espiritual/',
        'confidence' => 1.0,
    ],
    'PRÁTICA ESPÍRITA' => [
        'title' => 'A Prática Mediúnica Espírita',
        'authors' => ['Manoel Philomeno de Miranda'],
        'source_name' => 'FEEGO Livraria',
        'source_url' => 'https://livraria.feego.org.br/collections/all/manoel-philomeno-de-miranda',
        'confidence' => 1.0,
    ],
    'QUEM FOI JESUS?' => [
        'title' => 'Quem foi Jesus - uma análise histórica e ecumênica',
        'authors' => ['André Marinho'],
        'publisher' => 'Editora Lachâtre',
        'page_count' => 300,
        'source_name' => 'Blog da ABPE',
        'source_url' => 'https://blogabpe.org/2018/09/09/quem-foi-jesus-um-livro-que-nos-fala-dessa-historia/',
        'confidence' => 1.0,
    ],
    'SALMOS DE REDENÇÃO' => [
        'title' => 'Salmos de Redenção',
        'authors' => ['Gilvanize Balbino Pereira', 'Ferdinando (Espírito)'],
        'source_name' => 'CE Bezerra de Menezes',
        'source_url' => 'https://www.cecbezerrademenezes.org.br/acervo_espirito.pdf',
        'confidence' => 1.0,
    ],
    'TRANSE E MEDIUNIDADE' => [
        'title' => 'Transe e Mediunidade',
        'authors' => ['Lamartine Palhano Jr.'],
        'source_name' => 'FEEES',
        'source_url' => 'https://lojadesdobra.feees.org.br/produtos/transe-e-mediunidade-lamartine-palhano-jr/',
        'confidence' => 1.0,
    ],
    'YVONNE PEREIRA: ENTRE CARTAS E RECORDAÇÕES' => [
        'title' => 'Yvonne Pereira: Entre Cartas e Recordações',
        'authors' => ['Lindomar Coutinho da Silva', 'Yvonne do Amaral Pereira'],
        'publisher' => 'Publicações Lachatre',
        'isbn' => '9788566960112',
        'source_name' => 'Livraria Cultura Espírita União',
        'source_url' => 'https://www.ceu.com.br/livro/yvonne-pereira-entre-cartas-e-recordacoes',
        'confidence' => 1.0,
    ],
    'PESCADORES DE ALMAS' => [
        'title' => 'Pescadores de Almas - A Arte que Cura e Transforma',
        'authors' => ['Walkiria Kaminski'],
        'source_name' => 'Livraria JP',
        'source_url' => 'https://www.livrariajp.com/livros/espiritismo/pescadores-de-almas/',
        'confidence' => 1.0,
    ],
    'AVES PEREGRINAS' => [
        'title' => 'Aves Peregrinas',
        'authors' => ['Graça Leão'],
        'publisher' => 'EME',
        'isbn' => '9788573533446',
        'page_count' => 255,
        'source_name' => 'Relatórios Biblivre',
        'source_url' => 'https://gecbem.org.in/wp-content/uploads/2025/09/Catalogo_Biblioteca-Set_2025.pdf',
        'confidence' => 1.0,
    ],
    'DIVERSIDADES DOS CARISMAS' => [
        'title' => 'Diversidade dos Carismas',
        'authors' => ['Hermínio C. Miranda'],
        'source_name' => 'MercadoLivre',
        'source_url' => 'https://produto.mercadolivre.com.br/MLB-2978474379-livro-diversidade-dos-carismas-herminio-c-miranda-_JM',
        'confidence' => 1.0,
    ],
    'CONEXÃO COM DEUS' => [
        'title' => 'O Poder da Conexão com Deus',
        'authors' => ['Marcilio Ruiz Bissoli'],
        'isbn' => '9788543704326',
        'source_name' => 'Editora Baraúna',
        'source_url' => 'https://www.editorabarauna.com.br/livro/religiao/o-poder-da-conexao-com-deus/',
        'confidence' => 1.0,
    ],
    'DIVALDO FRANCO' => [
        'title' => 'Divaldo Franco: Uma Vida com os Espíritos',
        'authors' => ['Suely Caldas Schubert'],
        'source_name' => 'Livraria Leal',
        'source_url' => 'https://m.livrarialeal.com.br/biografias-divaldo-franco/biografias/divaldo-franco-uma-vida-com-os-espiritos.html',
        'confidence' => 1.0,
    ],
    'ESPIRITISMO E REFORMA ÍNTIMA' => [
        'title' => 'Espiritismo e Reforma Íntima',
        'authors' => ['Rino Curti'],
        'publisher' => 'LAKE',
        'page_count' => 128,
        'source_name' => 'LAKE',
        'source_url' => 'https://www.lake.org.br/espiritismo-e-reforma-intima-1/p',
        'confidence' => 1.0,
    ],
    'EMMANUEL' => [
        'title' => 'Emmanuel',
        'authors' => ['Francisco Cândido Xavier'],
        'source_name' => 'Mensagem Espírita',
        'source_url' => 'https://www.mensagemespirita.com.br/livro/24888/emmanuel-francisco-candido-xavier',
        'confidence' => 1.0,
    ],
    'FILHO DE DEUS' => [
        'title' => 'Filho de Deus',
        'authors' => ['Divaldo Pereira Franco', 'Joanna de Ângelis'],
        'source_name' => 'Editora Dufaux',
        'source_url' => 'https://loja.editoradufaux.com.br/filho-de-deus',
        'confidence' => 1.0,
    ],
    'HÁ FLORES FLORES NO CAMINHO' => [
        'title' => 'Há Flores no Caminho',
        'authors' => ['Divaldo Pereira Franco', 'Amélia Rodrigues'],
        'source_name' => 'Caminheiros da Fraternidade',
        'source_url' => 'https://caminheirosdafraternidade.com.br/biblioteca/acervo/all/30',
        'confidence' => 1.0,
    ],
    'JUVENTUDE, SEXUALIDADE & ESPIRITISMO' => [
        'title' => 'Juventude, Sexualidade e Espiritismo',
        'authors' => ['Autores Diversos'],
        'page_count' => 220,
        'source_name' => 'Livros da Luz',
        'source_url' => 'https://livros-da-luz.webnode.page/products/juventude-sexualidade-e-espiritismo/',
        'confidence' => 1.0,
    ],
    'PARA RIR E REFLETIR' => [
        'title' => 'Para Rir e Refletir',
        'authors' => ['Richard Simonetti'],
        'publisher' => 'CEAC',
        'isbn' => '9788586359392',
        'source_name' => 'Bondfaro',
        'source_url' => 'https://www.bondfaro.com.br/livros/para-rir-e-refletir-richard-simonetti-9788586359392',
        'confidence' => 1.0,
    ],
    'QUAL É A SUA DOR?' => [
        'title' => 'Qual é a sua dor?',
        'authors' => ['Marlon Reikdal'],
        'source_name' => 'Marlon Reikdal',
        'source_url' => 'https://marlonreikdal.com.br/qual-e-a-sua-dor/',
        'confidence' => 1.0,
    ],
    'QUAL É O SEU VALOR?' => [
        'title' => 'Qual é o seu valor?',
        'authors' => ['Marlon Reikdal'],
        'publication_year' => 2023,
        'source_name' => 'Marlon Reikdal',
        'source_url' => 'https://marlonreikdal.com.br/qual-e-o-seu-valor/',
        'confidence' => 1.0,
    ],
    'ESTUDO SISTEMATIZADO DA DOUT. ESPÍRITA TOMO ÚNICO' => [
        'title' => 'Estudo Sistematizado da Doutrina Espírita - Tomo Único',
        'authors' => ['Federação Espírita Brasileira'],
        'source_name' => 'Casa de Ismael',
        'source_url' => 'https://www.casadeismael.org.br/wp-content/uploads/2022/11/2013_RELATORIO.pdf',
        'confidence' => 1.0,
    ],
    'O AMOR COMO SOLUÇÃO' => [
        'title' => 'O Amor como Solução',
        'authors' => ['Divaldo Pereira Franco', 'Joanna de Ângelis'],
        'source_name' => 'Leal Publisher',
        'source_url' => 'https://lealpublisher.com/es/products/o-amor-como-solucao',
        'confidence' => 1.0,
    ],
    'O EVANHELHO SEGUNDO MATHEUS' => [
        'title' => 'O Evangelho Segundo Mateus',
        'authors' => ['Luiz Roberto Mattos'],
        'publisher' => 'UICLAP',
        'publication_year' => 2021,
        'page_count' => 122,
        'source_name' => 'Loja Uiclap',
        'source_url' => 'https://loja.uiclap.com/titulo/ua11936/',
        'confidence' => 1.0,
    ],
    'O ESPIRITO E O PENSAMENTO' => [
        'title' => 'O Espírito e o Pensamento',
        'authors' => ['Eduardo Augusto Lourenço', 'Benedito (Espírito)'],
        'publisher' => 'Do Conhecimento',
        'isbn' => '9788576182016',
        'publication_year' => 2010,
        'page_count' => 252,
        'source_name' => 'Sebo do Messias',
        'source_url' => 'https://sebodomessias.com.br/livro/espiritismo/o-espirito-e-o-pensamento-1',
        'confidence' => 1.0,
    ],
    'ROTEIRO' => [
        'title' => 'Roteiro',
        'authors' => ['Francisco Cândido Xavier', 'Emmanuel (Espírito)'],
        'publisher' => 'FEB',
        'source_name' => 'FEB - Biblioteca',
        'source_url' => 'https://www.sistemas.febnet.org.br/site/biblioteca/ver.php?ano=&id=5453&palavra=francisco+candido+xavie&tipo=autores',
        'confidence' => 1.0,
    ],
    'COLEÇÃO: CURSOS E ESTUDOS - REUNIÕES MEDIÚNICAS' => [
        'title' => 'Reuniões Mediúnicas',
        'authors' => ['Therezinha Oliveira'],
        'publisher' => 'Allan Kardec Editora',
        'isbn' => '9788578000226',
        'source_name' => 'Allan Kardec Editora',
        'source_url' => 'https://allankardec.org.br/obras/reunioes-mediunicas/',
        'confidence' => 1.0,
    ],
    'SÉRIE PSICOLÓGIA JOANNA DE ANGÊLIS (A)' => [
        'title' => 'A série psicológica de Joanna de Ângelis: fundamentação teórica',
        'authors' => ['Núcleo de Psicologia e Espiritismo da AME-Brasil'],
        'publisher' => 'AME-Brasil Editora',
        'isbn' => '9786586740127',
        'publication_year' => 2022,
        'page_count' => 276,
        'source_name' => 'Editora AME Brasil',
        'source_url' => 'https://loja.amebrasil.org.br/p/a-serie-psicologica-de-joanna-de-angelis-fundamentacao-teorica/',
        'confidence' => 1.0,
    ],
];
const MANUAL_REVIEW_PROMOTIONS = [
    'A ARTE DE SEGUIR EM FRENTE',
    'CAMINHO VERDADE E VIDA Brochura',
    'O Porquê da Vida Edicel',
    'ELUCIDAÇÕES PSICOLÓGICAS',
    'NA CORTINA DO TEMPO',
    'MENTE SAUDÁVEL, VIDA SERENA',
    'O EVANGELHO POR EMMANUEL - CARTAS UNIVVERSAIS E AO APOCALIPSE',
    'O EVANHELHO SEGUNDO MARCOS',
    'Na seara do mestre - Vinícius',
    'ANDRÉ LUIZ E SUAS NOVAS REVELAÇÕES',
    'O LIVRO DOS ESPÍRITOS (BESOUROLUX)',
    'O EVANGELHO SEGUNDO O ESPIRITISMO - BESOUBOX - NORMAL',
    'TEM ESPÍTITOS NO BANHEIRO?',
    'Preces Espíritas do Evangelho - Boa Nova',
    'LIVRO O AMOR JAMAIS TE ESQUECE',
    'LIVRO A CASA DO PENHASCO',
    'PALAVRAS DA VIDA ETERNA (BOLSO)',
    'O LIVRO DOS MÉDIUNS (BESOUROLUX)',
    'DIRETRIZES DE SEGURANÇA - MEDIUNIDADE',
    'Entre o Amor e a Guerra - Nova Edição',
    'SEXO E CONCIÊNCIA',
    'O CÉU E O INFERNO (BESOUROLUX)',
    'LV-SEXO E OBSESSAO',
    'LIVRO OS PRAZERES DA ALMA',
    'O olhar e as percepções da alma - Lucy Dias Ramos',
    'MILITARES DO ALÉM',
    'EVOLUIR É SIMPLES, NÓS É QUE COMPRICAMOS',
    'O EVANGELHO SEGUNDO O ESPIRITISMO - FEB LETRAS GRANDES',
    'O EVANGELHO SEGUNDO O ESPIRITISMO - EME PROMOCIONAL',
    'O CEU E O IN FERNO',
    'LIVRO PALCO DAS ENCARNACOES',
    'NO INVISÍVEL - EDICEL',
    'O Evangelho Redivivo - Livro V',
    'O LIVRO DOS ESPÍRITOS - LETRAS GRANDES',
    'Analisando as Traduções Bíblicas - 13ª Ed. 2009',
    'O Evangelho Redivivo - Livro VII',
    'O LIVRO DOS MÉDIUNS - EME PROMOCIONAL',
    'Espinhos do Tempo - Nova Edição',
    'VIOLETAS NA JANELA - 47 EDIÇÃO',
    'CURA E AUTOCURA',
    'A ARTE DO REENCONTRO',
    'A IRMÃ DO VIZIR',
    'ANTES QUE A MORTE NOS SEPARE',
    'AS VIDAS DE VIRGÍNIA',
    'Colorindo o evangelho - livro de colorir Capa comum - 18 maio 2015',
    'Colorindo o evangelho - Volume II - livro de colorir Capa comum - 20 fevereiro 2017',
    'CONTOS DESTA E DE OUTRA VIDA',
    'CURA-TE A TI MESMO',
    'DESPEDINDO - SE DA TERRA - ANDRE LUIZ RUIZ',
    'INSTRUÇÕES PRÁTICAS SOBRE AS MANIFESTAÇÕES DOS ESPÍRITOS',
    'Laços Eternos - Edição Especial',
    'Lar, Alicerce de Amor - Lucy Dias Ramos',
    'LIVRO FILHO ADOTIVO',
    'Meu Livrinho de Orações',
    'MOTOQUEIROS DO ALÉM',
    'Nas Voragens do Pecado - Yvonne A. Pereira',
    'O CEU E O INFERNO - LETRAS GIGANTES',
    'O LIVRINHO DOS EPÍRITOS',
    'O Novo Testamento - Aroldo Dutra',
    'Recordações da Mediunidade - Yvonne A. Pereira',
    'ROTEIRO SISTEMATIZADO LIVRO - EVANGELHO',
    'SOS FAMÍLIA',
    'UM INFINITO RENASCER',
];

$options = parseOptions($argv);

if ($options['help']) {
    renderHelp();
    exit(0);
}

ensureDirectory(dirname($options['output']));
ensureDirectory(dirname($options['review']));
ensureDirectory(dirname($options['json']));
ensureDirectory($options['cache_dir']);

$titles = readTitlesFromXlsx($options['input']);

if ($options['limit'] > 0) {
    $titles = array_slice($titles, 0, $options['limit']);
}

$rowsForImport = [];
$rowsForReview = [];
$fullResults = [];

foreach ($titles as $index => $rawTitle) {
    $prepared = prepareTitle($rawTitle);

    if (shouldSkipTitle((string) ($prepared['raw_title'] ?? ''))) {
        continue;
    }

    $result = resolveBookMetadata(
        $prepared,
        $options['cache_dir'],
        $options['sleep_ms'],
        $options['match_threshold'],
        $options['review_threshold']
    );
    $fullResults[] = $result;

    if (($result['confidence'] ?? 0.0) >= $options['match_threshold']) {
        $importRow = buildImportRow($index + 1, $prepared, $result);

        if (importRowHasRequiredFields($importRow)) {
            $rowsForImport[] = $importRow;
            continue;
        }

        $rowsForReview[] = buildReviewRow($prepared, $result, 'revisar-campos-obrigatorios');
        continue;
    }

    if (($result['confidence'] ?? 0.0) >= $options['review_threshold']) {
        $rowsForReview[] = buildReviewRow($prepared, $result, 'revisar');
        continue;
    }

    $rowsForReview[] = buildReviewRow($prepared, $result, 'sem-match-forte');
}

writeCsv(
    $options['output'],
    [
        'sku',
        'slug',
        'title',
        'subtitle',
        'author_name',
        'publisher_name',
        'isbn',
        'barcode',
        'edition_label',
        'volume_number',
        'volume_label',
        'publication_year',
        'page_count',
        'language',
        'description',
        'sale_price',
        'stock_minimum',
        'status',
        'location_label',
        'category_name',
        'genre_name',
        'collection_name',
        'source_name',
        'source_url',
        'confidence',
        'raw_title',
        'search_title',
        'author_hint',
    ],
    $rowsForImport
);

writeCsv(
    $options['review'],
    [
        'status',
        'raw_title',
        'search_title',
        'author_hint',
        'best_title',
        'best_subtitle',
        'best_authors',
        'best_publisher',
        'best_isbn',
        'best_year',
        'best_page_count',
        'source_name',
        'source_url',
        'confidence',
        'source_query',
    ],
    $rowsForReview
);

file_put_contents(
    $options['json'],
    json_encode([
        'generated_at' => gmdate('c'),
        'input' => $options['input'],
        'processed_titles' => count($titles),
        'matched_rows' => count($rowsForImport),
        'review_rows' => count($rowsForReview),
        'match_threshold' => $options['match_threshold'],
        'review_threshold' => $options['review_threshold'],
        'results' => $fullResults,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "Seed gerado com sucesso.\n";
echo 'Titulos processados: ' . count($titles) . "\n";
echo 'Rows prontas para importacao: ' . count($rowsForImport) . "\n";
echo 'Rows para revisao: ' . count($rowsForReview) . "\n";
echo 'CSV importavel: ' . $options['output'] . "\n";
echo 'CSV revisao: ' . $options['review'] . "\n";
echo 'JSON auditoria: ' . $options['json'] . "\n";

/**
 * @return array{input: string, output: string, review: string, json: string, cache_dir: string, limit: int, sleep_ms: int, help: bool, match_threshold: float, review_threshold: float}
 */
function parseOptions(array $argv): array
{
    $options = [
        'input' => realpath(DEFAULT_INPUT_PATH) ?: DEFAULT_INPUT_PATH,
        'output' => DEFAULT_OUTPUT_PATH,
        'review' => DEFAULT_REVIEW_PATH,
        'json' => DEFAULT_JSON_PATH,
        'cache_dir' => DEFAULT_CACHE_DIR,
        'limit' => 0,
        'sleep_ms' => DEFAULT_SLEEP_MS,
        'help' => false,
        'match_threshold' => DEFAULT_MATCH_THRESHOLD,
        'review_threshold' => DEFAULT_REVIEW_THRESHOLD,
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }

        if (strpos($argument, '--input=') === 0) {
            $options['input'] = substr($argument, 8);
            continue;
        }

        if (strpos($argument, '--output=') === 0) {
            $options['output'] = substr($argument, 9);
            continue;
        }

        if (strpos($argument, '--review=') === 0) {
            $options['review'] = substr($argument, 9);
            continue;
        }

        if (strpos($argument, '--json=') === 0) {
            $options['json'] = substr($argument, 7);
            continue;
        }

        if (strpos($argument, '--cache-dir=') === 0) {
            $options['cache_dir'] = substr($argument, 12);
            continue;
        }

        if (strpos($argument, '--limit=') === 0) {
            $options['limit'] = max(0, (int) substr($argument, 8));
            continue;
        }

        if (strpos($argument, '--sleep-ms=') === 0) {
            $options['sleep_ms'] = max(0, (int) substr($argument, 11));
            continue;
        }

        if (strpos($argument, '--match-threshold=') === 0) {
            $options['match_threshold'] = max(0.0, min(1.0, (float) substr($argument, 18)));
            continue;
        }

        if (strpos($argument, '--review-threshold=') === 0) {
            $options['review_threshold'] = max(0.0, min(1.0, (float) substr($argument, 19)));
            continue;
        }

        fwrite(STDERR, "Opcao invalida: {$argument}\n");
        exit(1);
    }

    if (!is_file($options['input'])) {
        fwrite(STDERR, "Arquivo de entrada nao encontrado: {$options['input']}\n");
        exit(1);
    }

    return $options;
}

function renderHelp(): void
{
    echo "Uso:\n";
    echo "  php scripts/build_bookshop_seed_from_xlsx.php [opcoes]\n\n";
    echo "Opcoes:\n";
    echo "  --input=/caminho/arquivo.xlsx\n";
    echo "  --output=/caminho/seed.csv\n";
    echo "  --review=/caminho/revisao.csv\n";
    echo "  --json=/caminho/auditoria.json\n";
    echo "  --cache-dir=/caminho/cache\n";
    echo "  --limit=100\n";
    echo "  --sleep-ms=120\n";
    echo "  --match-threshold=0.82\n";
    echo "  --review-threshold=0.64\n";
}

/**
 * @return array<int, string>
 */
function readTitlesFromXlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Nao foi possivel abrir o XLSX.');
    }

    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml !== false) {
        $sharedStrings = parseSharedStrings($sharedStringsXml);
    }

    $workbookRelsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $workbookXml = $zip->getFromName('xl/workbook.xml');

    if ($workbookRelsXml === false || $workbookXml === false) {
        $zip->close();
        throw new RuntimeException('Estrutura interna do XLSX invalida.');
    }

    $sheetPath = resolveFirstWorksheetPath($workbookXml, $workbookRelsXml);
    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Planilha principal nao encontrada dentro do XLSX.');
    }

    $rows = extractWorksheetRows($sheetXml, $sharedStrings);
    $titles = [];

    foreach ($rows as $row) {
        $title = trim((string) ($row[0] ?? ''));
        if ($title === '') {
            continue;
        }

        $titles[] = $title;
    }

    return array_values(array_unique($titles));
}

/**
 * @return array<int, string>
 */
function parseSharedStrings(string $xml): array
{
    $document = new DOMDocument();
    $document->loadXML($xml);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $strings = [];

    foreach ($xpath->query('//a:si') as $item) {
        $text = '';

        foreach ($xpath->query('.//a:t', $item) as $textNode) {
            $text .= $textNode->textContent;
        }

        $strings[] = $text;
    }

    return $strings;
}

function resolveFirstWorksheetPath(string $workbookXml, string $relsXml): string
{
    $workbook = new DOMDocument();
    $workbook->loadXML($workbookXml);
    $workbookXpath = new DOMXPath($workbook);
    $workbookXpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbookXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $firstSheet = $workbookXpath->query('//a:sheets/a:sheet')->item(0);
    if (!$firstSheet instanceof DOMElement) {
        throw new RuntimeException('Nenhuma aba foi encontrada no XLSX.');
    }

    $relationId = (string) $firstSheet->getAttributeNS(
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
        'id'
    );

    $rels = new DOMDocument();
    $rels->loadXML($relsXml);
    $relsXpath = new DOMXPath($rels);
    $relsXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

    foreach ($relsXpath->query('//r:Relationship') as $relationship) {
        if (!$relationship instanceof DOMElement) {
            continue;
        }

        if ((string) $relationship->getAttribute('Id') !== $relationId) {
            continue;
        }

        $target = ltrim((string) $relationship->getAttribute('Target'), '/');

        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
    }

    throw new RuntimeException('Nao foi possivel resolver a primeira aba do XLSX.');
}

/**
 * @param array<int, string> $sharedStrings
 * @return array<int, array<int, string>>
 */
function extractWorksheetRows(string $xml, array $sharedStrings): array
{
    $document = new DOMDocument();
    $document->loadXML($xml);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = [];

    foreach ($xpath->query('//a:sheetData/a:row') as $rowNode) {
        $row = [];

        foreach ($xpath->query('./a:c', $rowNode) as $cellNode) {
            if (!$cellNode instanceof DOMElement) {
                continue;
            }

            $cellRef = (string) $cellNode->getAttribute('r');
            $columnIndex = columnLettersToIndex($cellRef);
            $cellType = (string) $cellNode->getAttribute('t');
            $valueNode = $xpath->query('./a:v', $cellNode)->item(0);

            $value = '';

            if ($cellType === 's' && $valueNode !== null) {
                $sharedIndex = (int) $valueNode->textContent;
                $value = $sharedStrings[$sharedIndex] ?? '';
            } elseif ($cellType === 'inlineStr') {
                foreach ($xpath->query('./a:is//a:t', $cellNode) as $textNode) {
                    $value .= $textNode->textContent;
                }
            } elseif ($valueNode !== null) {
                $value = $valueNode->textContent;
            }

            $row[$columnIndex] = trim($value);
        }

        if ($row !== []) {
            ksort($row);
            $rows[] = array_values($row);
        }
    }

    return $rows;
}

function columnLettersToIndex(string $cellReference): int
{
    $letters = '';

    for ($i = 0; $i < strlen($cellReference); $i++) {
        $char = $cellReference[$i];

        if (ctype_alpha($char)) {
            $letters .= strtoupper($char);
            continue;
        }

        break;
    }

    $index = 0;

    for ($i = 0; $i < strlen($letters); $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }

    return max(0, $index - 1);
}

/**
 * @return array<string, string>
 */
function prepareTitle(string $rawTitle): array
{
    $trimmed = trim(preg_replace('/\s+/', ' ', $rawTitle) ?? $rawTitle);
    $normalizedTitle = stripParenthesizedNoise($trimmed);
    $searchTitle = $normalizedTitle;
    $authorHint = '';
    $segments = array_values(array_filter(array_map('trim', explode(' - ', $normalizedTitle))));

    if (count($segments) >= 3) {
        $lastSegment = (string) end($segments);

        if (segmentLooksLikeAuthor($lastSegment)) {
            array_pop($segments);
            $searchTitle = implode(' - ', $segments);
            $authorHint = cleanupAuthorHint($lastSegment);
        } elseif (segmentLooksLikePublisherMarker($lastSegment)) {
            array_pop($segments);
            $searchTitle = implode(' - ', $segments);
        }
    } elseif (count($segments) === 2) {
        [$left, $right] = $segments;

        if (segmentLooksLikeAuthor($right)) {
            $searchTitle = $left;
            $authorHint = cleanupAuthorHint($right);
        } elseif (segmentLooksLikePublisherMarker($right)) {
            $searchTitle = $left;
        }
    }

    $searchTitle = cleanSearchTitle($searchTitle);

    return [
        'raw_title' => $trimmed,
        'search_title' => $searchTitle,
        'author_hint' => $authorHint,
        'search_variants' => buildSearchVariants($searchTitle, $trimmed),
    ];
}

function stripParenthesizedNoise(string $value): string
{
    $cleaned = preg_replace('/\s*\((?:isbn[:\s]*)?[0-9xX-]{10,17}\)\s*/u', ' ', $value) ?? $value;
    $cleaned = preg_replace('/\s*\((bolso|nova edicao|nova edição|edicao|edição)\)\s*/iu', ' ', $cleaned) ?? $cleaned;
    $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;

    return trim($cleaned);
}

function segmentLooksLikeAuthor(string $segment): bool
{
    $normalized = trim($segment);
    $normalized = preg_replace('/\s*\((?:isbn[:\s]*)?[0-9xX-]{10,17}\)\s*/u', '', $normalized) ?? $normalized;

    if ($normalized === '') {
        return false;
    }

    if (strpos($normalized, ',') !== false) {
        return true;
    }

    return false;
}

function segmentLooksLikePublisherMarker(string $segment): bool
{
    $normalized = normalizeForMatch($segment);

    if ($normalized === '') {
        return false;
    }

    if (in_array($normalized, ['feb', 'ide', 'ed ide', 'bolso', 'pa', 'cei'], true)) {
        return true;
    }

    return preg_match('/^(ed|editora)\b/', $normalized) === 1;
}

function cleanupAuthorHint(string $value): string
{
    $cleaned = preg_replace('/\s*\((?:isbn[:\s]*)?[0-9xX-]{10,17}\)\s*/u', '', $value) ?? $value;
    $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;

    return trim($cleaned, " \t\n\r\0\x0B-");
}

function cleanSearchTitle(string $value): string
{
    $cleaned = stripParenthesizedNoise($value);
    $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;

    return trim($cleaned, " \t\n\r\0\x0B-");
}

/**
 * @return array<int, string>
 */
function buildSearchVariants(string $searchTitle, string $rawTitle): array
{
    $variants = [];
    $base = cleanSearchTitle($searchTitle);

    if ($base !== '') {
        $variants[] = $base;
    }

    $withoutPublisherSuffix = preg_replace('/\s+-\s+(feb|ide|ed ide|editora .+)$/iu', '', $base) ?? $base;
    if (trim($withoutPublisherSuffix) !== '') {
        $variants[] = trim($withoutPublisherSuffix);
    }

    $withoutSubtitleAfterColon = preg_replace('/\s*:\s*.*/u', '', $base) ?? $base;
    if (trim($withoutSubtitleAfterColon) !== '') {
        $variants[] = trim($withoutSubtitleAfterColon);
    }

    $withoutCommaSuffix = preg_replace('/\s*,\s*.+$/u', '', $base) ?? $base;
    if (trim($withoutCommaSuffix) !== '') {
        $variants[] = trim($withoutCommaSuffix);
    }

    $rawNormalized = cleanSearchTitle(stripParenthesizedNoise($rawTitle));
    if ($rawNormalized !== '' && $rawNormalized !== $base) {
        $variants[] = $rawNormalized;
    }

    return array_values(array_unique(array_filter($variants, static fn (string $variant): bool => $variant !== '')));
}

/**
 * @param array<string, string> $preparedTitle
 * @return array<string, mixed>
 */
function resolveBookMetadata(
    array $preparedTitle,
    string $cacheDir,
    int $sleepMs,
    float $matchThreshold,
    float $reviewThreshold
): array {
    $searchTitle = (string) $preparedTitle['search_title'];
    $authorHint = (string) $preparedTitle['author_hint'];
    $searchVariants = array_values(array_unique(array_filter(array_map(
        static fn (mixed $value): string => trim((string) $value),
        (array) ($preparedTitle['search_variants'] ?? [$searchTitle])
    ))));

    $googleCandidates = [];
    foreach ($searchVariants as $variant) {
        $googleCandidates = array_merge(
            $googleCandidates,
            searchGoogleBooks($variant, $authorHint, $cacheDir, $sleepMs, 'exact_title')
        );
    }
    $googleBest = bestCandidate($googleCandidates);

    if ($authorHint !== '' && (float) ($googleBest['confidence'] ?? 0.0) < $reviewThreshold) {
        foreach ($searchVariants as $variant) {
            $googleCandidates = array_merge(
                $googleCandidates,
                searchGoogleBooks($variant, '', $cacheDir, $sleepMs, 'exact_title')
            );
        }
        $googleBest = bestCandidate($googleCandidates);
    }

    if ((float) ($googleBest['confidence'] ?? 0.0) < $reviewThreshold) {
        foreach ($searchVariants as $variant) {
            $googleCandidates = array_merge(
                $googleCandidates,
                searchGoogleBooks($variant, $authorHint, $cacheDir, $sleepMs, 'broad')
            );
        }
        $googleBest = bestCandidate($googleCandidates);
    }

    if ($authorHint !== '' && (float) ($googleBest['confidence'] ?? 0.0) < $reviewThreshold) {
        foreach ($searchVariants as $variant) {
            $googleCandidates = array_merge(
                $googleCandidates,
                searchGoogleBooks($variant, '', $cacheDir, $sleepMs, 'broad')
            );
        }
        $googleBest = bestCandidate($googleCandidates);
    }

    $openLibraryCandidates = [];

    if ((float) ($googleBest['confidence'] ?? 0.0) < $matchThreshold) {
        foreach ($searchVariants as $variant) {
            $openLibraryCandidates = array_merge(
                $openLibraryCandidates,
                searchOpenLibrary($variant, $authorHint, $cacheDir, $sleepMs, 'title')
            );
        }
        $openLibraryBest = bestCandidate($openLibraryCandidates);

        if ($authorHint !== '' && (float) ($openLibraryBest['confidence'] ?? 0.0) < $reviewThreshold) {
            foreach ($searchVariants as $variant) {
                $openLibraryCandidates = array_merge(
                    $openLibraryCandidates,
                    searchOpenLibrary($variant, '', $cacheDir, $sleepMs, 'title')
                );
            }
            $openLibraryBest = bestCandidate($openLibraryCandidates);
        }

        if ((float) ($openLibraryBest['confidence'] ?? 0.0) < $reviewThreshold) {
            foreach ($searchVariants as $variant) {
                $openLibraryCandidates = array_merge(
                    $openLibraryCandidates,
                    searchOpenLibrary($variant, $authorHint, $cacheDir, $sleepMs, 'general')
                );
            }
            $openLibraryBest = bestCandidate($openLibraryCandidates);
        }

        if ($authorHint !== '' && (float) ($openLibraryBest['confidence'] ?? 0.0) < $reviewThreshold) {
            foreach ($searchVariants as $variant) {
                $openLibraryCandidates = array_merge(
                    $openLibraryCandidates,
                    searchOpenLibrary($variant, '', $cacheDir, $sleepMs, 'general')
                );
            }
        }
    }

    $allCandidates = deduplicateCandidates(array_merge($googleCandidates, $openLibraryCandidates));

    usort($allCandidates, static fn (array $left, array $right): int => ($right['confidence'] <=> $left['confidence']));

    $best = bestCandidate($allCandidates);
    if ($best !== null && (array) ($best['authors'] ?? []) === [] && (float) ($best['confidence'] ?? 0.0) >= 0.90) {
        $best = recoverMissingAuthorsFromGoogleBooks($best, $cacheDir, $sleepMs);
    }

    $override = resolveManualMetadataOverride((string) ($preparedTitle['raw_title'] ?? ''));
    if ($override !== null) {
        $best = mergeManualMetadataOverride($best, $override);
    }

    if (shouldPromoteReviewMatch((string) ($preparedTitle['raw_title'] ?? ''), $best)) {
        $best['confidence'] = max((float) ($best['confidence'] ?? 0.0), 1.0);
    }

    return [
        'raw_title' => $preparedTitle['raw_title'],
        'search_title' => $searchTitle,
        'author_hint' => $authorHint,
        'confidence' => (float) ($best['confidence'] ?? 0.0),
        'best_match' => $best,
        'candidates' => array_slice($allCandidates, 0, 5),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function resolveManualMetadataOverride(string $rawTitle): ?array
{
    $normalizedRawTitle = mb_strtoupper(trim($rawTitle), 'UTF-8');

    return MANUAL_METADATA_OVERRIDES[$normalizedRawTitle] ?? null;
}

function shouldPromoteReviewMatch(string $rawTitle, ?array $bestMatch): bool
{
    if ($bestMatch === null) {
        return false;
    }

    if ((array) ($bestMatch['authors'] ?? []) === []) {
        return false;
    }

    $normalizedRawTitle = trim($rawTitle);

    return in_array($normalizedRawTitle, MANUAL_REVIEW_PROMOTIONS, true);
}

function shouldSkipTitle(string $rawTitle): bool
{
    $normalizedRawTitle = mb_strtoupper(trim($rawTitle), 'UTF-8');

    return in_array($normalizedRawTitle, MANUAL_SKIPPED_TITLES, true);
}

/**
 * @param array<string, mixed>|null $bestMatch
 * @param array<string, mixed> $override
 * @return array<string, mixed>
 */
function mergeManualMetadataOverride(?array $bestMatch, array $override): array
{
    $base = $bestMatch ?? [
        'source_name' => '',
        'source_url' => '',
        'source_query' => '',
        'title' => '',
        'subtitle' => '',
        'authors' => [],
        'publisher' => '',
        'isbn' => '',
        'barcode' => '',
        'publication_year' => null,
        'page_count' => null,
        'language' => '',
        'description' => '',
        'confidence' => 0.0,
    ];

    foreach (['title', 'subtitle', 'publisher', 'isbn', 'barcode', 'language', 'description', 'source_name', 'source_url'] as $field) {
        if (isset($override[$field]) && trim((string) $override[$field]) !== '') {
            $base[$field] = $override[$field];
        }
    }

    if (isset($override['authors']) && (array) $override['authors'] !== []) {
        $base['authors'] = array_values(array_filter(array_map('strval', (array) $override['authors'])));
    }

    foreach (['publication_year', 'page_count'] as $field) {
        if (isset($override[$field]) && $override[$field] !== null && $override[$field] !== '') {
            $base[$field] = $override[$field];
        }
    }

    $base['confidence'] = max((float) ($base['confidence'] ?? 0.0), (float) ($override['confidence'] ?? 1.0));

    return $base;
}

/**
 * @return array<int, array<string, mixed>>
 */
function searchGoogleBooks(string $title, string $authorHint, string $cacheDir, int $sleepMs, string $queryMode): array
{
    $query = $queryMode === 'broad'
        ? trim($title . ' ' . $authorHint)
        : 'intitle:"' . $title . '"' . ($authorHint !== '' ? ' inauthor:"' . $authorHint . '"' : '');

    if ($query === '') {
        return [];
    }

    $url = 'https://www.googleapis.com/books/v1/volumes?q=' . rawurlencode($query)
        . '&langRestrict=pt&maxResults=5&printType=books';
    $payload = fetchJson($url, $cacheDir, 'google_' . sha1($url), $sleepMs);

    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $candidates = [];

    foreach ($items as $item) {
        $volume = is_array($item['volumeInfo'] ?? null) ? $item['volumeInfo'] : [];
        $industryIdentifiers = is_array($volume['industryIdentifiers'] ?? null)
            ? $volume['industryIdentifiers']
            : [];

        $isbn13 = '';
        $isbn10 = '';

        foreach ($industryIdentifiers as $identifier) {
            if (!is_array($identifier)) {
                continue;
            }

            $type = (string) ($identifier['type'] ?? '');
            $value = normalizeIsbn((string) ($identifier['identifier'] ?? ''));

            if ($type === 'ISBN_13' && $value !== '') {
                $isbn13 = $value;
            }

            if ($type === 'ISBN_10' && $value !== '') {
                $isbn10 = $value;
            }
        }

        $candidate = [
            'source_name' => 'Google Books',
            'source_url' => (string) ($volume['infoLink'] ?? $item['selfLink'] ?? ''),
            'source_query' => $query,
            'title' => trim((string) ($volume['title'] ?? '')),
            'subtitle' => trim((string) ($volume['subtitle'] ?? '')),
            'authors' => array_values(array_filter(array_map('strval', (array) ($volume['authors'] ?? [])))),
            'publisher' => trim((string) ($volume['publisher'] ?? '')),
            'isbn' => $isbn13 !== '' ? $isbn13 : $isbn10,
            'barcode' => $isbn13,
            'publication_year' => extractYear((string) ($volume['publishedDate'] ?? '')),
            'page_count' => isset($volume['pageCount']) ? (int) $volume['pageCount'] : null,
            'language' => normalizeLanguageCode((string) ($volume['language'] ?? '')),
            'description' => trim((string) ($volume['description'] ?? '')),
        ];

        $candidate['confidence'] = scoreCandidate($title, $authorHint, $candidate['title'], $candidate['subtitle'], $candidate['authors']);
        $candidates[] = $candidate;
    }

    return $candidates;
}

/**
 * @param array<string, mixed> $candidate
 * @return array<string, mixed>
 */
function recoverMissingAuthorsFromGoogleBooks(array $candidate, string $cacheDir, int $sleepMs): array
{
    $title = trim((string) ($candidate['title'] ?? ''));
    if ($title === '') {
        return $candidate;
    }

    $recoveryCandidates = searchGoogleBooks($title, '', $cacheDir, $sleepMs, 'broad');
    $recoveryCandidates = array_values(array_filter(
        $recoveryCandidates,
        static function (array $recoveryCandidate) use ($title): bool {
            if ((array) ($recoveryCandidate['authors'] ?? []) === []) {
                return false;
            }

            return scoreCandidate(
                $title,
                '',
                (string) ($recoveryCandidate['title'] ?? ''),
                (string) ($recoveryCandidate['subtitle'] ?? ''),
                (array) ($recoveryCandidate['authors'] ?? [])
            ) >= 0.88;
        }
    ));

    $recoveryBest = bestCandidate($recoveryCandidates);
    if ($recoveryBest === null) {
        return $candidate;
    }

    $candidate['authors'] = $recoveryBest['authors'];

    foreach (['publisher', 'isbn', 'barcode', 'publication_year', 'page_count', 'language', 'description'] as $field) {
        $currentValue = $candidate[$field] ?? null;
        $replacementValue = $recoveryBest[$field] ?? null;

        if (($currentValue === null || $currentValue === '' || $currentValue === []) && $replacementValue !== null && $replacementValue !== '' && $replacementValue !== []) {
            $candidate[$field] = $replacementValue;
        }
    }

    return $candidate;
}

/**
 * @return array<int, array<string, mixed>>
 */
function searchOpenLibrary(string $title, string $authorHint, string $cacheDir, int $sleepMs, string $queryMode): array
{
    if ($queryMode === 'general') {
        $query = trim($title . ' ' . $authorHint);
        if ($query === '') {
            return [];
        }

        $url = 'https://openlibrary.org/search.json?q=' . rawurlencode($query) . '&limit=5';
    } else {
        $url = 'https://openlibrary.org/search.json?title=' . rawurlencode($title) . '&limit=5';

        if ($authorHint !== '') {
            $url .= '&author=' . rawurlencode($authorHint);
        }
    }

    $payload = fetchJson($url, $cacheDir, 'openlibrary_' . sha1($url), $sleepMs);
    $docs = is_array($payload['docs'] ?? null) ? $payload['docs'] : [];
    $candidates = [];

    foreach ($docs as $doc) {
        if (!is_array($doc)) {
            continue;
        }

        $isbnValues = array_values(array_filter(array_map('normalizeIsbn', (array) ($doc['isbn'] ?? []))));
        $isbn13 = '';
        $isbn10 = '';

        foreach ($isbnValues as $isbn) {
            if (strlen($isbn) === 13 && $isbn13 === '') {
                $isbn13 = $isbn;
            }

            if (strlen($isbn) === 10 && $isbn10 === '') {
                $isbn10 = $isbn;
            }
        }

        $publishers = array_values(array_filter(array_map('strval', (array) ($doc['publisher'] ?? []))));
        $authors = array_values(array_filter(array_map('strval', (array) ($doc['author_name'] ?? []))));
        $workKey = (string) ($doc['key'] ?? '');

        if ($authors === [] && $workKey !== '') {
            $authors = fetchOpenLibraryWorkAuthors($workKey, $cacheDir, $sleepMs);
        }

        $candidate = [
            'source_name' => 'Open Library',
            'source_url' => 'https://openlibrary.org' . $workKey,
            'source_query' => $queryMode === 'general' ? trim($title . ' ' . $authorHint) : $title,
            'title' => trim((string) ($doc['title'] ?? '')),
            'subtitle' => '',
            'authors' => $authors,
            'publisher' => trim((string) ($publishers[0] ?? '')),
            'isbn' => $isbn13 !== '' ? $isbn13 : $isbn10,
            'barcode' => $isbn13,
            'publication_year' => isset($doc['first_publish_year']) ? (int) $doc['first_publish_year'] : null,
            'page_count' => isset($doc['number_of_pages_median']) ? (int) $doc['number_of_pages_median'] : null,
            'language' => normalizeOpenLibraryLanguage((array) ($doc['language'] ?? [])),
            'description' => '',
        ];

        $candidate['confidence'] = scoreCandidate($title, $authorHint, $candidate['title'], '', $candidate['authors']);
        $candidates[] = $candidate;
    }

    return $candidates;
}

/**
 * @return array<int, string>
 */
function fetchOpenLibraryWorkAuthors(string $workKey, string $cacheDir, int $sleepMs): array
{
    $workPath = trim($workKey);
    if ($workPath === '' || !str_starts_with($workPath, '/works/')) {
        return [];
    }

    $workPayload = fetchJson('https://openlibrary.org' . $workPath . '.json', $cacheDir, 'openlibrary_work_' . sha1($workPath), $sleepMs);
    $authors = [];

    foreach ((array) ($workPayload['authors'] ?? []) as $authorEntry) {
        if (!is_array($authorEntry)) {
            continue;
        }

        $authorKey = trim((string) ($authorEntry['author']['key'] ?? ''));
        if ($authorKey === '' || !str_starts_with($authorKey, '/authors/')) {
            continue;
        }

        $authorPayload = fetchJson(
            'https://openlibrary.org' . $authorKey . '.json',
            $cacheDir,
            'openlibrary_author_' . sha1($authorKey),
            $sleepMs
        );
        $authorName = trim((string) ($authorPayload['name'] ?? ''));

        if ($authorName !== '') {
            $authors[] = $authorName;
        }
    }

    return array_values(array_unique($authors));
}

/**
 * @param array<int, array<string, mixed>> $candidates
 * @return array<string, mixed>|null
 */
function bestCandidate(array $candidates): ?array
{
    if ($candidates === []) {
        return null;
    }

    usort($candidates, static fn (array $left, array $right): int => ($right['confidence'] <=> $left['confidence']));

    return $candidates[0] ?? null;
}

/**
 * @param array<int, array<string, mixed>> $candidates
 * @return array<int, array<string, mixed>>
 */
function deduplicateCandidates(array $candidates): array
{
    $unique = [];

    foreach ($candidates as $candidate) {
        $key = implode('|', [
            (string) ($candidate['source_name'] ?? ''),
            (string) ($candidate['source_url'] ?? ''),
            normalizeForMatch((string) ($candidate['title'] ?? '')),
            (string) ($candidate['isbn'] ?? ''),
        ]);
        $confidence = (float) ($candidate['confidence'] ?? 0.0);

        if (!isset($unique[$key]) || $confidence > (float) ($unique[$key]['confidence'] ?? 0.0)) {
            $unique[$key] = $candidate;
        }
    }

    return array_values($unique);
}

/**
 * @return array<string, mixed>
 */
function fetchJson(string $url, string $cacheDir, string $cacheKey, int $sleepMs): array
{
    $cachePath = rtrim($cacheDir, '/\\') . '/' . $cacheKey . '.json';

    if (is_file($cachePath)) {
        $cached = json_decode((string) file_get_contents($cachePath), true);

        return is_array($cached) ? $cached : [];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => implode("\r\n", [
                'Accept: application/json',
                'User-Agent: natalcode-bookshop-seed-bot/1.0',
            ]),
        ],
    ]);

    $attempt = 0;
    $response = false;

    while ($attempt < 3 && $response === false) {
        $attempt++;
        $response = @file_get_contents($url, false, $context);

        if ($response !== false) {
            break;
        }

        usleep((int) (($sleepMs + (250 * $attempt)) * 1000));
    }

    if ($response === false) {
        file_put_contents($cachePath, '{}');

        return [];
    }

    file_put_contents($cachePath, $response);
    if ($sleepMs > 0) {
        usleep($sleepMs * 1000);
    }

    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : [];
}

function scoreCandidate(string $queryTitle, string $authorHint, string $candidateTitle, string $candidateSubtitle, array $authors): float
{
    $query = normalizeForMatch($queryTitle);
    $candidate = normalizeForMatch(trim($candidateTitle . ' ' . $candidateSubtitle));

    if ($query === '' || $candidate === '') {
        return 0.0;
    }

    similar_text($query, $candidate, $similarityPercent);
    $similarity = $similarityPercent / 100;

    $queryTokens = uniqueTokens($query);
    $candidateTokens = uniqueTokens($candidate);
    $intersection = count(array_intersect($queryTokens, $candidateTokens));
    $coverage = $queryTokens === [] ? 0.0 : ($intersection / count($queryTokens));

    $score = ($similarity * 0.62) + ($coverage * 0.38);

    if ($query === $candidate) {
        $score += 0.18;
    } elseif (str_starts_with($candidate, $query) || str_starts_with($query, $candidate)) {
        $score += 0.08;
    }

    if ($authorHint !== '') {
        $normalizedAuthorHint = normalizeForMatch($authorHint);
        $normalizedAuthors = array_map('normalizeForMatch', $authors);

        foreach ($normalizedAuthors as $normalizedAuthor) {
            if ($normalizedAuthor === '') {
                continue;
            }

            if (
                str_contains($normalizedAuthor, $normalizedAuthorHint)
                || str_contains($normalizedAuthorHint, $normalizedAuthor)
            ) {
                $score += 0.12;
                break;
            }
        }
    }

    return max(0.0, min(1.0, $score));
}

function normalizeForMatch(string $value): string
{
    $value = trim($value);
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($transliterated) && $transliterated !== '') {
        $value = $transliterated;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return trim($value);
}

/**
 * @return array<int, string>
 */
function uniqueTokens(string $value): array
{
    $parts = array_filter(explode(' ', $value), static fn (string $token): bool => $token !== '');

    return array_values(array_unique($parts));
}

function normalizeIsbn(string $value): string
{
    $isbn = preg_replace('/[^0-9Xx]/', '', $value) ?? '';

    return strtoupper($isbn);
}

function extractYear(string $value): ?int
{
    if (preg_match('/(1[5-9][0-9]{2}|20[0-9]{2}|21[0-9]{2})/', $value, $matches) !== 1) {
        return null;
    }

    return (int) $matches[1];
}

function normalizeLanguageCode(string $value): string
{
    return match (strtolower(trim($value))) {
        'pt', 'por' => 'Português',
        'en', 'eng' => 'Inglês',
        'es', 'spa' => 'Espanhol',
        'fr', 'fre', 'fra' => 'Francês',
        'it', 'ita' => 'Italiano',
        default => '',
    };
}

function normalizeOpenLibraryLanguage(array $languages): string
{
    foreach ($languages as $language) {
        $normalized = normalizeLanguageCode((string) $language);

        if ($normalized !== '') {
            return $normalized;
        }
    }

    return '';
}

/**
 * @param array<string, string> $prepared
 * @param array<string, mixed> $result
 * @return array<string, string>
 */
function buildImportRow(int $index, array $prepared, array $result): array
{
    $match = is_array($result['best_match'] ?? null) ? $result['best_match'] : [];
    $matchedTitle = BookshopTextNormalizer::normalizeTitle((string) ($match['title'] ?? $prepared['search_title']));
    $isbn = normalizeIsbn((string) ($match['isbn'] ?? ''));
    $barcode = normalizeIsbn((string) ($match['barcode'] ?? ''));
    $authorName = BookshopTextNormalizer::normalizeAuthorName(implode(', ', (array) ($match['authors'] ?? [])));

    if ($authorName === '') {
        $authorName = BookshopTextNormalizer::normalizeAuthorName((string) ($prepared['author_hint'] ?? ''));
    }

    if ($barcode === '' && strlen($isbn) === 13) {
        $barcode = $isbn;
    }

    $subtitle = trim((string) ($match['subtitle'] ?? ''));
    $titleForSlug = $matchedTitle !== '' ? $matchedTitle : (string) $prepared['search_title'];

    return [
        'sku' => sprintf('CEDE-LIV-%04d', $index),
        'slug' => slugify($titleForSlug . '-' . $index),
        'title' => $matchedTitle !== '' ? $matchedTitle : BookshopTextNormalizer::normalizeTitle((string) $prepared['search_title']),
        'subtitle' => $subtitle,
        'author_name' => $authorName,
        'publisher_name' => (string) ($match['publisher'] ?? ''),
        'isbn' => $isbn,
        'barcode' => $barcode,
        'edition_label' => '',
        'volume_number' => '',
        'volume_label' => '',
        'publication_year' => isset($match['publication_year']) && $match['publication_year'] !== null
            ? (string) $match['publication_year']
            : '',
        'page_count' => isset($match['page_count']) && $match['page_count'] !== null && (int) $match['page_count'] > 0
            ? (string) $match['page_count']
            : '',
        'language' => (string) ($match['language'] ?? ''),
        'description' => cleanDescription((string) ($match['description'] ?? '')),
        'sale_price' => '',
        'stock_minimum' => '',
        'status' => 'active',
        'location_label' => '',
        'category_name' => '',
        'genre_name' => '',
        'collection_name' => '',
        'source_name' => (string) ($match['source_name'] ?? ''),
        'source_url' => (string) ($match['source_url'] ?? ''),
        'confidence' => number_format((float) ($result['confidence'] ?? 0), 4, '.', ''),
        'raw_title' => (string) $prepared['raw_title'],
        'search_title' => (string) $prepared['search_title'],
        'author_hint' => (string) $prepared['author_hint'],
    ];
}

/**
 * @param array<string, string> $row
 */
function importRowHasRequiredFields(array $row): bool
{
    foreach (['sku', 'slug', 'title', 'author_name'] as $field) {
        if (trim((string) ($row[$field] ?? '')) === '') {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, string> $prepared
 * @param array<string, mixed> $result
 * @return array<string, string>
 */
function buildReviewRow(array $prepared, array $result, string $status): array
{
    $match = is_array($result['best_match'] ?? null) ? $result['best_match'] : [];

    return [
        'status' => $status,
        'raw_title' => (string) $prepared['raw_title'],
        'search_title' => (string) $prepared['search_title'],
        'author_hint' => (string) $prepared['author_hint'],
        'best_title' => (string) ($match['title'] ?? ''),
        'best_subtitle' => (string) ($match['subtitle'] ?? ''),
        'best_authors' => implode(', ', (array) ($match['authors'] ?? [])),
        'best_publisher' => (string) ($match['publisher'] ?? ''),
        'best_isbn' => (string) ($match['isbn'] ?? ''),
        'best_year' => isset($match['publication_year']) && $match['publication_year'] !== null
            ? (string) $match['publication_year']
            : '',
        'best_page_count' => isset($match['page_count']) && $match['page_count'] !== null
            ? (string) $match['page_count']
            : '',
        'source_name' => (string) ($match['source_name'] ?? ''),
        'source_url' => (string) ($match['source_url'] ?? ''),
        'confidence' => number_format((float) ($result['confidence'] ?? 0), 4, '.', ''),
        'source_query' => (string) ($match['source_query'] ?? ''),
    ];
}

/**
 * @param array<int, string> $headers
 * @param array<int, array<string, string>> $rows
 */
function writeCsv(string $path, array $headers, array $rows): void
{
    $handle = fopen($path, 'wb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Nao foi possivel abrir o arquivo CSV para escrita: ' . $path);
    }

    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $headers, ';');

    foreach ($rows as $row) {
        $orderedRow = [];

        foreach ($headers as $header) {
            $orderedRow[] = $row[$header] ?? '';
        }

        fputcsv($handle, $orderedRow, ';');
    }

    fclose($handle);
}

function slugify(string $value): string
{
    $normalized = trim($value);
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

    if (is_string($transliterated) && $transliterated !== '') {
        $normalized = $transliterated;
    }

    $normalized = strtolower($normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? $normalized;
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'livro';
}

function cleanDescription(string $description): string
{
    $description = trim(strip_tags($description));
    $description = preg_replace('/\s+/', ' ', $description) ?? $description;

    return $description;
}

function ensureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Nao foi possivel criar o diretorio: ' . $path);
    }
}
