<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Application\Security\RecaptchaVerifier;
use App\Application\Support\InstitutionalEmailTemplate;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class ContactPageAction extends AbstractPageAction
{
    private const RECAPTCHA_ACTION = 'contact_submit';

    private RecaptchaVerifier $recaptchaVerifier;

    public function __construct(LoggerInterface $logger, Twig $twig, RecaptchaVerifier $recaptchaVerifier)
    {
        parent::__construct($logger, $twig);
        $this->recaptchaVerifier = $recaptchaVerifier;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $method = strtoupper($request->getMethod());

        $form = $this->getEmptyForm();
        $errors = [];
        $status = '';

        if ($method !== 'POST') {
            $flash = $this->consumeContactFlash();
            $status = (string) ($flash['status'] ?? '');
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));
            $flashForm = (array) ($flash['form'] ?? []);
            $form = array_merge($form, [
                'name' => trim((string) ($flashForm['name'] ?? '')),
                'email' => strtolower(trim((string) ($flashForm['email'] ?? ''))),
                'subject' => trim((string) ($flashForm['subject'] ?? '')),
                'message' => trim((string) ($flashForm['message'] ?? '')),
                'company' => '',
            ]);
        }

        if ($method === 'POST') {
            $body = (array) $request->getParsedBody();

            $form['name'] = trim((string) ($body['name'] ?? ''));
            $form['email'] = strtolower(trim((string) ($body['email'] ?? '')));
            $form['subject'] = trim((string) ($body['subject'] ?? ''));
            $form['message'] = trim((string) ($body['message'] ?? ''));
            $form['company'] = trim((string) ($body['company'] ?? ''));

            if ($form['company'] !== '') {
                $this->storeContactFlash([
                    'status' => 'sent',
                    'errors' => [],
                    'form' => $this->getEmptyForm(),
                ]);

                return $response->withHeader('Location', '/contato')->withStatus(303);
            } else {
                $recaptchaValidation = $this->verifyRecaptchaToken(
                    $request,
                    $this->recaptchaVerifier,
                    (string) ($body['recaptcha_token'] ?? ''),
                    self::RECAPTCHA_ACTION
                );
                if (!$recaptchaValidation['ok']) {
                    $errors[] = $recaptchaValidation['message'];
                }

                if ($form['name'] === '') {
                    $errors[] = 'Informe seu nome.';
                }

                if ($form['email'] === '' || filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = 'Informe um e-mail válido.';
                }

                if ($form['message'] === '' || mb_strlen($form['message']) < 10) {
                    $errors[] = 'Escreva uma mensagem com pelo menos 10 caracteres.';
                }

                if ($form['subject'] === '') {
                    $form['subject'] = 'Contato pelo formulário do site';
                }

                if (empty($errors)) {
                    try {
                        $this->sendContactEmail($form['name'], $form['email'], $form['subject'], $form['message']);
                        $this->storeContactFlash([
                            'status' => 'sent',
                            'errors' => [],
                            'form' => $this->getEmptyForm(),
                        ]);

                        return $response->withHeader('Location', '/contato')->withStatus(303);
                    } catch (\Throwable $exception) {
                        $this->logger->error('Falha no envio de e-mail de contato.', [
                            'error' => $exception->getMessage(),
                        ]);
                        error_log('[cedern contato] falha no envio: ' . $exception->getMessage() . ' | APP_ENV='
                            . (string) ($_ENV['APP_ENV'] ?? '')
                            . ' | APP_ENV_FILE=' . (string) ($_ENV['APP_ENV_FILE'] ?? '')
                            . ' | APP_LOG_PATH=' . (string) ($_ENV['APP_LOG_PATH'] ?? '')
                            . ' | MAIL_HOST=' . (string) ($_ENV['MAIL_HOST'] ?? '')
                            . ' | MAIL_PORT=' . (string) ($_ENV['MAIL_PORT'] ?? '')
                            . ' | MAIL_FROM_ADDRESS=' . (string) ($_ENV['MAIL_FROM_ADDRESS'] ?? '')
                            . ' | MAIL_TO_ADDRESS=' . (string) ($_ENV['MAIL_TO_ADDRESS'] ?? '')
                        );
                        $status = 'error';
                        $errors[] = 'Não foi possível enviar sua mensagem agora. Tente novamente em instantes.';
                    }
                }
            }

            $this->storeContactFlash([
                'status' => $status,
                'errors' => $errors,
                'form' => [
                    'name' => $form['name'],
                    'email' => $form['email'],
                    'subject' => $form['subject'],
                    'message' => $form['message'],
                    'company' => '',
                ],
            ]);

            return $response->withHeader('Location', '/contato')->withStatus(303);
        }

        return $this->renderPage($response, 'pages/contact.twig', [
            'contact_form' => $form,
            'contact_form_errors' => $errors,
            'contact_form_status' => $status,
            'page_title' => 'Contato | CEDE',
            'page_url' => 'https://cedern.org/contato',
            'page_description' => 'Veja o endereço, mapa e canais de contato do CEDE.',
        ]);
    }

    /**
     * @throws Exception
     */
    private function sendContactEmail(string $name, string $email, string $subject, string $message): void
    {
        $smtpHost = trim((string) ($_ENV['MAIL_HOST'] ?? 'smtp.hostinger.com'));
        $smtpPort = (int) ($_ENV['MAIL_PORT'] ?? 465);
        $smtpUser = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
        $smtpPass = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $fromEmail = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? $smtpUser));
        $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'CEDE - Site'));
        $toEmail = trim((string) ($_ENV['MAIL_TO_ADDRESS'] ?? $fromEmail));

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '' || $toEmail === '') {
            throw new \RuntimeException('Configuração de e-mail incompleta no .env.');
        }

        $normalizedName = $this->normalizeSingleLineValue($name, 'Visitante');
        $normalizedEmail = strtolower(trim($email));
        $normalizedSubject = $this->normalizeSingleLineValue($subject, 'Contato pelo formulário do site');
        $normalizedMessage = $this->normalizeMultilineValue($message);

        $safeName = htmlspecialchars($normalizedName, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($normalizedEmail, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($normalizedSubject, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($normalizedMessage, ENT_QUOTES, 'UTF-8'));
        $replyMailTo = htmlspecialchars(
            $this->buildReplyMailToLink($normalizedEmail, $normalizedSubject),
            ENT_QUOTES,
            'UTF-8'
        );

        $subjectLine = '[Contato Site] Novo contato recebido';
        $altBody = "Novo contato pelo site\n"
            . "Mensagem recebida pelo formulario institucional.\n\n"
            . "Nome: {$normalizedName}\n"
            . "E-mail: {$normalizedEmail}\n"
            . "Assunto informado: {$normalizedSubject}\n\n"
            . "Responder: {$this->buildReplyMailToLink($normalizedEmail, $normalizedSubject)}\n\n"
            . $normalizedMessage;

        $mailer = $this->buildContactMailer(
            $smtpHost,
            $smtpPort,
            $smtpUser,
            $smtpPass,
            $fromEmail,
            $fromName,
            $toEmail,
            $email,
            $name
        );

        $logoCid = 'cedern-logo';
        $logoPath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede4_logo.png';
        $logoSrc = null;
        if (is_file($logoPath)) {
            $mailer->addEmbeddedImage($logoPath, $logoCid, 'cede4_logo.png', 'base64', 'image/png');
            $logoSrc = 'cid:' . $logoCid;
        }

        $headerMetaHtml = InstitutionalEmailTemplate::buildInstitutionHeaderMeta();
        $htmlBody = InstitutionalEmailTemplate::buildLayout(
            'Novo contato pelo site',
            '<p style="margin:0 0 14px;">Mensagem recebida pelo formulario institucional do site do CEDE.</p>'
            . '<div style="margin:0 0 16px;padding:14px 16px;border:1px solid #dbe4ee;'
            . 'border-radius:12px;background:#f8fafc;">'
            . '<p style="margin:0 0 8px;"><strong>Nome:</strong> ' . $safeName . '</p>'
            . '<p style="margin:0 0 8px;"><strong>E-mail:</strong> '
            . '<a href="mailto:' . $safeEmail . '" style="color:#1d4ed8;text-decoration:none;">' . $safeEmail . '</a></p>'
            . '<p style="margin:0;"><strong>Assunto informado:</strong> ' . $safeSubject . '</p>'
            . '</div>'
            . '<div style="margin:0 0 16px;padding:16px;border-left:4px solid #2563eb;'
            . 'border-radius:10px;background:#f8fafc;">'
            . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;text-transform:uppercase;color:#64748b;">Mensagem</p>'
            . '<p style="margin:0;">' . $safeMessage . '</p>'
            . '</div>'
            . '<p style="margin:0 0 10px;">'
            . '<a href="' . $replyMailTo . '" '
            . 'style="display:inline-block;padding:11px 15px;border-radius:10px;'
            . 'background:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;">'
            . 'Responder por e-mail</a></p>'
            . '<p style="margin:0;font-size:12px;color:#64748b;">'
            . 'Se preferir, use o botao de resposta do seu webmail ou escreva para ' . $safeEmail . '.</p>',
            $logoSrc,
            $headerMetaHtml
        );

        $mailer->isHTML(true);
        $mailer->Subject = $subjectLine;
        $mailer->Body = $htmlBody;
        $mailer->AltBody = $altBody;

        try {
            $mailer->send();
        } catch (\Throwable $primaryException) {
            $errorInfo = $mailer->ErrorInfo;
            $isDataRejected = stripos($primaryException->getMessage(), 'data not accepted') !== false
                || stripos($errorInfo, 'data not accepted') !== false;

            if (!$isDataRejected) {
                throw new \RuntimeException(
                    'Falha SMTP primária: ' . $primaryException->getMessage()
                    . ' | ErrorInfo=' . $errorInfo,
                    0,
                    $primaryException
                );
            }

            // Fallback for strict SMTP filters: simplified plain-text-only message.
            $fallbackMailer = $this->buildContactMailer(
                $smtpHost,
                $smtpPort,
                $smtpUser,
                $smtpPass,
                $fromEmail,
                $fromName,
                $toEmail,
                $email,
                $name
            );
            $fallbackMailer->isHTML(false);
            $fallbackMailer->Subject = $subjectLine;
            $fallbackMailer->Body = $altBody;
            $fallbackMailer->AltBody = $altBody;
            try {
                $fallbackMailer->send();
            } catch (\Throwable $fallbackException) {
                throw new \RuntimeException(
                    'Falha SMTP no fallback: ' . $fallbackException->getMessage()
                    . ' | PrimaryErrorInfo=' . $errorInfo
                    . ' | FallbackErrorInfo=' . $fallbackMailer->ErrorInfo,
                    0,
                    $fallbackException
                );
            }
        }
    }

    private function buildContactMailer(
        string $smtpHost,
        int $smtpPort,
        string $smtpUser,
        string $smtpPass,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $replyToEmail,
        string $replyToName
    ): PHPMailer {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->SMTPAuth = true;
        $mailer->Username = $smtpUser;
        $mailer->Password = $smtpPass;
        $mailer->Port = $smtpPort;
        $mailer->SMTPSecure = $this->resolveSmtpEncryption($smtpPort);
        $mailer->CharSet = 'UTF-8';
        $mailer->Sender = $fromEmail;
        $mailer->Timeout = max(3, (int) ($_ENV['MAIL_TIMEOUT'] ?? 15));

        $smtpDebugEnabled = filter_var(
            trim((string) ($_ENV['MAIL_SMTP_DEBUG'] ?? 'false')),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($smtpDebugEnabled) {
            $mailer->SMTPDebug = 2;
            $mailer->Debugoutput = static function (string $message, int $level): void {
                error_log('[cedern smtp][L' . $level . '] ' . $message);
            };
        }

        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($toEmail);
        $allowExternalReplyTo = filter_var(
            trim((string) ($_ENV['MAIL_ALLOW_EXTERNAL_REPLYTO'] ?? 'false')),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($allowExternalReplyTo) {
            $mailer->addReplyTo($replyToEmail, $replyToName);
        } else {
            $mailer->addReplyTo($fromEmail, $fromName);
        }
        $mailer->addCustomHeader('X-Auto-Response-Suppress', 'All');

        $messageIdDomain = strtolower(trim((string) strrchr($fromEmail, '@')));
        $messageIdDomain = ltrim($messageIdDomain, '@');
        if ($messageIdDomain !== '') {
            $mailer->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(12)), $messageIdDomain);
        }

        return $mailer;
    }

    private function resolveSmtpEncryption(int $smtpPort): string
    {
        $explicitEncryption = strtolower(trim((string) ($_ENV['MAIL_ENCRYPTION'] ?? '')));
        if ($explicitEncryption === 'ssl' || $explicitEncryption === 'smtps') {
            return PHPMailer::ENCRYPTION_SMTPS;
        }

        if ($explicitEncryption === 'tls' || $explicitEncryption === 'starttls') {
            return PHPMailer::ENCRYPTION_STARTTLS;
        }

        return $smtpPort === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
    }

    private function buildReplyMailToLink(string $email, string $subject): string
    {
        $replySubject = $this->normalizeSingleLineValue('Re: ' . $subject, 'Re: Contato pelo formulario do site');

        return 'mailto:' . $email . '?' . http_build_query([
            'subject' => $replySubject,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function normalizeSingleLineValue(string $value, string $fallback = ''): string
    {
        $normalized = preg_replace('/[\r\n\t]+/', ' ', $value) ?? '';
        $normalized = preg_replace('/\s{2,}/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return $fallback;
        }

        return mb_substr($normalized, 0, 160);
    }

    private function normalizeMultilineValue(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : 'Mensagem nao informada.';
    }

    /**
     * @return array{name:string,email:string,subject:string,message:string,company:string}
     */
    private function getEmptyForm(): array
    {
        return [
            'name' => '',
            'email' => '',
            'subject' => '',
            'message' => '',
            'company' => '',
        ];
    }

    /**
     * @param array<string, mixed> $flash
     */
    private function storeContactFlash(array $flash): void
    {
        $_SESSION['contact_form_flash'] = $flash;
    }

    /**
     * @return array<string, mixed>
     */
    private function consumeContactFlash(): array
    {
        $flash = (array) ($_SESSION['contact_form_flash'] ?? []);
        unset($_SESSION['contact_form_flash']);

        return $flash;
    }
}
