<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionGatewaySyncAction extends AbstractAdminFinanceContributionGatewayAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $chargeId = (int) ($request->getAttribute('id') ?? 0);
        $context = $this->loadChargeContext($chargeId);

        if ($context === null) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Cobrança não encontrada para sincronização.',
                'tone' => 'error',
            ]);

            return $this->redirectToList($response, date('Y-m'));
        }

        $charge = $context['charge'];
        $competence = $context['competence'];

        if (!$this->gatewayConfigured()) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Gateway externo não configurado para sincronização.',
                'tone' => 'error',
            ]);

            return $this->redirectToChargeDetail($response, $chargeId);
        }

        if (trim((string) ($charge['gateway_payment_id'] ?? '')) === '') {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Ainda não existe cobrança externa para sincronizar.',
                'tone' => 'error',
            ]);

            return $this->redirectToChargeDetail($response, $chargeId);
        }

        try {
            $syncedCharge = $this->syncGatewayCharge($charge);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Cobrança externa sincronizada. Status atual no gateway: '
                    . $this->normalizeGatewayStatusLabel((string) ($syncedCharge['gateway_status'] ?? '')),
                'tone' => 'success',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao sincronizar cobrança externa.', [
                'charge_id' => $chargeId,
                'competence' => $competence,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'message' => 'Não foi possível sincronizar a cobrança externa: ' . $exception->getMessage(),
                'tone' => 'error',
            ]);
        }

        return $this->redirectToChargeDetail($response, $chargeId);
    }
}
