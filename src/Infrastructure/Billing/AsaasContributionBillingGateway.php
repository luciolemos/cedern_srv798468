<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use App\Domain\Billing\ContributionBillingGateway;

final class AsaasContributionBillingGateway implements ContributionBillingGateway
{
    private const SANDBOX_BASE_URL = 'https://api-sandbox.asaas.com/v3';
    private const PRODUCTION_BASE_URL = 'https://api.asaas.com/v3';

    private string $apiKey;
    private string $appEnv;
    private string $environment;
    private string $baseUrl;
    private string $userAgent;
    private bool $customerNotificationsDisabled;
    private bool $allowProductionInNonProduction;

    public function __construct()
    {
        $this->appEnv = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'production')));
        $this->environment = strtolower(trim((string) ($_ENV['ASAAS_ENVIRONMENT'] ?? 'sandbox')));
        $this->apiKey = trim((string) ($_ENV['ASAAS_API_KEY'] ?? ''));
        $this->baseUrl = $this->environment === 'production'
            ? self::PRODUCTION_BASE_URL
            : self::SANDBOX_BASE_URL;
        $this->userAgent = trim((string) ($_ENV['ASAAS_USER_AGENT'] ?? 'cedern-finance/1.0'));
        $this->customerNotificationsDisabled = filter_var(
            trim((string) ($_ENV['ASAAS_CUSTOMER_NOTIFICATION_DISABLED'] ?? 'true')),
            FILTER_VALIDATE_BOOLEAN
        );
        $this->allowProductionInNonProduction = filter_var(
            trim((string) ($_ENV['ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION'] ?? 'false')),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && !$this->isUnsafeProductionConfiguration();
    }

    public function providerKey(): string
    {
        return 'asaas';
    }

    public function createCharge(array $member, array $charge, string $billingType): array
    {
        $this->assertConfigured();

        $normalizedBillingType = $this->normalizeBillingType($billingType);
        $customerId = $this->ensureCustomerId($member);
        $paymentResponse = $this->requestJson('POST', '/payments', [
            'customer' => $customerId,
            'billingType' => $normalizedBillingType,
            'value' => round((float) ($charge['amount_due'] ?? 0), 2),
            'dueDate' => $this->resolveGatewayDueDate((string) ($charge['due_date'] ?? '')),
            'description' => $this->buildChargeDescription($member, $charge),
            'externalReference' => $this->buildExternalReference($charge),
        ]);

        $gatewayData = $this->normalizeGatewayPaymentPayload($paymentResponse);

        if ($normalizedBillingType === 'PIX') {
            $pixResponse = $this->requestJson(
                'GET',
                '/payments/' . rawurlencode((string) ($gatewayData['gateway_payment_id'] ?? '')) . '/pixQrCode'
            );
            $gatewayData = array_merge($gatewayData, $this->normalizeGatewayPixPayload($pixResponse));
        }

        return $gatewayData;
    }

    public function refreshCharge(array $charge): array
    {
        $this->assertConfigured();

        $paymentId = trim((string) ($charge['gateway_payment_id'] ?? ''));
        if ($paymentId === '') {
            throw new \RuntimeException('A cobrança local ainda não possui identificador do Asaas.');
        }

        $paymentResponse = $this->requestJson('GET', '/payments/' . rawurlencode($paymentId));
        $gatewayData = $this->normalizeGatewayPaymentPayload($paymentResponse);

        $billingType = strtoupper(trim((string) ($gatewayData['gateway_billing_type'] ?? '')));
        if ($billingType === 'PIX') {
            try {
                $pixResponse = $this->requestJson('GET', '/payments/' . rawurlencode($paymentId) . '/pixQrCode');
                $gatewayData = array_merge($gatewayData, $this->normalizeGatewayPixPayload($pixResponse));
            } catch (\Throwable $exception) {
            }
        }

        return $gatewayData;
    }

    private function assertConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Asaas não configurado. Defina ASAAS_API_KEY.');
        }

        if ($this->isUnsafeProductionConfiguration()) {
            throw new \RuntimeException(
                'Asaas em produção bloqueado neste ambiente. Use ASAAS_ENVIRONMENT=sandbox ou '
                . 'libere explicitamente ASAAS_ALLOW_PRODUCTION_IN_NON_PRODUCTION=true.'
            );
        }

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('A extensão cURL é obrigatória para integração com o Asaas.');
        }
    }

    private function isUnsafeProductionConfiguration(): bool
    {
        return $this->environment === 'production'
            && $this->isNonProductionAppEnv()
            && !$this->allowProductionInNonProduction;
    }

    private function isNonProductionAppEnv(): bool
    {
        return !in_array($this->appEnv, ['prod', 'production'], true);
    }

    /**
     * @param array<string, mixed> $member
     */
    private function ensureCustomerId(array $member): string
    {
        $memberId = (int) ($member['id'] ?? 0);
        $externalReference = $memberId > 0 ? 'member-user-' . $memberId : '';
        $cpfCnpj = $this->digitsOnly((string) ($member['cpf'] ?? ''));

        if ($cpfCnpj === '') {
            throw new \RuntimeException('CPF do associado é obrigatório para gerar cobrança no Asaas.');
        }

        if ($externalReference !== '') {
            $existingCustomer = $this->findCustomerByExternalReference($externalReference);
            if ($existingCustomer !== null) {
                return $this->syncExistingCustomer($existingCustomer, $member, $externalReference);
            }
        }

        $existingCustomer = $this->findCustomerByCpfCnpj($cpfCnpj);
        if ($existingCustomer !== null) {
            return $this->syncExistingCustomer($existingCustomer, $member, $externalReference);
        }

        $fullName = trim((string) ($member['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = 'Associado CEDE';
        }

        $payload = $this->buildCustomerPayload($member, $externalReference);
        $response = $this->requestJson('POST', '/customers', $payload);

        $customerId = trim((string) ($response['id'] ?? ''));
        if ($customerId === '') {
            throw new \RuntimeException('O Asaas não retornou o identificador do cliente.');
        }

        return $customerId;
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private function buildCustomerPayload(array $member, string $externalReference): array
    {
        $fullName = trim((string) ($member['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = 'Associado CEDE';
        }

        $payload = [
            'name' => $fullName,
            'cpfCnpj' => $this->digitsOnly((string) ($member['cpf'] ?? '')),
            'email' => strtolower(trim((string) ($member['email'] ?? ''))),
            'phone' => $this->digitsOnly((string) ($member['phone_landline'] ?? '')),
            'mobilePhone' => $this->digitsOnly((string) ($member['phone_mobile'] ?? '')),
            'address' => trim((string) ($member['street_address'] ?? '')),
            'addressNumber' => trim((string) ($member['address_number'] ?? '')),
            'complement' => trim((string) ($member['address_complement'] ?? '')),
            'province' => trim((string) ($member['neighborhood'] ?? '')),
            'postalCode' => $this->digitsOnly((string) ($member['postal_code'] ?? '')),
            'externalReference' => $externalReference,
            'notificationDisabled' => $this->customerNotificationsDisabled,
        ];

        $payload = array_filter($payload, static function ($value): bool {
            if (is_bool($value)) {
                return true;
            }

            return trim((string) $value) !== '';
        });

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCustomerByExternalReference(string $externalReference): ?array
    {
        if ($externalReference === '') {
            return null;
        }

        $response = $this->requestJson('GET', '/customers?limit=1&externalReference=' . rawurlencode($externalReference));
        $customers = $response['data'] ?? null;

        return is_array($customers) && isset($customers[0]) && is_array($customers[0]) ? $customers[0] : null;
    }

    /**
     * @param array<string, mixed> $customer
     * @param array<string, mixed> $member
     */
    private function syncExistingCustomer(array $customer, array $member, string $externalReference): string
    {
        $customerId = trim((string) ($customer['id'] ?? ''));
        if ($customerId === '') {
            throw new \RuntimeException('O Asaas não retornou o identificador do cliente existente.');
        }

        $payload = $this->buildCustomerPayload($member, $externalReference);
        $this->requestJson('PUT', '/customers/' . rawurlencode($customerId), $payload);

        return $customerId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCustomerByCpfCnpj(string $cpfCnpj): ?array
    {
        if ($cpfCnpj === '') {
            return null;
        }

        $response = $this->requestJson('GET', '/customers?limit=1&cpfCnpj=' . rawurlencode($cpfCnpj));
        $customers = $response['data'] ?? null;

        return is_array($customers) && isset($customers[0]) && is_array($customers[0]) ? $customers[0] : null;
    }

    /**
     * @param array<string, mixed> $member
     * @param array<string, mixed> $charge
     */
    private function buildChargeDescription(array $member, array $charge): string
    {
        $fullName = trim((string) ($member['full_name'] ?? 'Associado CEDE'));
        $competence = trim((string) ($charge['competence'] ?? ''));

        return sprintf('Contribuição mensal CEDE - %s - %s', $fullName, $competence !== '' ? $competence : date('Y-m'));
    }

    /**
     * @param array<string, mixed> $charge
     */
    private function buildExternalReference(array $charge): string
    {
        $chargeId = (int) ($charge['id'] ?? 0);

        return $chargeId > 0 ? 'cedern-contribution-' . $chargeId : 'cedern-contribution';
    }

    private function normalizeBillingType(string $value): string
    {
        return match (strtolower(trim($value))) {
            'pix' => 'PIX',
            'boleto' => 'BOLETO',
            default => throw new \RuntimeException('Forma de cobrança externa inválida.'),
        };
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function resolveGatewayDueDate(string $dueDate): string
    {
        $normalizedDueDate = trim($dueDate);
        $today = date('Y-m-d');

        if ($normalizedDueDate === '') {
            return $today;
        }

        $parsedDueDate = \DateTimeImmutable::createFromFormat('Y-m-d', $normalizedDueDate);
        if (!$parsedDueDate instanceof \DateTimeImmutable || $parsedDueDate->format('Y-m-d') !== $normalizedDueDate) {
            return $today;
        }

        return $normalizedDueDate < $today ? $today : $normalizedDueDate;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeGatewayPaymentPayload(array $payload): array
    {
        return [
            'gateway_provider' => $this->providerKey(),
            'gateway_customer_id' => trim((string) ($payload['customer'] ?? '')),
            'gateway_payment_id' => trim((string) ($payload['id'] ?? '')),
            'gateway_billing_type' => strtoupper(trim((string) ($payload['billingType'] ?? ''))),
            'gateway_status' => strtoupper(trim((string) ($payload['status'] ?? ''))),
            'gateway_invoice_url' => $this->nullableString($payload['invoiceUrl'] ?? null),
            'gateway_bank_slip_url' => $this->nullableString($payload['bankSlipUrl'] ?? null),
            'gateway_transaction_receipt_url' => $this->nullableString($payload['transactionReceiptUrl'] ?? null),
            'gateway_pix_payload' => null,
            'gateway_pix_encoded_image' => null,
            'gateway_pix_expiration_date' => null,
            'gateway_last_synced_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeGatewayPixPayload(array $payload): array
    {
        return [
            'gateway_pix_payload' => $this->nullableString($payload['payload'] ?? null),
            'gateway_pix_encoded_image' => $this->nullableString($payload['encodedImage'] ?? null),
            'gateway_pix_expiration_date' => $this->normalizeDateTimeValue($payload['expirationDate'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeDateTimeValue(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);
        if ($normalized === null) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Não foi possível iniciar a conexão com o Asaas.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: ' . $this->userAgent,
            'access_token: ' . $this->apiKey,
        ];

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

        if ($body !== null && strtoupper($method) !== 'GET') {
            $encodedBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encodedBody === false) {
                curl_close($curl);
                throw new \RuntimeException('Falha ao codificar requisição para o Asaas.');
            }

            curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedBody);
        }

        $rawResponse = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($rawResponse === false) {
            throw new \RuntimeException('Falha na comunicação com o Asaas: ' . $curlError);
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Resposta inválida do Asaas.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException($this->extractAsaasErrorMessage($decoded, $statusCode));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractAsaasErrorMessage(array $response, int $statusCode): string
    {
        $messages = [];
        $errors = $response['errors'] ?? null;

        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (!is_array($error)) {
                    continue;
                }

                $description = trim((string) ($error['description'] ?? ''));
                if ($description !== '') {
                    $messages[] = $description;
                }
            }
        }

        if ($messages === []) {
            $messages[] = 'O Asaas retornou erro HTTP ' . $statusCode . '.';
        }

        return implode(' ', $messages);
    }
}
