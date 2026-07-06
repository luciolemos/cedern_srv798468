<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionOpenWhatsappAction extends AbstractAdminFinanceContributionReminderAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $chargeId = (int) ($request->getAttribute('id') ?? 0);
        $fallbackCompetence = $this->normalizeCompetence($request->getQueryParams()['competence'] ?? null);
        $context = $this->loadChargeContext($chargeId);

        if ($context === null) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Cobrança não encontrada para abrir o lembrete no WhatsApp.',
                'tone' => 'error',
            ]);

            return $this->redirectToList($response, $fallbackCompetence);
        }

        $charge = $context['charge'];
        $member = $context['member'];
        $competence = $context['competence'];
        $optInAuthorized = (int) ($member['billing_whatsapp_opt_in'] ?? 0) === 1;
        $chargeStatus = strtolower(trim((string) ($charge['status'] ?? '')));
        $whatsappUrl = $this->buildWhatsappReminderUrl($charge, $member);

        if (!$optInAuthorized || $chargeStatus !== 'pending' || $whatsappUrl === null) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Esse associado não está apto para lembrete por WhatsApp nesta cobrança.',
                'tone' => 'error',
            ]);

            return $this->redirectToList($response, $competence);
        }

        $this->memberAuthRepository->registerContributionReminderEvent(
            $chargeId,
            'whatsapp',
            $this->resolveActorUserId(),
            [
                'target_phone' => preg_replace('/\D+/', '', (string) ($member['phone_mobile'] ?? '')) ?? '',
            ]
        );

        return $response
            ->withHeader('Location', $whatsappUrl)
            ->withStatus(302);
    }
}
