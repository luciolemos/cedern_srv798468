<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Application\Support\InstitutionalEmailTemplate;
use App\Domain\Member\MemberAuthRepository;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class MemberForgotPasswordPageAction extends AbstractPageAction
{
    private const FLASH_KEY = 'member_password_forgot';
    private const TOKEN_TTL_MINUTES = 60;

    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $method = strtoupper($request->getMethod());
        $errors = [];
        $success = false;
        $form = [
            'email' => '',
        ];

        if ($method !== 'POST') {
            $flash = $this->consumeSessionFlash(self::FLASH_KEY);
            $success = (string) ($flash['status'] ?? '') === 'sent';
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));
            $flashForm = (array) ($flash['form'] ?? []);
            $form['email'] = strtolower(trim((string) ($flashForm['email'] ?? '')));
        }

        if ($method === 'POST') {
            $body = (array) ($request->getParsedBody() ?? []);
            $email = strtolower(trim((string) ($body['email'] ?? '')));

            $form['email'] = $email;

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Informe um e-mail válido.';
            }

            if (empty($errors)) {
                try {
                    $user = $this->memberAuthRepository->findByEmail($email);

                    if ($user !== null) {
                        $token = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $token);
                        $expiresAt = new \DateTimeImmutable('+' . self::TOKEN_TTL_MINUTES . ' minutes');

                        if (
                            $this->memberAuthRepository->createPasswordResetToken(
                                (int) ($user['id'] ?? 0),
                                $email,
                                $tokenHash,
                                $expiresAt
                            )
                        ) {
                            $this->sendPasswordResetEmail($user, $token, $expiresAt);
                        } else {
                            $this->logger->warning('Falha ao registrar token de redefinicao de senha.', [
                                'email' => $email,
                            ]);
                        }
                    }
                } catch (Throwable $exception) {
                    $this->logger->warning('Falha ao processar solicitacao de redefinicao de senha.', [
                        'email' => $email,
                        'error' => $exception->getMessage(),
                    ]);
                }

                $this->storeSessionFlash(self::FLASH_KEY, [
                    'status' => 'sent',
                    'errors' => [],
                    'form' => [
                        'email' => '',
                    ],
                ]);

                return $response->withHeader('Location', '/esqueci-senha')->withStatus(303);
            }

            $this->storeSessionFlash(self::FLASH_KEY, [
                'status' => 'error',
                'errors' => $errors,
                'form' => [
                    'email' => $form['email'],
                ],
            ]);

            return $response->withHeader('Location', '/esqueci-senha')->withStatus(303);
        }

        return $this->renderPage($response, 'pages/member-password-forgot.twig', [
            'member_password_forgot_errors' => $errors,
            'member_password_forgot_success' => $success,
            'member_password_forgot_form' => $form,
            'member_password_forgot_ttl_minutes' => self::TOKEN_TTL_MINUTES,
            'page_title' => 'Recuperar senha | CEDE',
            'page_url' => 'https://cedern.org/esqueci-senha',
            'page_description' => 'Solicite um link seguro para redefinir sua senha na área do membro do CEDE.',
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @throws Exception
     */
    private function sendPasswordResetEmail(array $user, string $token, \DateTimeImmutable $expiresAt): void
    {
        $smtpHost = trim((string) ($_ENV['MAIL_HOST'] ?? 'smtp.hostinger.com'));
        $smtpPort = (int) ($_ENV['MAIL_PORT'] ?? 465);
        $smtpUser = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
        $smtpPass = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $fromEmail = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? $smtpUser));
        $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'CEDE - Site'));

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
            throw new \RuntimeException('Configuração SMTP incompleta para redefinição de senha.');
        }

        $fullName = trim((string) ($user['full_name'] ?? ''));
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $resetUrl = $this->buildResetUrl($token);
        $formattedExpiry = $expiresAt
            ->setTimezone(new \DateTimeZone('America/Fortaleza'))
            ->format('d/m/Y H:i');
        $headerMetaHtml = InstitutionalEmailTemplate::buildInstitutionHeaderMeta();
        $safeFullName = htmlspecialchars($fullName !== '' ? $fullName : 'membro', ENT_QUOTES, 'UTF-8');
        $safeExpiry = htmlspecialchars($formattedExpiry, ENT_QUOTES, 'UTF-8');
        $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $actionsHtml = InstitutionalEmailTemplate::buildActionGroup([
            [
                'href' => $resetUrl,
                'label' => 'Redefinir senha agora',
                'is_primary' => true,
            ],
        ]);

        $logoSrc = $this->resolveEmbeddedLogoSrc();
        $body = InstitutionalEmailTemplate::buildLayout(
            'Redefinição de senha',
            '<p style="margin:0 0 14px;">Olá, <strong>' . $safeFullName . '</strong>.</p>'
            . '<p style="margin:0 0 14px;">Recebemos uma solicitação para redefinir a senha da sua área de membro do CEDE.</p>'
            . '<div style="margin:0 0 16px;padding:16px;border-left:4px solid #2563eb;'
            . 'border-radius:10px;background:#f8fafc;">'
            . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;'
            . 'text-transform:uppercase;color:#64748b;">Validade do link</p>'
            . '<p style="margin:0;">Este link é válido até <strong>' . $safeExpiry . '</strong> (horário de Fortaleza).</p>'
            . '</div>'
            . $actionsHtml
            . '<p style="margin:0 0 10px;">Se o botão não abrir, copie e cole este endereço no navegador:</p>'
            . '<p style="margin:0 0 14px;word-break:break-word;">'
            . '<a href="' . $safeResetUrl . '" style="color:#1d4ed8;text-decoration:none;">' . $safeResetUrl . '</a></p>'
            . '<p style="margin:0;font-size:13px;color:#475569;">'
            . 'Se você não solicitou esta alteração, ignore este e-mail. Sua senha atual continuará válida até que uma nova seja definida.</p>',
            $logoSrc,
            $headerMetaHtml
        );

        $this->sendMail(
            $smtpHost,
            $smtpPort,
            $smtpUser,
            $smtpPass,
            $fromEmail,
            $fromName,
            $email,
            $fullName !== '' ? $fullName : 'Membro',
            $fromEmail,
            $fromName,
            'Recuperação de senha | CEDE',
            $body
        );
    }

    private function buildResetUrl(string $token): string
    {
        $siteUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'), '/');

        return $siteUrl . '/redefinir-senha?token=' . rawurlencode($token);
    }

    /**
     * @throws Exception
     */
    private function sendMail(
        string $smtpHost,
        int $smtpPort,
        string $smtpUser,
        string $smtpPass,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $replyToEmail,
        string $replyToName,
        string $subject,
        string $htmlBody
    ): void {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->SMTPAuth = true;
        $mailer->Username = $smtpUser;
        $mailer->Password = $smtpPass;
        $mailer->Port = $smtpPort;
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mailer->CharSet = 'UTF-8';
        $mailer->Sender = $fromEmail;

        $hostFromUrl = (string) parse_url(
            (string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'),
            PHP_URL_HOST
        );
        if ($hostFromUrl !== '') {
            $mailer->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(12)), $hostFromUrl);
        }

        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($toEmail, $toName);
        $mailer->addReplyTo($replyToEmail, $replyToName);

        $logoPath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede4_logo.png';
        if (is_file($logoPath)) {
            $mailer->addEmbeddedImage($logoPath, 'cedern-logo', 'cede4_logo.png', 'base64', 'image/png');
        }

        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $htmlBody;
        $mailer->AltBody = strip_tags(str_replace(
            ['<br>', '<br/>', '<br />', '</p>'],
            ["\n", "\n", "\n", "\n"],
            $htmlBody
        ));

        $mailer->send();
    }

    private function resolveEmbeddedLogoSrc(): ?string
    {
        $logoPath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede4_logo.png';

        return is_file($logoPath) ? 'cid:cedern-logo' : null;
    }
}
