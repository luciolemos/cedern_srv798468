<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Support\BookshopDescriptionSanitizer;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Throwable;

class AdminBookshopBookPdfAction extends AbstractAdminBookshopAction
{
    private const DOCUMENT_TIMEZONE = 'America/Fortaleza';
    private const PLAYWRIGHT_BROWSER_CACHE_DIR = 'var/cache/ms-playwright';
    private const PRINTABLE_HTML_FALLBACK_NOTICE =
        'Gerador de PDF indisponível neste servidor no momento. Use a impressão do navegador para salvar em PDF.';

    public function __invoke(Request $request, Response $response): Response
    {
        $bookId = (int) ($request->getAttribute('id') ?? 0);

        if ($bookId <= 0) {
            $response->getBody()->write('Livro não encontrado.');

            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        try {
            $book = $this->bookshopRepository->findBookByIdForAdmin($bookId);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao carregar livro para geração do PDF administrativo.', [
                'book_id' => $bookId,
                'error' => $exception->getMessage(),
            ]);

            $response->getBody()->write('Não foi possível carregar os dados do livro neste momento.');

            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        if ($book === null) {
            $response->getBody()->write('Livro não encontrado.');

            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $book['description'] = BookshopDescriptionSanitizer::sanitizeForDisplay((string) ($book['description'] ?? ''));

        try {
            $documentData = $this->buildDocumentData($request, $book);
            $documentData['pdf_document_url'] = $this->buildAbsoluteAppUrl($request, '/painel/livraria/acervo/' . $bookId);
            $html = $this->twig->getEnvironment()->render('pages/admin-bookshop-book-pdf.twig', $documentData);
            $pdfBinary = $this->renderPdfFromHtml($html);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao gerar PDF administrativo do item do acervo da livraria.', [
                'book_id' => $bookId,
                'error' => $exception->getMessage(),
            ]);

            $fallbackResponse = $this->respondWithPrintableHtmlFallback(
                $request,
                $response,
                $book,
                $this->buildAbsoluteAppUrl($request, '/painel/livraria/acervo/' . $bookId)
            );
            if ($fallbackResponse !== null) {
                return $fallbackResponse;
            }

            $response->getBody()->write('Não foi possível gerar o PDF do livro neste momento.');

            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $response->getBody()->write($pdfBinary);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $this->buildPdfFileName($book) . '"')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
    }

    /**
     * @param array<string, mixed> $book
     */
    protected function respondWithPrintableHtmlFallback(
        Request $request,
        Response $response,
        array $book,
        ?string $documentUrlOverride = null
    ): ?Response {
        try {
            $documentData = $this->buildDocumentData($request, $book);
            $documentData['pdf_notice'] = self::PRINTABLE_HTML_FALLBACK_NOTICE;

            if ($documentUrlOverride !== null && trim($documentUrlOverride) !== '') {
                $documentData['pdf_document_url'] = $documentUrlOverride;
            }

            $html = $this->twig->getEnvironment()->render('pages/admin-bookshop-book-pdf.twig', $documentData);
        } catch (Throwable $fallbackException) {
            $this->logger->error('Falha ao gerar fallback HTML do PDF do item do acervo da livraria.', [
                'book_id' => (int) ($book['id'] ?? 0),
                'error' => $fallbackException->getMessage(),
            ]);

            return null;
        }

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('X-Cede-Document-Fallback', 'html')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
    }

    protected function renderPdfFromHtml(string $html): string
    {
        $exportDirectory = $this->prepareExportDirectory();
        $exportToken = date('YmdHis') . '-' . bin2hex(random_bytes(6));
        $htmlPath = $exportDirectory . '/ficha-acervo-' . $exportToken . '.html';
        $pdfPath = $exportDirectory . '/ficha-acervo-' . $exportToken . '.pdf';

        if (file_put_contents($htmlPath, $html) === false) {
            throw new RuntimeException('Não foi possível preparar o HTML temporário do PDF.');
        }

        try {
            $this->runPdfCommand($htmlPath, $pdfPath);
            clearstatcache(true, $pdfPath);

            if (!is_file($pdfPath) || filesize($pdfPath) < 1) {
                throw new RuntimeException('O arquivo PDF não foi criado.');
            }

            $pdfBinary = file_get_contents($pdfPath);
            if ($pdfBinary === false) {
                throw new RuntimeException('Não foi possível ler o PDF gerado.');
            }

            return $pdfBinary;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
        }
    }

    /**
     * @param array<string, mixed> $book
     * @return array<string, mixed>
     */
    private function buildDocumentData(Request $request, array $book): array
    {
        $title = $this->displayValue((string) ($book['title'] ?? ''), 'Livro sem título');
        $author = $this->displayValue((string) ($book['author_name'] ?? ''));
        $subtitle = trim((string) ($book['subtitle'] ?? ''));
        $collectionLabel = $this->buildCollectionLabel($book);
        $descriptionHtml = trim((string) ($book['description'] ?? ''));
        $coverDataUri = $this->resolveBookCoverDataUri((string) ($book['cover_image_path'] ?? ''));

        $editorialRows = [
            ['label' => 'Coleção / volume', 'value' => $this->displayValue($collectionLabel)],
            ['label' => 'Gênero literário', 'value' => $this->displayValue((string) ($book['genre_name'] ?? ''))],
            ['label' => 'Categoria doutrinária', 'value' => $this->displayValue((string) ($book['category_name'] ?? ''))],
            ['label' => 'Editora', 'value' => $this->displayValue((string) ($book['publisher_name'] ?? ''))],
            ['label' => 'Edição', 'value' => $this->displayValue((string) ($book['edition_label'] ?? ''))],
            ['label' => 'Ano de publicação', 'value' => $this->displayValue((string) ($book['publication_year'] ?? ''))],
            ['label' => 'Idioma', 'value' => $this->displayValue((string) ($book['language'] ?? ''))],
            ['label' => 'Número de páginas', 'value' => $this->displayInteger($book['page_count'] ?? null)],
            ['label' => 'ISBN', 'value' => $this->displayValue((string) ($book['isbn'] ?? ''))],
            ['label' => 'Código de barras', 'value' => $this->displayValue((string) ($book['barcode'] ?? ''))],
        ];

        return [
            'pdf_document_url' => $this->buildAbsoluteAppUrl($request, '/painel/livraria/acervo/' . (int) ($book['id'] ?? 0)),
            'pdf_generated_at' => (new DateTimeImmutable('now', new DateTimeZone(self::DOCUMENT_TIMEZONE)))
                ->format('d/m/Y H:i'),
            'pdf_notice' => '',
            'pdf_brand_logo_data_uri' => $this->resolveBrandLogoDataUri(),
            'pdf_book_cover_data_uri' => $coverDataUri,
            'pdf_book_title' => $title,
            'pdf_book_author' => $author,
            'pdf_book_subtitle' => $subtitle,
            'pdf_editorial_rows' => $editorialRows,
            'pdf_sections' => [],
            'pdf_description_html' => trim(strip_tags($descriptionHtml)) !== '' ? $descriptionHtml : '',
        ];
    }

    /**
     * @param array<string, mixed> $book
     */
    private function buildPdfFileName(array $book): string
    {
        $baseName = trim((string) ($book['slug'] ?? ''));

        if ($baseName === '') {
            $baseName = trim((string) ($book['sku'] ?? ''));
        }

        if ($baseName === '') {
            $baseName = trim((string) ($book['title'] ?? 'livro-acervo'));
        }

        $slug = $this->slugify($baseName);
        if ($slug === '') {
            $slug = 'livro-acervo';
        }

        return 'ficha-acervo-' . $slug . '.pdf';
    }

    private function prepareExportDirectory(): string
    {
        $directory = dirname(__DIR__, 4) . '/var/cache/bookshop-book-pdf';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório temporário do PDF.');
        }

        if (!is_writable($directory)) {
            @chmod($directory, 0775);
            clearstatcache(true, $directory);
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('O diretório temporário do PDF não está gravável.');
        }

        return $directory;
    }

    private function runPdfCommand(string $htmlPath, string $pdfPath): void
    {
        $nodeBinary = trim((string) ($_ENV['NODE_BINARY'] ?? 'node'));
        $nodeScript = dirname(__DIR__, 4) . '/scripts/export_bookshop_manual_pdf.mjs';
        $playwrightBrowsersPath = $this->preparePlaywrightBrowserCacheDirectory();

        if (!is_file($nodeScript)) {
            throw new RuntimeException('O script de exportação do PDF não foi encontrado.');
        }

        $command = sprintf(
            'PLAYWRIGHT_BROWSERS_PATH=%s %s %s %s %s',
            escapeshellarg($playwrightBrowsersPath),
            escapeshellarg($nodeBinary),
            escapeshellarg($nodeScript),
            escapeshellarg($htmlPath),
            escapeshellarg($pdfPath)
        );

        if (function_exists('proc_open')) {
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 4));
            if (!is_resource($process)) {
                throw new RuntimeException('Não foi possível iniciar o gerador de PDF.');
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Falha ao executar o gerador de PDF.'
                    . ($stderr !== '' ? ' ' . trim($stderr) : '')
                    . ($stdout !== '' ? ' ' . trim($stdout) : '')
                );
            }

            return;
        }

        if (function_exists('exec')) {
            $output = [];
            $exitCode = 0;
            exec($command . ' 2>&1', $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException('Falha ao executar o gerador de PDF. ' . trim(implode("\n", $output)));
            }

            return;
        }

        throw new RuntimeException('Nenhum executor de comando está disponível para gerar o PDF.');
    }

    private function preparePlaywrightBrowserCacheDirectory(): string
    {
        $directory = dirname(__DIR__, 4) . '/' . self::PLAYWRIGHT_BROWSER_CACHE_DIR;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o cache local do Playwright.');
        }

        @chmod($directory, 0775);
        clearstatcache(true, $directory);

        if (!is_dir($directory) || !is_readable($directory) || !is_executable($directory)) {
            throw new RuntimeException('O cache local do Playwright não está acessível.');
        }

        return $directory;
    }

    /**
     * @param array<string, mixed> $book
     */
    private function buildCollectionLabel(array $book): string
    {
        $parts = [];
        $collectionName = trim((string) ($book['collection_name'] ?? ''));
        if ($collectionName !== '') {
            $parts[] = $collectionName;
        }

        $volumeNumber = isset($book['volume_number']) && $book['volume_number'] !== null
            ? (int) $book['volume_number']
            : null;
        if ($volumeNumber !== null && $volumeNumber > 0) {
            $parts[] = 'Vol. ' . $volumeNumber;
        }

        $volumeLabel = trim((string) ($book['volume_label'] ?? ''));
        if ($volumeLabel !== '') {
            $parts[] = $volumeLabel;
        }

        return implode(' · ', $parts);
    }

    private function displayValue(string $value, string $fallback = 'Não informado'): string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function displayInteger(mixed $value, string $fallback = 'Não informado'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return (string) (int) $value;
    }

    private function formatDateTime(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return 'Não informado';
        }

        try {
            return (new DateTimeImmutable($normalized))->format('d/m/Y H:i');
        } catch (Throwable) {
            return $normalized;
        }
    }

    private function formatMoney(mixed $value): string
    {
        if (!is_numeric((string) $value)) {
            return 'Não informado';
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    private function formatStockSummary(array $book): string
    {
        $quantity = (int) ($book['stock_quantity'] ?? 0);
        $label = $this->resolveStockStateLabel($book);

        return $quantity . ' exemplar' . ($quantity === 1 ? '' : 'es') . ' · ' . $label;
    }

    /**
     * @param array<string, mixed> $book
     */
    private function resolveBookStatusLabel(array $book): string
    {
        $label = trim((string) ($book['status_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        return trim((string) ($book['status'] ?? '')) === 'inactive' ? 'Inativo' : 'Ativo';
    }

    /**
     * @param array<string, mixed> $book
     */
    private function resolveStockStateLabel(array $book): string
    {
        $label = trim((string) ($book['stock_state_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $quantity = (int) ($book['stock_quantity'] ?? 0);
        $minimum = (int) ($book['stock_minimum'] ?? 0);

        if ($quantity <= 0) {
            return 'Sem estoque';
        }

        if ($quantity <= $minimum) {
            return 'Estoque baixo';
        }

        return 'Em estoque';
    }

    private function resolveMoneyLabel(mixed $label, mixed $rawValue): string
    {
        $normalizedLabel = trim((string) $label);

        return $normalizedLabel !== '' ? $normalizedLabel : $this->formatMoney($rawValue);
    }

    private function resolveBookCoverDataUri(string $relativePath): ?string
    {
        $absolutePath = $this->resolveManagedBookshopCoverAbsolutePath($relativePath)
            ?? $this->resolveManagedPrivateBookshopCoverAbsolutePath($relativePath);

        return $this->resolveImageDataUri($absolutePath);
    }

    private function resolveBrandLogoDataUri(): ?string
    {
        $absolutePath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede12_logo.png';

        return $this->resolveImageDataUri($absolutePath);
    }

    private function resolveImageDataUri(?string $absolutePath): ?string
    {
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => function_exists('mime_content_type')
                ? (mime_content_type($absolutePath) ?: 'application/octet-stream')
                : 'application/octet-stream',
        };

        return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
    }
}
