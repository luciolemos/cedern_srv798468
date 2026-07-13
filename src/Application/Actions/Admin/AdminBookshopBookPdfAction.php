<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Support\BookshopDescriptionSanitizer;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class AdminBookshopBookPdfAction extends AbstractAdminBookshopAction
{
    private const DOCUMENT_TIMEZONE = 'America/Fortaleza';

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
            $documentResponse = $this->respondWithPrintableHtmlDocument(
                $request,
                $response,
                $book,
                $this->buildAbsoluteAppUrl($request, '/painel/livraria/acervo/' . $bookId)
            );
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao gerar ficha para impressão do item do acervo da livraria.', [
                'book_id' => $bookId,
                'error' => $exception->getMessage(),
            ]);
            $response->getBody()->write('Não foi possível preparar a ficha para impressão do livro neste momento.');

            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        return $documentResponse;
    }

    /**
     * @param array<string, mixed> $book
     */
    protected function respondWithPrintableHtmlDocument(
        Request $request,
        Response $response,
        array $book,
        ?string $documentUrlOverride = null
    ): Response {
        $documentData = $this->buildDocumentData($request, $book);

        if ($documentUrlOverride !== null && trim($documentUrlOverride) !== '') {
            $documentData['pdf_document_url'] = $documentUrlOverride;
        }

        $html = $this->twig->getEnvironment()->render('pages/admin-bookshop-book-pdf.twig', $documentData);

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
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
