<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionMarkExemptAction extends AbstractAdminFinanceContributionsAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $chargeId = (int) ($request->getAttribute('id') ?? 0);
        $payload = (array) ($request->getParsedBody() ?? []);
        $fallbackCompetence = $this->normalizeCompetence($payload['competence'] ?? null);
        $charge = $chargeId > 0 ? $this->memberAuthRepository->findContributionChargeById($chargeId) : null;
        $competence = $this->normalizeCompetence($charge['competence'] ?? $fallbackCompetence);

        if ($charge === null) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Cobrança não encontrada para isenção.',
                'tone' => 'error',
            ]);

            return $this->redirectToList($response, $competence);
        }

        try {
            $updated = $this->memberAuthRepository->markContributionChargeAsExempt(
                $chargeId,
                'Isenção registrada pelo financeiro.',
                $this->resolveActorUserId()
            );

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => $updated
                    ? 'Isenção registrada para ' . trim((string) ($charge['member_full_name'] ?? 'o associado')) . '.'
                    : 'A cobrança selecionada não pode mais ser marcada como isenta.',
                'tone' => $updated ? 'success' : 'error',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao isentar contribuição.', [
                'charge_id' => $chargeId,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Não foi possível registrar a isenção dessa cobrança.',
                'tone' => 'error',
            ]);
        }

        return $this->redirectToList($response, $competence);
    }
}
