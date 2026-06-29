<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionSendEmailAction extends AbstractAdminFinanceContributionReminderAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $chargeId = (int) ($request->getAttribute('id') ?? 0);
        $payload = (array) ($request->getParsedBody() ?? []);
        $fallbackCompetence = $this->normalizeCompetence($payload['competence'] ?? null);
        $context = $this->loadChargeContext($chargeId);

        if ($context === null) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Cobrança não encontrada para envio do lembrete.',
                'tone' => 'error',
            ]);

            return $this->redirectToList($response, $fallbackCompetence);
        }

        $charge = $context['charge'];
        $member = $context['member'];
        $competence = $context['competence'];
        $optInAuthorized = (int) ($member['billing_email_opt_in'] ?? 0) === 1;
        $chargeStatus = strtolower(trim((string) ($charge['status'] ?? '')));

        if (!$optInAuthorized || $chargeStatus !== 'pending') {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Esse associado não está apto para receber lembrete por e-mail nesta cobrança.',
                'tone' => 'error',
            ]);

            return $this->redirectToList($response, $competence);
        }

        try {
            $this->sendContributionReminderEmail($charge, $member);
            $this->memberAuthRepository->registerContributionReminderEvent(
                $chargeId,
                'email',
                $this->resolveActorUserId(),
                [
                    'target_email' => strtolower(trim((string) ($member['email'] ?? ''))),
                ]
            );

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Lembrete enviado por e-mail para ' . trim((string) ($member['full_name'] ?? 'o associado')) . '.',
                'tone' => 'success',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao enviar lembrete financeiro por e-mail.', [
                'charge_id' => $chargeId,
                'member_user_id' => (int) ($member['id'] ?? 0),
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Não foi possível enviar o lembrete por e-mail dessa cobrança.',
                'tone' => 'error',
            ]);
        }

        return $this->redirectToList($response, $competence);
    }
}
