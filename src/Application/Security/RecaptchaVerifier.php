<?php

declare(strict_types=1);

namespace App\Application\Security;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RecaptchaVerifier
{
    private const VERIFY_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function isEnabled(): bool
    {
        return $this->resolveBooleanEnv('RECAPTCHA_ENABLED');
    }

    public function isReady(): bool
    {
        return $this->isEnabled()
            && $this->getSiteKey() !== ''
            && $this->getSecretKey() !== '';
    }

    public function getSiteKey(): string
    {
        return trim((string) ($_ENV['RECAPTCHA_SITE_KEY'] ?? ''));
    }

    public function getMinScore(): float
    {
        $score = (float) ($_ENV['RECAPTCHA_MIN_SCORE'] ?? 0.5);

        if ($score < 0) {
            return 0.0;
        }

        if ($score > 1) {
            return 1.0;
        }

        return $score;
    }

    /**
     * @return array{ok: bool, message: string, score: float|null, error_codes: list<string>}
     */
    public function verifySubmission(
        string $token,
        string $expectedAction,
        ?string $requestHost = null,
        ?string $remoteIp = null
    ): array {
        $requestHost = strtolower(trim((string) $requestHost));
        $remoteIp = trim((string) $remoteIp);
        $baseContext = [
            'expected_action' => trim($expectedAction),
            'request_host' => $requestHost,
        ];

        if ($remoteIp !== '') {
            $baseContext['remote_ip'] = $remoteIp;
        }

        if (!$this->isEnabled()) {
            return [
                'ok' => true,
                'message' => '',
                'score' => null,
                'error_codes' => [],
            ];
        }

        if (!$this->isReady()) {
            $this->logger->warning('reCAPTCHA enabled but not fully configured.', $baseContext + [
                'has_site_key' => $this->getSiteKey() !== '',
                'has_secret_key' => $this->getSecretKey() !== '',
            ]);

            return $this->buildFailure('A validacao anti-spam esta indisponivel no momento.');
        }

        $token = trim($token);
        if ($token === '') {
            $this->logger->info('reCAPTCHA submission missing token.', $baseContext);

            return $this->buildFailure('Confirme a validacao anti-spam e tente novamente.');
        }

        $payload = [
            'secret' => $this->getSecretKey(),
            'response' => $token,
        ];

        if ($remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $result = $this->performVerificationRequest($payload);
        } catch (\Throwable $exception) {
            $this->logger->error('reCAPTCHA verification request failed.', $baseContext + [
                'exception_message' => $exception->getMessage(),
            ]);

            return $this->buildFailure('Nao foi possivel validar a verificacao de seguranca. Tente novamente.');
        }

        $errorCodes = $this->normalizeErrorCodes($result['error-codes'] ?? []);
        $score = $this->parseScore($result['score'] ?? null);

        if ((bool) ($result['success'] ?? false) !== true) {
            $this->logger->warning('reCAPTCHA verification rejected by upstream.', $baseContext + [
                'score' => $score,
                'error_codes' => $errorCodes,
            ]);

            return $this->buildFailure(
                'Nao foi possivel validar a verificacao de seguranca. Tente novamente.',
                $score,
                $errorCodes
            );
        }

        $resolvedAction = trim((string) ($result['action'] ?? ''));
        if ($expectedAction !== '' && $resolvedAction !== $expectedAction) {
            $this->logger->warning('reCAPTCHA action mismatch.', $baseContext + [
                'score' => $score,
                'error_codes' => $errorCodes,
                'resolved_action' => $resolvedAction,
            ]);

            return $this->buildFailure(
                'A verificacao de seguranca expirou. Atualize a pagina e tente novamente.',
                $score,
                $errorCodes
            );
        }

        $expectedHostname = $this->resolveExpectedHostname($requestHost);
        $receivedHostname = trim((string) ($result['hostname'] ?? ''));
        if ($expectedHostname !== '' && !$this->hostnameMatches($receivedHostname, $expectedHostname)) {
            $this->logger->warning('reCAPTCHA hostname mismatch.', $baseContext + [
                'score' => $score,
                'error_codes' => $errorCodes,
                'expected_hostname' => $expectedHostname,
                'received_hostname' => $receivedHostname,
            ]);

            return $this->buildFailure(
                'A verificacao de seguranca foi rejeitada para este dominio.',
                $score,
                $errorCodes
            );
        }

        $minimumScore = $this->getMinScore();
        if ($score !== null && $score < $minimumScore) {
            $this->logger->warning('reCAPTCHA score below threshold.', $baseContext + [
                'score' => $score,
                'minimum_score' => $minimumScore,
                'error_codes' => $errorCodes,
                'resolved_action' => $resolvedAction,
                'received_hostname' => $receivedHostname,
            ]);

            return $this->buildFailure(
                'Sua solicitacao nao passou na verificacao de seguranca. Tente novamente.',
                $score,
                $errorCodes
            );
        }

        return [
            'ok' => true,
            'message' => '',
            'score' => $score,
            'error_codes' => $errorCodes,
        ];
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, mixed>
     */
    protected function performVerificationRequest(array $payload): array
    {
        $body = http_build_query($payload, '', '&');

        if (function_exists('curl_init')) {
            $rawResponse = $this->performCurlRequest($body);
        } else {
            $rawResponse = $this->performStreamRequest($body);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Resposta invalida do endpoint do reCAPTCHA.');
        }

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function normalizeErrorCodes(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && trim($item) !== ''
        ));
    }

