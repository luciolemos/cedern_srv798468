<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Support\BookshopTextNormalizer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class AdminBookshopImportPageAction extends AbstractAdminBookshopAction
{
    private const FLASH_KEY = 'admin_bookshop_import';

    private const HEADER_ALIASES = [
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

    public function __invoke(Request $request, Response $response): Response
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash(self::FLASH_KEY);

            return $this->renderPage($response, 'pages/admin-bookshop-import.twig', [
                'bookshop_import_summary' => (array) ($flash['summary'] ?? []),
                'bookshop_import_errors' => (array) ($flash['errors'] ?? []),
                'page_title' => 'Importar Acervo | Dashboard',
                'page_url' => 'https://cedern.org/painel/livraria/importar',
                'page_description' => 'Importação de acervo da livraria por CSV exportado do Excel.',
            ]);
        }

        $uploadedFiles = $request->getUploadedFiles();
        $csvUpload = $uploadedFiles['inventory_file'] ?? null;
        $summary = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];
        $errors = [];

        if (!$csvUpload instanceof UploadedFileInterface || $csvUpload->getError() === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Envie um arquivo CSV exportado da planilha.';
        }

        if ($errors === []) {
            try {
                $rows = $this->parseCsvUpload($csvUpload);

                foreach ($rows as $rowNumber => $row) {
                    $summary['processed']++;

                    try {
                        $payload = $this->normalizeImportRow($row);
                        $rowErrors = $this->validateImportPayload($payload);

                        if ($rowErrors !== []) {
                            throw new \RuntimeException(implode(' ', $rowErrors));
                        }

                        $existingBook = $this->bookshopRepository->findBookBySku((string) $payload['sku']);
                        if ($existingBook === null && (string) ($payload['isbn'] ?? '') !== '') {
                            $existingBook = $this->bookshopRepository->findBookByIsbn((string) $payload['isbn']);
                        }

                        if ($existingBook !== null) {
                            $payload['cost_price'] = (string) ($existingBook['cost_price'] ?? '0.00');
                            $payload['stock_quantity'] = (int) ($existingBook['stock_quantity'] ?? 0);
                            $this->bookshopRepository->updateBook((int) $existingBook['id'], $payload);
                            $summary['updated']++;
                        } else {
                            $payload['cost_price'] = '0.00';
                            $payload['stock_quantity'] = 0;
                            $this->bookshopRepository->createBook($payload);
                            $summary['created']++;
                        }
                    } catch (\Throwable $exception) {
                        $summary['errors']++;
                        $errors[] = 'Linha ' . $rowNumber . ': ' . trim($exception->getMessage());
                    }
                }
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $this->storeSessionFlash(self::FLASH_KEY, [
            'summary' => $summary,
            'errors' => array_slice($errors, 0, 12),
        ]);

        return $response->withHeader('Location', '/painel/livraria/importar')->withStatus(303);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsvUpload(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Não foi possível enviar o CSV. Tente novamente.');
        }

        $filename = strtolower(trim((string) $file->getClientFilename()));
        if ($filename !== '' && substr($filename, -4) !== '.csv') {
            throw new \RuntimeException(
                'Use um CSV exportado do Excel. Arquivos XLSX ainda não entram direto neste fluxo.'
            );
        }

        $stream = $file->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $contents = (string) $stream->getContents();
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $temporaryFile = tempnam(sys_get_temp_dir(), 'bookshop_csv_');
        if ($temporaryFile === false) {
            throw new \RuntimeException('Não foi possível preparar a leitura temporária do CSV.');
        }

        file_put_contents($temporaryFile, $contents);
        $handle = fopen($temporaryFile, 'rb');

        if (!is_resource($handle)) {
            @unlink($temporaryFile);

            throw new \RuntimeException('Não foi possível ler o CSV enviado.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            @unlink($temporaryFile);

            throw new \RuntimeException('O CSV enviado está vazio.');
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!is_array($headers)) {
            fclose($handle);
            @unlink($temporaryFile);

            throw new \RuntimeException('Não foi possível interpretar o cabeçalho do CSV.');
        }

        $mappedHeaders = $this->mapHeaders($headers);
        $rows = [];
        $lineNumber = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            $row = [];
            foreach ($mappedHeaders as $field => $index) {
                $row[$field] = trim((string) ($data[$index] ?? ''));
            }

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $rows[$lineNumber] = $row;
        }

        fclose($handle);
        @unlink($temporaryFile);

        if ($rows === []) {
            throw new \RuntimeException('Nenhuma linha utilizável foi encontrada no CSV.');
        }

        return $rows;
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, int>
     */
    private function mapHeaders(array $headers): array
    {
        $normalizedHeaders = [];

        foreach ($headers as $index => $header) {
            $normalizedHeaders[$index] = $this->normalizeHeader((string) $header);
        }

        $mapped = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($normalizedHeaders as $index => $header) {
                if (in_array($header, array_map([$this, 'normalizeHeader'], $aliases), true)) {
                    $mapped[$field] = $index;
                    break;
                }
            }
        }

        return $mapped;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['"', '\''], '', $normalized);

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    private function normalizeImportRow(array $row): array
    {
        $title = BookshopTextNormalizer::normalizeTitle((string) ($row['title'] ?? ''));
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        $isbn = trim((string) ($row['isbn'] ?? ''));

        if ($sku === '' && $isbn !== '') {
            $sku = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '', $isbn) ?? $isbn);
        }

        $slugInput = trim((string) ($row['slug'] ?? ''));
        $slug = $this->slugify($slugInput !== '' ? $slugInput : ($title . '-' . strtolower($sku)));
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
            'language' => $this->normalizeBookshopLanguage($row['language'] ?? ''),
            'description' => trim((string) ($row['description'] ?? '')),
            'sale_price' => $this->normalizeMoneyInput($row['sale_price'] ?? '0'),
            'stock_minimum' => max(0, $this->normalizeIntegerInput($row['stock_minimum'] ?? 0, 0)),
            'status' => $status,
            'location_label' => trim((string) ($row['location_label'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function validateImportPayload(array $payload): array
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
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
