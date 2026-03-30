<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopImportTemplateDownloadAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $templatePath = dirname(__DIR__, 4) . '/var/exports/modelo-importacao-acervo.csv';

        if (!is_file($templatePath)) {
            $this->logger->warning('Modelo de importacao do acervo nao encontrado.', [
                'path' => $templatePath,
            ]);

            $response->getBody()->write('Arquivo modelo indisponivel.');

            return $response->withStatus(404);
        }

        $contents = (string) file_get_contents($templatePath);
        $response->getBody()->write($contents);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="modelo-importacao-acervo.csv"')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}
