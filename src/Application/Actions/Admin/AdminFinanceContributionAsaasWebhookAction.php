<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionAsaasWebhookAction extends AbstractAdminFinanceContributionGatewayAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $configuredToken = trim((string) ($_ENV['ASAAS_WEBHOOK_TOKEN'] ?? ''));
        $incomingToken = trim($request->getHeaderLine('asaas-access-token'));

        if ($configuredToken !== '' && $incomingToken !== $configuredToken) {
            return $this->jsonResponse($response->withStatus(401), [
                'ok' => false,
                'message' => 'Webhook token inválido.',
            ]);
        }

        if (!$this->gatewayConfigured()) {
            return $this->jsonResponse($response->withStatus(202), [
                'ok' => true,
                'message' => 'Gateway desabilitado localmente.',
            ]);
        }

        $payload = $this->resolveJsonPayload($request);
        $paymentId = trim((string) (($payload['payment']['id'] ?? '') ?: ''));

        if ($paymentId === '') {
            return $this->jsonResponse($response->withStatus(400), [
                'ok' => false,
                'message' => 'payment.id ausente no webhook.',
            ]);
        }

        $charge = $this->memberAuthRepository->findContributionChargeByGatewayPaymentId($paymentId);
        if ($charge === null) {
            return $this->jsonResponse($response->withStatus(202), [
                'ok' => true,
                'message' => 'Cobrança local não encontrada para o payment.id recebido.',
            ]);
        }

        try {
            $this->syncGatewayCharge($charge);

            return $this->jsonResponse($response, [
                'ok' => true,
                'message' => 'Webhook processado.',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao processar webhook do Asaas para contribuição.', [
                'gateway_payment_id' => $paymentId,
                'error' => $exception->getMessage(),
            ]);

            return $this->jsonResponse($response->withStatus(500), [
                'ok' => false,
                'message' => 'Falha ao processar webhook.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveJsonPayload(Request $request): array
    {
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody)) {
            return $parsedBody;
        }

        $rawBody = (string) $request->getBody();
        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(Response $response, array $payload): Response
    {
        $response->getBody()->write((string) json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
