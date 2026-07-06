<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionGatewayCreateAction extends AbstractAdminFinanceContributionGatewayAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $chargeId = (int) ($request->getAttribute('id') ?? 0);
        $payload = (array) ($request->getParsedBody() ?? []);
        $billingType = strtolower(trim((string) ($payload['billing_type'] ?? '')));
        $context = $this->loadChargeContext($chargeId);
        $fallbackCompetence = $this->normalizeCompetence($payload['competence'] ?? null);

        if ($context === null) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Cobrança não encontrada para integração com o gateway.',
                'tone' => 'error',
            ]);

            return $this->redirectToList($response, $fallbackCompetence);
        }

        $charge = $context['charge'];
        $member = $context['member'];
        $competence = $context['competence'];

        if (!$this->gatewayConfigured()) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Gateway externo não configurado. Defina as credenciais do Asaas antes de gerar a cobrança.',
                'tone' => 'error',
            ]);

            return $this->redirectToChargeDetail($response, $chargeId);
        }

        if (strtolower(trim((string) ($charge['status'] ?? ''))) !== 'pending') {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Somente cobranças pendentes podem ser enviadas ao gateway.',
                'tone' => 'error',
            ]);

            return $this->redirectToChargeDetail($response, $chargeId);
        }

        if (trim((string) ($charge['gateway_payment_id'] ?? '')) !== '') {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Esta contribuição já possui uma cobrança externa criada.',
                'tone' => 'error',
            ]);

            return $this->redirectToChargeDetail($response, $chargeId);
        }

        try {
            $gatewayData = $this->billingGateway->createCharge($member, $charge, $billingType);
            $this->memberAuthRepository->updateContributionGatewayData($chargeId, $gatewayData);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => sprintf(
                    'Cobrança %s criada no Asaas para %s.',
                    $billingType === 'pix' ? 'Pix' : 'por boleto',
                    trim((string) ($member['full_name'] ?? 'o associado'))
                ),
                'tone' => 'success',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao criar cobrança externa de contribuição.', [
                'charge_id' => $chargeId,
                'competence' => $competence,
                'billing_type' => $billingType,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Não foi possível criar a cobrança externa: ' . $exception->getMessage(),
                'tone' => 'error',
            ]);
        }

        return $this->redirectToChargeDetail($response, $chargeId);
    }
}
