<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Application\Actions\Admin\AbstractAdminBookshopAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BookshopCoverImagePageAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $fileName = trim((string) $request->getAttribute('file'));

        if ($fileName === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $fileName) !== 1) {
            return $response->withStatus(404);
        }

        $absolutePath = $this->resolveBookshopPrivateCoverFileAbsolutePath($fileName);
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return $response->withStatus(404);
        }

        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            $this->logger->warning('Falha ao ler capa privada da livraria.', [
                'path' => $absolutePath,
            ]);

            return $response->withStatus(404);
        }

        $mimeType = $this->resolveMimeType($absolutePath);
        $fileSize = filesize($absolutePath);
        $response->getBody()->write($contents);

        if ($fileSize !== false) {
            $response = $response->withHeader('Content-Length', (string) $fileSize);
        }

        return $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }

    private function resolveMimeType(string $absolutePath): string
    {
        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        return (string) ($mimeTypes[$extension] ?? 'application/octet-stream');
    }

    private function resolveBookshopPrivateCoverFileAbsolutePath(string $fileName): string
    {
        foreach ($this->resolveBookshopPrivateCoverDirectories() as $directory) {
            $candidate = rtrim($directory, '/') . '/' . $fileName;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return rtrim($this->resolveBookshopCoverFallbackUploadDirectory(), '/') . '/' . $fileName;
    }
}
