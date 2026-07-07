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
            $documentData = $this->buildDocumentData($request, $user, []);
            $documentData['pdf_document_url'] = $this->buildAbsoluteAppUrl($request, '/painel/usuarios/' . $userId);
            $html = $this->twig->getEnvironment()->render('pages/member-registration-form-pdf.twig', $documentData);
            $pdfBinary = $this->renderPdfFromHtml($html);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao gerar PDF administrativo do formulário de cadastro do associado.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            $fallbackResponse = $this->respondWithPrintableHtmlFallback(
                $request,
                $response,
                $user,
                [],
                $this->buildAbsoluteAppUrl($request, '/painel/usuarios/' . $userId)
            );
            if ($fallbackResponse !== null) {
                return $fallbackResponse;
            }

            $response->getBody()->write('Não foi possível gerar o PDF do cadastro neste momento.');

            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $response->getBody()->write($pdfBinary);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="formulario-cadastro-associado.pdf"')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
    }
}
