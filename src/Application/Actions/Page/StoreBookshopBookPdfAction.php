<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Application\Actions\Admin\AdminBookshopBookPdfAction;
use App\Support\BookshopDescriptionSanitizer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class StoreBookshopBookPdfAction extends AdminBookshopBookPdfAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $slug = trim((string) ($request->getAttribute('slug') ?? ''));

        if ($slug === '') {
            $response->getBody()->write('Livro não encontrado.');

            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        try {
            $book = $this->bookshopRepository->findCatalogBookBySlug($slug);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao carregar livro da livraria pública para impressão.', [
                'book_slug' => $slug,
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
        $resolvedSlug = trim((string) ($book['slug'] ?? $slug));
        $catalogUrl = $this->buildAbsoluteAppUrl($request, '/loja/livraria');
        $documentUrl = $catalogUrl . '#livraria-' . rawurlencode($resolvedSlug);

        try {
            return $this->respondWithPrintableHtmlDocument(
                $request,
                $response,
                $book,
                $documentUrl,
                [
                    'pdf_origin_label' => 'Origem da consulta',
                    'pdf_purpose' => 'Consulta e impressão da ficha do título da livraria.',
                ]
            );
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao preparar ficha pública para impressão do item da livraria.', [
                'book_slug' => $resolvedSlug,
                'error' => $exception->getMessage(),
            ]);

            $response->getBody()->write('Não foi possível preparar a ficha para impressão do livro neste momento.');

            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }
    }
}
