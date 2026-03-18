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

class MemberRegisterPageAction extends AbstractPageAction
{
    private const FLASH_KEY = 'member_register';

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
            'full_name' => '',
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
            $form['full_name'] = trim((string) ($flashForm['full_name'] ?? ''));
            $form['email'] = strtolower(trim((string) ($flashForm['email'] ?? '')));
        }

        if ($method === 'POST') {
            $body = (array) ($request->getParsedBody() ?? []);
            $fullName = trim((string) ($body['full_name'] ?? ''));
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $password = (string) ($body['password'] ?? '');
            $passwordConfirmation = (string) ($body['password_confirmation'] ?? '');

            $form['full_name'] = $fullName;
            $form['email'] = $email;

            if ($fullName === '') {
                $errors[] = 'Informe seu nome completo.';
            }

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Informe um e-mail válido.';
            }

            if (strlen($password) < 8) {
                $errors[] = 'A senha deve ter ao menos 8 caracteres.';
            }

            if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $errors[] = 'A senha deve conter ao menos 1 letra maiúscula e 1 número.';
            }

            if ($password !== $passwordConfirmation) {
                $errors[] = 'A confirmação de senha não confere.';
            }

            if (empty($errors)) {
                try {
                    $this->memberAuthRepository->createPendingUser([
                        'full_name' => $fullName,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);

                    try {
                        $this->sendRegistrationEmails($fullName, $email);
                    } catch (Throwable $mailException) {
                        $this->logger->warning('Cadastro criado, mas falhou o envio de e-mail de confirmação.', [
                            'email' => $email,
                            'error' => $mailException->getMessage(),
                        ]);
                    }

                    $this->storeSessionFlash(self::FLASH_KEY, [
                        'status' => 'sent',
                        'errors' => [],
                        'form' => [
                            'full_name' => '',
                            'email' => '',
                        ],
                    ]);

                    return $response->withHeader('Location', '/cadastro')->withStatus(303);
                } catch (Throwable $exception) {
                    $errorMessage = strtolower($exception->getMessage());
                    $isDuplicate = str_contains($errorMessage, 'duplicate')
                        || str_contains($errorMessage, '1062')
                        || str_contains($errorMessage, 'unique')
                        || str_contains($errorMessage, 'email');

                    $this->logger->error('Falha ao criar cadastro pendente de membro.', [
                        'email' => $email,
                        'exception' => $exception,
                    ]);

                    $errors[] = $isDuplicate
                        ? 'Este e-mail já possui cadastro.'
                        : 'Cadastro indisponível no momento. Tente novamente em instantes.';
                }
            }

            $this->storeSessionFlash(self::FLASH_KEY, [
                'status' => $success ? 'sent' : 'error',
                'errors' => $errors,
                'form' => [
                    'full_name' => $form['full_name'],
                    'email' => $form['email'],
                ],
            ]);

            return $response->withHeader('Location', '/cadastro')->withStatus(303);
        }

        return $this->renderPage($response, 'pages/member-register.twig', [
            'member_register_errors' => $errors,
            'member_register_success' => $success,
            'member_register_form' => $form,
            'page_title' => 'Cadastro de Membro | CEDE',
            'page_url' => 'https://cedern.org/cadastro',
            'page_description' => 'Cadastro de frequentador para área de membros do CEDE.',
        ]);
    }

    /**
     * @throws Exception
     */
    private function sendRegistrationEmails(string $fullName, string $email): void
    {
        $smtpHost = trim((string) ($_ENV['MAIL_HOST'] ?? 'smtp.hostinger.com'));
        $smtpPort = (int) ($_ENV['MAIL_PORT'] ?? 465);
        $smtpUser = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
        $smtpPass = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $fromEmail = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? $smtpUser));
        $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'CEDE - Contato'));
        $notifyEmail = trim((string) ($_ENV['MAIL_TO_ADDRESS'] ?? $fromEmail));
        $siteUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org'), '/');

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
            throw new \RuntimeException('Configuração SMTP incompleta para envio de e-mails de cadastro.');
        }

        $headerMetaHtml = InstitutionalEmailTemplate::buildInstitutionHeaderMeta();
        $panelReviewUrl = $siteUrl . '/painel/usuarios?sort=created_at&dir=desc&q=pending';
        $memberLoginUrl = $siteUrl . '/entrar';
        $contactUrl = $siteUrl . '/contato';
        $adminReplyUrl = $this->buildMailToLink(
            strtolower(trim($email)),
            'Sobre sua solicitacao de cadastro no CEDE'
        );
        $safeFullName = htmlspecialchars(trim($fullName), ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars(strtolower(trim($email)), ENT_QUOTES, 'UTF-8');
        $adminActionsHtml = InstitutionalEmailTemplate::buildActionGroup([
            [
                'href' => $panelReviewUrl,
                'label' => 'Abrir painel de usuarios',
                'is_primary' => true,
            ],
            [
                'href' => $adminReplyUrl,
                'label' => 'Responder solicitante',
                'is_primary' => false,
            ],
        ]);
        $memberActionsHtml = InstitutionalEmailTemplate::buildActionGroup([
            [
                'href' => $memberLoginUrl,
                'label' => 'Abrir area do membro',
                'is_primary' => true,
            ],
            [
                'href' => $contactUrl,
                'label' => 'Falar com o CEDE',
                'is_primary' => false,
            ],
        ]);

        if ($notifyEmail !== '') {
            $adminSubject = '[Cadastro de Membro] Nova solicitação recebida';

            $adminLogoSrc = $this->resolveEmbeddedLogoSrc();
            $adminBody = InstitutionalEmailTemplate::buildLayout(
                'Nova solicitação de cadastro',
                '<p style="margin:0 0 14px;">Uma nova solicitacao de cadastro foi enviada pela area publica do site.</p>'
                . '<div style="margin:0 0 16px;padding:14px 16px;border:1px solid #dbe4ee;'
                . 'border-radius:12px;background:#f8fafc;">'
                . '<p style="margin:0 0 8px;"><strong>Nome:</strong> ' . $safeFullName . '</p>'
                . '<p style="margin:0;"><strong>E-mail:</strong> '
                . '<a href="mailto:' . $safeEmail . '" style="color:#1d4ed8;text-decoration:none;">'
                . $safeEmail . '</a></p>'
                . '</div>'
                . '<div style="margin:0 0 16px;padding:16px;border-left:4px solid #2563eb;'
                . 'border-radius:10px;background:#f8fafc;">'
                . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;'
                . 'text-transform:uppercase;color:#64748b;">Proxima acao</p>'
                . '<p style="margin:0;">Acesse o painel administrativo para revisar o cadastro, definir perfil, '
                . 'tipo de socio e funcao institucional, quando necessario.</p>'
                . '</div>'
                . $adminActionsHtml
                . '<div style="margin:0;padding:14px 16px;border:1px dashed #cbd5e1;'
                . 'border-radius:12px;background:#ffffff;">'
                . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;'
                . 'text-transform:uppercase;color:#64748b;">Observacoes</p>'
                . '<p style="margin:0;font-size:13px;color:#475569;">'
                . 'O cadastro ja foi salvo como pendente. Depois da aprovacao, a pessoa podera entrar '
                . 'com este mesmo e-mail e a senha cadastrada no formulario publico.</p>'
                . '</div>',
                $adminLogoSrc,
                $headerMetaHtml
            );

            $this->sendMail(
                $smtpHost,
                $smtpPort,
                $smtpUser,
                $smtpPass,
                $fromEmail,
                $fromName,
                $notifyEmail,
                'Administração CEDE',
                $email,
                $fullName,
                $adminSubject,
                $adminBody
            );
        }

        $memberSubject = 'Seja bem-vindo(a)! Recebemos seu cadastro no CEDE';
        $memberLogoSrc = $this->resolveEmbeddedLogoSrc();
        $memberBody = InstitutionalEmailTemplate::buildLayout(
            'Boas-vindas ao CEDE',
            '<p style="margin:0 0 14px;">Olá, <strong>' . $safeFullName . '</strong>.</p>'
            . '<p style="margin:0 0 14px;">Recebemos sua solicitacao de cadastro na area de membros do CEDE. '
            . 'Seu pedido foi registrado com sucesso e agora aguarda validacao da equipe.</p>'
            . '<div style="margin:0 0 16px;padding:14px 16px;border:1px solid #dbe4ee;'
            . 'border-radius:12px;background:#f8fafc;">'
            . '<p style="margin:0 0 8px;"><strong>Nome informado:</strong> ' . $safeFullName . '</p>'
            . '<p style="margin:0 0 8px;"><strong>E-mail de acesso:</strong> ' . $safeEmail . '</p>'
            . '<p style="margin:0;"><strong>Status atual:</strong> Cadastro em analise</p>'
            . '</div>'
            . '<div style="margin:0 0 16px;padding:16px;border-left:4px solid #2563eb;'
            . 'border-radius:10px;background:#f8fafc;">'
            . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;'
            . 'text-transform:uppercase;color:#64748b;">O que acontece agora</p>'
            . '<p style="margin:0;">Assim que a equipe concluir a validacao, seu acesso sera liberado e voce '
            . 'podera entrar normalmente com este mesmo e-mail e a senha cadastrada.</p>'
            . '</div>'
            . $memberActionsHtml
            . '<div style="margin:0;padding:14px 16px;border:1px dashed #cbd5e1;'
            . 'border-radius:12px;background:#ffffff;">'
            . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;'
            . 'text-transform:uppercase;color:#64748b;">Observacoes</p>'
            . '<p style="margin:0;font-size:13px;color:#475569;">'
            . 'Guarde este e-mail para consulta. Enquanto a solicitacao estiver em analise, o acesso '
            . 'permanecera pendente. Se precisar de ajuda ou quiser complementar alguma informacao, use '
            . 'o canal oficial de contato do CEDE.</p>'
            . '</div>',
            $memberLogoSrc,
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
            $fullName,
            $fromEmail,
            $fromName,
            $memberSubject,
            $memberBody
        );
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

    private function buildMailToLink(string $email, string $subject): string
    {
        return 'mailto:' . $email . '?' . http_build_query([
            'subject' => trim($subject),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
