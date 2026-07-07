<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Member\MemberAuthRepository;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\Views\Twig;
use Throwable;

class MemberCompleteProfilePdfAction extends AbstractMemberGuardedPageAction
{
    use MemberProfilePhotoStorageTrait;

    private const DOCUMENT_TIMEZONE = 'America/Fortaleza';
    private const PLAYWRIGHT_BROWSER_CACHE_DIR = 'var/cache/ms-playwright';
    private const PRINTABLE_HTML_FALLBACK_NOTICE =
        'Gerador de PDF indisponível neste servidor no momento. Use a impressão do navegador para salvar em PDF.';
    private const PAYMENT_METHOD_LABELS = [
        'boleto' => 'Boleto',
        'pix' => 'Pix',
        'pix_automatico' => 'Pix Automático',
        'manual' => 'Pagamento manual',
    ];

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig, $memberAuthRepository);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $member = $this->resolveAuthenticatedMember($response, false);

        if ($member instanceof Response) {
            return $member;
        }

        $submittedData = strtoupper($request->getMethod()) === 'POST'
            ? (array) ($request->getParsedBody() ?? [])
            : [];

        try {
            $documentData = $this->buildDocumentData($request, $member, $submittedData);
            $html = $this->twig->getEnvironment()->render('pages/member-registration-form-pdf.twig', $documentData);
            $pdfBinary = $this->renderPdfFromHtml($html);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao gerar PDF do formulário de cadastro do associado.', [
                'member_id' => (int) ($member['id'] ?? 0),
                'error' => $exception->getMessage(),
            ]);

            $fallbackResponse = $this->respondWithPrintableHtmlFallback($request, $response, $member, $submittedData);
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

    /**
     * @param array<string, mixed> $member
     * @param array<string, mixed> $submittedData
     */
    protected function respondWithPrintableHtmlFallback(
        Request $request,
        Response $response,
        array $member,
        array $submittedData,
        ?string $documentUrlOverride = null
    ): ?Response {
        try {
            $documentData = $this->buildDocumentData($request, $member, $submittedData);
            $documentData['pdf_notice'] = self::PRINTABLE_HTML_FALLBACK_NOTICE;

            if ($documentUrlOverride !== null && trim($documentUrlOverride) !== '') {
                $documentData['pdf_document_url'] = $documentUrlOverride;
            }

            $html = $this->twig->getEnvironment()->render('pages/member-registration-form-pdf.twig', $documentData);
        } catch (Throwable $fallbackException) {
            $this->logger->error('Falha ao gerar fallback HTML do formulário de cadastro do associado.', [
                'member_id' => (int) ($member['id'] ?? 0),
                'error' => $fallbackException->getMessage(),
            ]);

            return null;
        }

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('X-Cede-Document-Fallback', 'html')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
    }

    protected function renderPdfFromHtml(string $html): string
    {
        $exportDirectory = $this->prepareExportDirectory();
        $exportToken = date('YmdHis') . '-' . bin2hex(random_bytes(6));
        $htmlPath = $exportDirectory . '/formulario-cadastro-associado-' . $exportToken . '.html';
        $pdfPath = $exportDirectory . '/formulario-cadastro-associado-' . $exportToken . '.pdf';

        if (file_put_contents($htmlPath, $html) === false) {
            throw new RuntimeException('Não foi possível preparar o HTML temporário do PDF.');
        }

        try {
            $this->runPdfCommand($htmlPath, $pdfPath);
            clearstatcache(true, $pdfPath);

            if (!is_file($pdfPath) || filesize($pdfPath) < 1) {
                throw new RuntimeException('O arquivo PDF não foi criado.');
            }

            $pdfBinary = file_get_contents($pdfPath);
            if ($pdfBinary === false) {
                throw new RuntimeException('Não foi possível ler o PDF gerado.');
            }

            return $pdfBinary;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
        }
    }

    /**
     * @param array<string, mixed> $member
     * @param array<string, mixed> $submittedData
     * @return array<string, mixed>
     */
    protected function buildDocumentData(Request $request, array $member, array $submittedData): array
    {
        [$existingBirthCity, $existingBirthState] = $this->parseBirthPlace((string) ($member['birth_place'] ?? ''));
        $usingSubmittedPreview = $submittedData !== [];

        $fullName = $this->resolveTextField($submittedData, 'full_name', (string) ($member['full_name'] ?? ''));
        $email = $this->resolveTextField($submittedData, 'email', (string) ($member['email'] ?? ''));
        $phoneMobile = $this->resolveTextField($submittedData, 'phone_mobile', (string) ($member['phone_mobile'] ?? ''));
        $phoneLandline = $this->resolveTextField($submittedData, 'phone_landline', (string) ($member['phone_landline'] ?? ''));
        $birthDate = $this->resolveTextField($submittedData, 'birth_date', (string) ($member['birth_date'] ?? ''));
        $birthState = strtoupper($this->resolveTextField($submittedData, 'birth_state', $existingBirthState));
        $birthCity = $this->resolveTextField($submittedData, 'birth_city', $existingBirthCity);
        $birthPlace = $this->resolveTextField($submittedData, 'birth_place', (string) ($member['birth_place'] ?? ''));
        $cpf = $this->resolveTextField($submittedData, 'cpf', (string) ($member['cpf'] ?? ''));
        $postalCode = $this->resolveTextField($submittedData, 'postal_code', (string) ($member['postal_code'] ?? ''));
        $streetAddress = $this->resolveTextField($submittedData, 'street_address', (string) ($member['street_address'] ?? ''));
        $addressNumber = $this->resolveTextField($submittedData, 'address_number', (string) ($member['address_number'] ?? ''));
        $addressComplement = $this->resolveTextField($submittedData, 'address_complement', (string) ($member['address_complement'] ?? ''));
        $neighborhood = $this->resolveTextField($submittedData, 'neighborhood', (string) ($member['neighborhood'] ?? ''));
        $addressCity = $this->resolveTextField($submittedData, 'address_city', (string) ($member['address_city'] ?? ''));
        $addressState = strtoupper($this->resolveTextField($submittedData, 'address_state', (string) ($member['address_state'] ?? '')));
        $preferredDueDay = $this->resolveTextField($submittedData, 'preferred_due_day', (string) ($member['preferred_due_day'] ?? ''));
        $contributionAmount = $this->resolveTextField($submittedData, 'contribution_amount', (string) ($member['contribution_amount'] ?? ''));
        $contributionPlanLabel = $this->resolveTextField($submittedData, 'contribution_plan_label', (string) ($member['contribution_plan_label'] ?? ''));
        $preferredPaymentMethod = strtolower($this->resolveTextField(
            $submittedData,
            'preferred_payment_method',
            (string) ($member['preferred_payment_method'] ?? '')
        ));

        $birthPlaceDisplay = $birthCity !== '' && $birthState !== ''
            ? $birthCity . '/' . $birthState
            : $birthPlace;
        $associationStatus = $this->resolveAssociationStatus((string) ($member['association_status'] ?? ''), (string) ($member['status'] ?? ''));
        $associationStatusLabel = $this->resolveAssociationStatusLabel($associationStatus);
        $memberTypeLabel = $this->resolveMemberTypeLabel((string) ($member['member_type_label'] ?? ''), (string) ($member['member_type'] ?? ''));
        $institutionalRole = trim((string) ($member['institutional_role'] ?? ''));
        $isContributor = (int) ($member['is_contributor'] ?? 0) === 1;
        $privacyAccepted = $usingSubmittedPreview
            ? (($submittedData['privacy_notice_acknowledged'] ?? '') === '1')
            : trim((string) ($member['privacy_notice_accepted_at'] ?? '')) !== '';
        $billingEmailOptIn = $usingSubmittedPreview
            ? (($submittedData['billing_email_opt_in'] ?? '') === '1')
            : (int) ($member['billing_email_opt_in'] ?? 0) === 1;
        $billingWhatsappOptIn = $usingSubmittedPreview
            ? (($submittedData['billing_whatsapp_opt_in'] ?? '') === '1')
            : (int) ($member['billing_whatsapp_opt_in'] ?? 0) === 1;

        $summary = [
            [
                'label' => 'Cadastro',
                'value' => 'Usuário SISCEDE',
            ],
            [
                'label' => 'Vínculo',
                'value' => $associationStatusLabel,
            ],
            [
                'label' => 'Tipo de Sócio',
                'value' => $memberTypeLabel,
            ],
            [
                'label' => 'Contribui',
                'value' => $isContributor ? 'Sim' : 'Não',
            ],
            [
                'label' => 'Função no CEDE',
                'value' => $institutionalRole !== '' ? $institutionalRole : 'Sem função definida',
            ],
        ];

        return [
            'pdf_document_url' => $this->buildAbsoluteAppUrl($request, '/membro/perfil/completar'),
            'pdf_generated_at' => (new DateTimeImmutable('now', new DateTimeZone(self::DOCUMENT_TIMEZONE)))
                ->format('d/m/Y H:i'),
            'pdf_notice' => '',
            'pdf_brand_logo_data_uri' => $this->resolveBrandLogoDataUri(),
            'pdf_member_photo_data_uri' => $this->resolveMemberPhotoDataUri((string) ($member['profile_photo_path'] ?? '')),
            'pdf_summary' => $summary,
            'pdf_signatories' => [
                [
                    'name' => $this->displayValue($fullName),
                    'role' => 'Associado(a)',
                ],
                [
                    'name' => $this->resolvePresidentName(),
                    'role' => 'Presidente do CEDE',
                ],
            ],
            'pdf_sections' => [
                [
                    'title' => 'Dados pessoais',
                    'rows' => [
                        ['label' => 'Nome completo', 'value' => $this->displayValue($fullName), 'wide' => true],
                        ['label' => 'Data de nascimento', 'value' => $this->formatDate($birthDate)],
                        ['label' => 'CPF', 'value' => $this->formatCpf($cpf)],
                        ['label' => 'Naturalidade', 'value' => $this->displayValue($birthPlaceDisplay)],
                        ['label' => 'E-mail', 'value' => $this->displayValue($email)],
                        ['label' => 'Celular', 'value' => $this->formatPhone($phoneMobile)],
                        ['label' => 'Telefone fixo', 'value' => $this->formatPhone($phoneLandline)],
                    ],
                ],
                [
                    'title' => 'Endereço',
                    'rows' => [
                        ['label' => 'Logradouro', 'value' => $this->displayValue($streetAddress), 'wide' => true],
                        ['label' => 'Número', 'value' => $this->displayValue($addressNumber), 'span' => 1],
                        ['label' => 'Bairro', 'value' => $this->displayValue($neighborhood), 'span' => 2],
                        ['label' => 'Complemento', 'value' => $this->displayValue($addressComplement), 'span' => 3],
                        ['label' => 'Cidade', 'value' => $this->displayValue($addressCity), 'span' => 3],
                        ['label' => 'UF', 'value' => $this->displayValue($addressState), 'span' => 1],
                        ['label' => 'CEP', 'value' => $this->formatPostalCode($postalCode), 'span' => 2],
                    ],
                ],
                [
                    'title' => 'Contribuição e cobrança',
                    'rows' => [
                        ['label' => 'Dia do vencimento', 'value' => $this->formatDueDay($preferredDueDay)],
                        ['label' => 'Valor da contribuição', 'value' => $this->formatCurrency($contributionAmount)],
                        [
                            'label' => 'Forma preferida de pagamento',
                            'value' => self::PAYMENT_METHOD_LABELS[$preferredPaymentMethod] ?? 'Não informado',
                        ],
                        ['label' => 'Autoriza envio por e-mail', 'value' => $billingEmailOptIn ? 'Sim' : 'Não'],
                        ['label' => 'Autoriza envio por WhatsApp', 'value' => $billingWhatsappOptIn ? 'Sim' : 'Não'],
                        ['label' => 'Ciência da privacidade', 'value' => $privacyAccepted ? 'Registrada' : 'Pendente'],
                        ['label' => 'Plano definido pela diretoria', 'value' => $this->displayValue($contributionPlanLabel), 'wide' => true],
                    ],
                ],
            ],
        ];
    }

    private function resolvePresidentName(): string
    {
        try {
            $users = $this->memberAuthRepository->findAllUsersForAdmin();
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao buscar presidente do CEDE para assinatura do PDF.', [
                'error' => $exception->getMessage(),
            ]);

            return 'Presidência do CEDE';
        }

        foreach ($users as $user) {
            $associationStatus = strtolower(trim((string) ($user['association_status'] ?? '')));
            if (!in_array($associationStatus, ['applicant', 'member', 'former'], true)) {
                $associationStatus = strtolower(trim((string) ($user['status'] ?? ''))) === 'pending'
                    ? 'applicant'
                    : 'member';
            }

            if (
                (string) ($user['status'] ?? '') === 'active'
                && $associationStatus === 'member'
                && trim((string) ($user['institutional_role'] ?? '')) === 'Presidente CEDE'
            ) {
                return $this->displayValue((string) ($user['full_name'] ?? ''));
            }
        }

        return 'Presidência do CEDE';
    }

    private function prepareExportDirectory(): string
    {
        $directory = dirname(__DIR__, 4) . '/var/cache/member-registration-pdf';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório temporário do PDF.');
        }

        if (!is_writable($directory)) {
            @chmod($directory, 0775);
            clearstatcache(true, $directory);
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('O diretório temporário do PDF não está gravável.');
        }

        return $directory;
    }

    private function runPdfCommand(string $htmlPath, string $pdfPath): void
    {
        $nodeBinary = trim((string) ($_ENV['NODE_BINARY'] ?? 'node'));
        $nodeScript = dirname(__DIR__, 4) . '/scripts/export_bookshop_manual_pdf.mjs';
        $playwrightBrowsersPath = $this->preparePlaywrightBrowserCacheDirectory();

        if (!is_file($nodeScript)) {
            throw new RuntimeException('O script de exportação do PDF não foi encontrado.');
        }

        $command = sprintf(
            'PLAYWRIGHT_BROWSERS_PATH=%s %s %s %s %s',
            escapeshellarg($playwrightBrowsersPath),
            escapeshellarg($nodeBinary),
            escapeshellarg($nodeScript),
            escapeshellarg($htmlPath),
            escapeshellarg($pdfPath)
        );

        if (function_exists('proc_open')) {
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 4));
            if (!is_resource($process)) {
                throw new RuntimeException('Não foi possível iniciar o gerador de PDF.');
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Falha ao executar o gerador de PDF.'
                    . ($stderr !== '' ? ' ' . trim($stderr) : '')
                    . ($stdout !== '' ? ' ' . trim($stdout) : '')
                );
            }

            return;
        }

        if (function_exists('exec')) {
            $output = [];
            $exitCode = 0;
            exec($command . ' 2>&1', $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException('Falha ao executar o gerador de PDF. ' . trim(implode("\n", $output)));
            }

            return;
        }

        throw new RuntimeException('Nenhum executor de comando está disponível para gerar o PDF.');
    }

    private function preparePlaywrightBrowserCacheDirectory(): string
    {
        $directory = dirname(__DIR__, 4) . '/' . self::PLAYWRIGHT_BROWSER_CACHE_DIR;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o cache local do Playwright.');
        }

        @chmod($directory, 0775);
        clearstatcache(true, $directory);

        if (!is_dir($directory) || !is_readable($directory) || !is_executable($directory)) {
            throw new RuntimeException('O cache local do Playwright não está acessível.');
        }

        return $directory;
    }

    private function resolveTextField(array $submittedData, string $field, string $fallback): string
    {
        if (!array_key_exists($field, $submittedData)) {
            return trim($fallback);
        }

        $value = $submittedData[$field];

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseBirthPlace(string $birthPlace): array
    {
        $normalized = trim($birthPlace);
        if ($normalized === '' || !str_contains($normalized, '/')) {
            return ['', ''];
        }

        [$city, $state] = array_pad(explode('/', $normalized, 2), 2, '');

        return [trim($city), strtoupper(trim($state))];
    }

    private function resolveAssociationStatus(string $associationStatus, string $accountStatus): string
    {
        $normalizedAssociationStatus = strtolower(trim($associationStatus));
        if (in_array($normalizedAssociationStatus, ['applicant', 'member', 'former'], true)) {
            return $normalizedAssociationStatus;
        }

        return strtolower(trim($accountStatus)) === 'pending' ? 'applicant' : 'member';
    }

    private function resolveAssociationStatusLabel(string $associationStatus): string
    {
        return match (strtolower(trim($associationStatus))) {
            'member' => 'Associado',
            'former' => 'Desligado',
            default => 'Solicitante',
        };
    }

    private function resolveMemberTypeLabel(string $memberTypeLabel, string $memberType): string
    {
        $normalizedLabel = trim($memberTypeLabel);
        if ($normalizedLabel !== '') {
            return $normalizedLabel;
        }

        return match (strtolower(trim($memberType))) {
            'fundador' => 'Fundador',
            'efetivo' => 'Efetivo',
            default => 'Não definido',
        };
    }

    private function displayValue(string $value, string $fallback = 'Não informado'): string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function formatDate(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return 'Não informado';
        }

        try {
            return (new DateTimeImmutable($normalized))->format('d/m/Y');
        } catch (Throwable) {
            return $normalized;
        }
    }

    private function formatDueDay(string $value): string
    {
        $day = (int) trim($value);
        if ($day < 1 || $day > 28) {
            return 'Não informado';
        }

        return 'Dia ' . sprintf('%02d', $day);
    }

    private function formatCurrency(string $value): string
    {
        $normalized = $this->normalizeCurrencyInput($value);
        if ($normalized === null) {
            return 'Não informado';
        }

        return 'R$ ' . number_format((float) $normalized, 2, ',', '.');
    }

    private function normalizeCurrencyInput(string $value): ?string
    {
        $normalized = preg_replace('/\s+/', '', trim($value)) ?? '';
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        $amount = (float) $normalized;
        if ($amount <= 0) {
            return null;
        }

        return number_format($amount, 2, '.', '');
    }

    private function formatCpf(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?? '';
        if (strlen($digits) !== 11) {
            return $this->displayValue($value);
        }

        return substr($digits, 0, 3)
            . '.'
            . substr($digits, 3, 3)
            . '.'
            . substr($digits, 6, 3)
            . '-'
            . substr($digits, 9, 2);
    }

    private function formatPostalCode(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?? '';
        if (strlen($digits) !== 8) {
            return $this->displayValue($value);
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
    }

    private function formatPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?? '';
        if (strlen($digits) === 11) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 5),
                substr($digits, 7, 4)
            );
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 4),
                substr($digits, 6, 4)
            );
        }

        return $this->displayValue($value);
    }

    private function resolveMemberPhotoDataUri(string $relativePath): ?string
    {
        $absolutePath = $this->resolveManagedMemberProfilePhotoAbsolutePath($relativePath);
        return $this->resolveImageDataUri($absolutePath);
    }

    private function resolveBrandLogoDataUri(): ?string
    {
        $absolutePath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede12_logo.png';

        return $this->resolveImageDataUri($absolutePath);
    }

    private function resolveImageDataUri(?string $absolutePath): ?string
    {
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => function_exists('mime_content_type')
                ? (mime_content_type($absolutePath) ?: 'application/octet-stream')
                : 'application/octet-stream',
        };

        return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
    }
}
