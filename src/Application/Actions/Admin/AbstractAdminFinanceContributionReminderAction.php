<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Support\InstitutionalEmailTemplate;
use App\Application\Support\SmtpSettings;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

abstract class AbstractAdminFinanceContributionReminderAction extends AbstractAdminFinanceContributionsAction
{
    /**
     * @return array{charge: array<string, mixed>, member: array<string, mixed>, competence: string}|null
     */
    protected function loadChargeContext(int $chargeId): ?array
    {
        if ($chargeId <= 0) {
            return null;
        }

        $charge = $this->memberAuthRepository->findContributionChargeById($chargeId);
        if ($charge === null) {
            return null;
        }

        $memberId = (int) ($charge['member_user_id'] ?? 0);
        $member = $memberId > 0 ? $this->memberAuthRepository->findById($memberId) : null;
        if ($member === null) {
            return null;
        }

        return [
            'charge' => $charge,
            'member' => $member,
            'competence' => $this->normalizeCompetence($charge['competence'] ?? null),
        ];
    }

    protected function formatDate(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    protected function formatCurrency(mixed $value): string
    {
        $numericValue = is_numeric((string) $value) ? (float) $value : 0.0;

        return 'R$ ' . number_format($numericValue, 2, ',', '.');
    }

    protected function resolvePaymentMethodLabel(string $value): string
    {
        $normalized = strtolower(trim($value));

        return self::PAYMENT_METHOD_LABELS[$normalized] ?? 'Pagamento manual';
    }

    /**
     * @throws Exception
     */
    protected function sendContributionReminderEmail(array $charge, array $member): void
    {
        $smtpHost = trim((string) ($_ENV['MAIL_HOST'] ?? 'smtp.hostinger.com'));
        $smtpPort = (int) ($_ENV['MAIL_PORT'] ?? 465);
        $smtpUser = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
        $smtpPass = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $fromEmail = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? $smtpUser));
        $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'CEDE - Financeiro'));
        $normalizedEmail = strtolower(trim((string) ($member['email'] ?? '')));

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
            throw new \RuntimeException('Configuração SMTP incompleta para envio de lembretes financeiros.');
        }

        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('E-mail inválido para envio do lembrete financeiro.');
        }

        $fullName = trim((string) ($member['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = 'Associado CEDE';
        }

        $subject = $this->buildContributionReminderSubject($charge);
        $htmlBody = $this->buildContributionReminderHtmlBody($charge, $member);
        $altBody = $this->buildContributionReminderAltBody($charge, $member);

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->SMTPAuth = true;
        $mailer->Username = $smtpUser;
        $mailer->Password = $smtpPass;
        $mailer->Port = $smtpPort;
        $mailer->SMTPSecure = SmtpSettings::resolveConfiguredEncryption($smtpPort);
        $mailer->CharSet = 'UTF-8';
        $mailer->Sender = $fromEmail;

        $messageIdDomain = strtolower(trim((string) strrchr($fromEmail, '@')));
        $messageIdDomain = ltrim($messageIdDomain, '@');
        if ($messageIdDomain !== '') {
            $mailer->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(12)), $messageIdDomain);
        }

        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($normalizedEmail, $fullName);
        $mailer->addReplyTo($fromEmail, $fromName);

        $logoPath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede4_logo.png';
        if (is_file($logoPath)) {
            $mailer->addEmbeddedImage($logoPath, 'cedern-logo', 'cede4_logo.png', 'base64', 'image/png');
        }

        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $htmlBody;
        $mailer->AltBody = $altBody;
        $mailer->send();
    }

    protected function buildContributionReminderSubject(array $charge): string
    {
        $competence = $this->normalizeCompetence($charge['competence'] ?? null);

        return 'Lembrete de contribuição CEDE · ' . $this->formatCompetenceLabel($competence);
    }

    protected function buildContributionReminderText(array $charge, array $member): string
    {
        $fullName = trim((string) ($member['full_name'] ?? 'Associado CEDE'));
        $competence = $this->normalizeCompetence($charge['competence'] ?? null);
        $dueDate = $this->formatDate((string) ($charge['due_date'] ?? ''));
        $amount = $this->formatCurrency($charge['amount_due'] ?? 0);
        $paymentMethod = $this->resolvePaymentMethodLabel((string) (
            $charge['preferred_payment_method']
            ?? $member['preferred_payment_method']
            ?? 'manual'
        ));
        $pixKey = $this->resolveContributionPixKey();
        $invoiceUrl = trim((string) ($charge['gateway_invoice_url'] ?? ''));
        $pixPayload = trim((string) ($charge['gateway_pix_payload'] ?? ''));

        $lines = [
            'Olá, ' . $fullName . '.',
            'Este é um lembrete da contribuição mensal do CEDE referente a ' . $this->formatCompetenceLabel($competence) . '.',
            'Valor: ' . $amount . '.',
            'Vencimento: ' . $dueDate . '.',
            'Forma preferencial: ' . $paymentMethod . '.',
        ];

        if ($pixKey !== null && in_array(strtolower(trim((string) ($charge['preferred_payment_method'] ?? ''))), ['pix', 'pix_automatico'], true)) {
            $lines[] = 'Chave PIX do CEDE: ' . $pixKey . '.';
        }

        if ($invoiceUrl !== '') {
            $lines[] = 'Link da cobrança: ' . $invoiceUrl . '.';
        }

        if ($pixPayload !== '' && strtoupper(trim((string) ($charge['gateway_billing_type'] ?? ''))) === 'PIX') {
            $lines[] = 'Copia e cola Pix: ' . $pixPayload . '.';
        }

        $lines[] = 'Se o pagamento já foi realizado, desconsidere esta mensagem.';
        $lines[] = 'Em caso de dúvida, responda este contato oficial do CEDE.';

        return implode("\n", $lines);
    }

    protected function buildContributionReminderHtmlBody(array $charge, array $member): string
    {
        $fullName = trim((string) ($member['full_name'] ?? 'Associado CEDE'));
        $competence = $this->normalizeCompetence($charge['competence'] ?? null);
        $dueDate = $this->formatDate((string) ($charge['due_date'] ?? ''));
        $amount = $this->formatCurrency($charge['amount_due'] ?? 0);
        $paymentMethod = $this->resolvePaymentMethodLabel((string) (
            $charge['preferred_payment_method']
            ?? $member['preferred_payment_method']
            ?? 'manual'
        ));
        $headerMetaHtml = InstitutionalEmailTemplate::buildInstitutionHeaderMeta();
        $memberLoginUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org'), '/') . '/entrar';
        $contactUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org'), '/') . '/contato';
        $pixKey = $this->resolveContributionPixKey();
        $invoiceUrl = trim((string) ($charge['gateway_invoice_url'] ?? ''));
        $pixPayload = trim((string) ($charge['gateway_pix_payload'] ?? ''));
        $safeFullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeCompetence = htmlspecialchars($this->formatCompetenceLabel($competence), ENT_QUOTES, 'UTF-8');
        $safeDueDate = htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8');
        $safeAmount = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
        $safePaymentMethod = htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8');

        $detailLines = [
            '<p style="margin:0 0 8px;"><strong>Competência:</strong> ' . $safeCompetence . '</p>',
            '<p style="margin:0 0 8px;"><strong>Valor:</strong> ' . $safeAmount . '</p>',
            '<p style="margin:0 0 8px;"><strong>Vencimento:</strong> ' . $safeDueDate . '</p>',
            '<p style="margin:0;"><strong>Forma preferencial:</strong> ' . $safePaymentMethod . '</p>',
        ];

        if ($pixKey !== null && in_array(strtolower(trim((string) ($charge['preferred_payment_method'] ?? ''))), ['pix', 'pix_automatico'], true)) {
            $detailLines[] = '<p style="margin:12px 0 0;"><strong>Chave PIX do CEDE:</strong> '
                . htmlspecialchars($pixKey, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        if ($pixPayload !== '' && strtoupper(trim((string) ($charge['gateway_billing_type'] ?? ''))) === 'PIX') {
            $detailLines[] = '<p style="margin:12px 0 0;"><strong>Copia e cola Pix:</strong><br>'
                . htmlspecialchars($pixPayload, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return InstitutionalEmailTemplate::buildLayout(
            'Lembrete de contribuição mensal',
            '<p style="margin:0 0 14px;">Olá, <strong>' . $safeFullName . '</strong>.</p>'
            . '<p style="margin:0 0 14px;">Este é um lembrete da contribuição mensal do CEDE referente a <strong>'
            . $safeCompetence . '</strong>.</p>'
            . '<div style="margin:0 0 16px;padding:14px 16px;border:1px solid #dbe4ee;'
            . 'border-radius:12px;background:#f8fafc;">'
            . implode('', $detailLines)
            . '</div>'
            . '<div style="margin:0 0 16px;padding:16px;border-left:4px solid #2563eb;'
            . 'border-radius:10px;background:#f8fafc;">'
            . '<p style="margin:0;">Se o pagamento já foi realizado, desconsidere esta mensagem. '
            . 'Em caso de dúvida, use os canais oficiais do CEDE.</p>'
            . '</div>'
            . InstitutionalEmailTemplate::buildActionGroup([
                ...($invoiceUrl !== '' ? [[
                    'href' => $invoiceUrl,
                    'label' => 'Abrir cobrança',
                    'is_primary' => true,
                ]] : []),
                [
                    'href' => $memberLoginUrl,
                    'label' => 'Abrir área do membro',
                    'is_primary' => $invoiceUrl === '',
                ],
                [
                    'href' => $contactUrl,
                    'label' => 'Falar com o CEDE',
                    'is_primary' => false,
                ],
            ]),
            $this->resolveEmbeddedLogoSrc(),
            $headerMetaHtml
        );
    }

    protected function buildContributionReminderAltBody(array $charge, array $member): string
    {
        return $this->buildContributionReminderText($charge, $member);
    }

    protected function buildWhatsappReminderUrl(array $charge, array $member): ?string
    {
        $phone = $this->normalizeWhatsappNumber((string) ($member['phone_mobile'] ?? ''));
        if ($phone === null) {
            return null;
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($this->buildContributionReminderText($charge, $member));
    }

    protected function normalizeWhatsappNumber(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return $digits;
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return '55' . $digits;
        }

        return strlen($digits) >= 12 ? $digits : null;
    }

    protected function resolveContributionPixKey(): ?string
    {
        $contentFile = dirname(__DIR__, 4) . '/app/content/home.php';
        if (!is_file($contentFile)) {
            return null;
        }

        /** @var array<string, mixed> $content */
        $content = require $contentFile;
        $donationOptions = $content['donationOptions'] ?? null;
        if (!is_array($donationOptions)) {
            return null;
        }

        foreach ($donationOptions as $option) {
            if (!is_array($option)) {
                continue;
            }

            $pixKey = trim((string) ($option['pixKey'] ?? ''));
            if ($pixKey !== '') {
                return $pixKey;
            }
        }

        return null;
    }

    protected function resolveEmbeddedLogoSrc(): ?string
    {
        $logoPath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede4_logo.png';

        return is_file($logoPath) ? 'cid:cedern-logo' : null;
    }
}
