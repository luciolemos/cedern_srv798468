<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\MemberCompleteProfilePdfAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class AdminMemberUserPdfAction extends MemberCompleteProfilePdfAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $userId = (int) ($request->getAttribute('id') ?? 0);

        if ($userId <= 0) {
            $response->getBody()->write('Usuário não encontrado.');

            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        try {
            $user = $this->memberAuthRepository->findById($userId);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao carregar usuário para geração administrativa do PDF cadastral.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            $response->getBody()->write('Não foi possível carregar os dados do usuário neste momento.');

            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        if ($user === null) {
            $response->getBody()->write('Usuário não encontrado.');

            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        try {
            $documentResponse = $this->respondWithPrintableHtmlDocument(
                $request,
                $response,
                $user,
                [],
                $this->buildAbsoluteAppUrl($request, '/painel/usuarios/' . $userId)
            );
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao gerar ficha para impressão administrativa do formulário de cadastro do associado.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
            $response->getBody()->write('Não foi possível preparar a ficha para impressão do cadastro neste momento.');

            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        return $documentResponse;
    }
}
