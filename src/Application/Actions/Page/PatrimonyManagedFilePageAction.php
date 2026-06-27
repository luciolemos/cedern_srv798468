<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Application\Actions\Admin\AbstractAdminPatrimonyAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PatrimonyManagedFilePageAction extends AbstractAdminPatrimonyAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $bucket = trim((string) $request->getAttribute('bucket'));
        $fileName = trim((string) $request->getAttribute('file'));

        if (!in_array($bucket, ['docs', 'img'], true)) {
            return $response->withStatus(404);
        }

        if ($fileName === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $fileName) !== 1) {
            return $response->withStatus(404);
        }

        $relativePath = 'media/patrimonio/' . $bucket . '/' . $fileName;
        $absolutePath = $this->resolveManagedPatrimonyAbsolutePath($relativePath);

        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return $response->withStatus(404);
        }

        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            $this->logger->warning('Falha ao ler arquivo patrimonial gerenciado.', [
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
            ->withHeader('Cache-Control', 'public, max-age=86400')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    private function resolveMimeType(string $absolutePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = @finfo_file($finfo, $absolutePath);
                @finfo_close($finfo);

                if (is_string($mimeType) && trim($mimeType) !== '') {
                    return $mimeType;
                }
            }
        }

        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return (string) ($mimeTypes[$extension] ?? 'application/octet-stream');
    }
}
