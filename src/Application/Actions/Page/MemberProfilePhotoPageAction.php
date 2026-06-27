<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class MemberProfilePhotoPageAction extends AbstractPageAction
{
    use MemberProfilePhotoStorageTrait;

    public function __construct(LoggerInterface $logger, Twig $twig)
    {
        parent::__construct($logger, $twig);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $fileName = trim((string) $request->getAttribute('file'));

        if ($fileName === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $fileName) !== 1) {
            return $response->withStatus(404);
        }

        $relativePath = 'media/membros/fotos/' . $fileName;
        $absolutePath = $this->resolveManagedMemberProfilePhotoAbsolutePath($relativePath);

        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return $response->withStatus(404);
        }

        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            $this->logger->warning('Falha ao ler foto de perfil gerenciada.', [
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
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        return (string) ($mimeTypes[$extension] ?? 'application/octet-stream');
    }
}
