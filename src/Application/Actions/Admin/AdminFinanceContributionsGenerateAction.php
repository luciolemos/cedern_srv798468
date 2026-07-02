<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionsGenerateAction extends AbstractAdminFinanceContributionsAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $payload = (array) ($request->getParsedBody() ?? []);
        $competence = $this->normalizeCompetence($payload['competence'] ?? null);

        try {
            $result = $this->memberAuthRepository->generateContributionCharges(
                $competence,
                $this->resolveActorUserId()
            );

            $message = sprintf(
                'Competência %s: %d cobrança%s criada%s, %d já existente%s e %d perfil%s com configuração financeira pendente.',
                $this->formatCompetenceLabel($competence),
                (int) ($result['created'] ?? 0),
                (int) ($result['created'] ?? 0) === 1 ? '' : 's',
                (int) ($result['created'] ?? 0) === 1 ? '' : 's',
                (int) ($result['skipped_existing'] ?? 0),
                (int) ($result['skipped_existing'] ?? 0) === 1 ? '' : 's',
                (int) ($result['skipped_incomplete_profile'] ?? 0),
                (int) ($result['skipped_incomplete_profile'] ?? 0) === 1 ? '' : 's'
            );

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => $message,
                'tone' => 'success',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao gerar cobranças mensais.', [
                'competence' => $competence,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Não foi possível gerar as cobranças da competência selecionada.',
                'tone' => 'error',
            ]);
        }

        return $this->redirectToList($response, $competence);
    }
}