    private function getSecretKey(): string
    {
        return trim((string) ($_ENV['RECAPTCHA_SECRET_KEY'] ?? ''));
    }

    private function resolveBooleanEnv(string $key): bool
    {
        $rawValue = trim((string) ($_ENV[$key] ?? 'false'));
        if ($rawValue === '') {
            return false;
        }

        return filter_var($rawValue, FILTER_VALIDATE_BOOLEAN);
    }

    private function resolveExpectedHostname(?string $requestHost): string
    {
        $allowedHostname = strtolower(trim((string) ($_ENV['RECAPTCHA_ALLOWED_HOSTNAME'] ?? '')));
        if ($allowedHostname !== '') {
            return $allowedHostname;
        }

        $requestHost = strtolower(trim((string) $requestHost));
        if ($requestHost !== '') {
            return $requestHost;
        }

        $defaultPageUrl = trim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? ''));
        $parsedHost = strtolower(trim((string) parse_url($defaultPageUrl, PHP_URL_HOST)));

        return $parsedHost;
    }

    private function hostnameMatches(string $receivedHostname, string $expectedHostname): bool
    {
        $receivedHostname = strtolower(trim($receivedHostname));
        $expectedHostname = strtolower(trim($expectedHostname));

        if ($receivedHostname === '' || $expectedHostname === '') {
            return false;
        }

        return $receivedHostname === $expectedHostname
            || str_ends_with($receivedHostname, '.' . $expectedHostname);
    }

    private function parseScore(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $score = (float) $value;
        if ($score < 0) {
            return 0.0;
        }

        if ($score > 1) {
            return 1.0;
        }

        return $score;
    }

    /**
     * @return array{ok: bool, message: string, score: float|null, error_codes: list<string>}
     */
    private function buildFailure(string $message, ?float $score = null, array $errorCodes = []): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'score' => $score,
            'error_codes' => $errorCodes,
        ];
    }

    private function performCurlRequest(string $body): string
    {
        $handle = curl_init(self::VERIFY_ENDPOINT);
        if ($handle === false) {
            throw new \RuntimeException('Falha ao inicializar cliente cURL para reCAPTCHA.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($handle);
        if (!is_string($response) || $response === '') {
            $error = curl_error($handle);
            curl_close($handle);
            throw new \RuntimeException(
                $error !== ''
                    ? $error
                    : 'Resposta vazia do endpoint do reCAPTCHA.'
            );
        }

        curl_close($handle);

        return $response;
    }

    private function performStreamRequest(string $body): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents(self::VERIFY_ENDPOINT, false, $context);
        if (!is_string($response) || $response === '') {
            throw new \RuntimeException('Nao foi possivel obter resposta do endpoint do reCAPTCHA.');
        }

        return $response;
    }
}
