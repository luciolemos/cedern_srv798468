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
    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $errors = [];
        $success = false;
        $form = [
            'full_name' => '',
            'email' => '',
        ];

        if (strtoupper($request->getMethod()) === 'POST') {
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

                    $success = true;
                    $form = [
                        'full_name' => '',
                        'email' => '',
                    ];
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

        if ($notifyEmail !== '') {
            $adminSubject = '[Cadastro de Membro] Nova solicitação recebida';

            $adminLogoSrc = $this->resolveEmbeddedLogoSrc();
            $adminBody = InstitutionalEmailTemplate::buildLayout(
                'Nova solicitação de cadastro',
                '<p>Um novo cadastro de membro foi realizado no site.</p>'
                . '<p><strong>Nome:</strong> ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>E-mail:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p>Acesse o painel para revisar e aprovar quando necessário.</p>',
                $adminLogoSrc
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
            '<p>Olá, <strong>' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Ficamos felizes em receber sua solicitação de cadastro na área de membros do CEDE.</p>'
            . '<p>Seu pedido entrou em análise e, após validação da equipe, '
            . 'seu acesso será liberado com este e-mail.</p>'
            . '<p>Enquanto isso, você já pode acompanhar nossas atividades e agenda pública no site.</p>'
            . '<p><a href="' . htmlspecialchars($siteUrl . '/entrar', ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;padding:10px 14px;border-radius:8px;'
            . 'background:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;">'
            . 'Abrir área do membro</a></p>'
            . '<p style="margin-top:10px;color:#334155;">Com fraternidade,<br>Equipe CEDE</p>',
            $memberLogoSrc
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
}
