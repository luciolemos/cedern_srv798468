<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Application\Support\InstitutionalEmailTemplate;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ContactPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $method = strtoupper($request->getMethod());

        $form = [
            'name' => '',
            'email' => '',
            'subject' => '',
            'message' => '',
            'company' => '',
        ];
        $errors = [];
        $status = '';

        if ($method === 'POST') {
            $body = (array) $request->getParsedBody();

            $form['name'] = trim((string) ($body['name'] ?? ''));
            $form['email'] = strtolower(trim((string) ($body['email'] ?? '')));
            $form['subject'] = trim((string) ($body['subject'] ?? ''));
            $form['message'] = trim((string) ($body['message'] ?? ''));
            $form['company'] = trim((string) ($body['company'] ?? ''));

            if ($form['company'] !== '') {
                $status = 'sent';
            } else {
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
                        $status = 'sent';
                        $form = [
                            'name' => '',
                            'email' => '',
                            'subject' => '',
                            'message' => '',
                            'company' => '',
                        ];
                    } catch (\Throwable $exception) {
                        $this->logger->error('Falha no envio de e-mail de contato.', [
                            'error' => $exception->getMessage(),
                        ]);
                        $status = 'error';
                        $errors[] = 'Não foi possível enviar sua mensagem agora. Tente novamente em instantes.';
                    }
                }
            }
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
        $mailer->addAddress($toEmail);
        $mailer->addReplyTo($email, $name);

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        $logoCid = 'cedern-logo';
        $logoPath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede4_logo.png';
        $logoSrc = null;
        if (is_file($logoPath)) {
            $mailer->addEmbeddedImage($logoPath, $logoCid, 'cede4_logo.png', 'base64', 'image/png');
            $logoSrc = 'cid:' . $logoCid;
        }

        $htmlBody = InstitutionalEmailTemplate::buildLayout(
            'Novo contato pelo site',
            '<p><strong>Nome:</strong> ' . $safeName . '</p>'
            . '<p><strong>E-mail:</strong> ' . $safeEmail . '</p>'
            . '<p><strong>Assunto:</strong> ' . $safeSubject . '</p>'
            . '<hr style="border:none;border-top:1px solid #e2e8f0;'
            . 'margin:14px 0;">'
            . '<p>' . $safeMessage . '</p>',
            $logoSrc
        );

        $mailer->isHTML(true);
        $mailer->Subject = '[Contato Site] ' . $subject;
            $mailer->Body = $htmlBody;
        $mailer->AltBody = "Novo contato pelo site\n"
            . "Nome: {$name}\n"
            . "E-mail: {$email}\n"
            . "Assunto: {$subject}\n\n"
            . $message;

        $mailer->send();
    }
}
