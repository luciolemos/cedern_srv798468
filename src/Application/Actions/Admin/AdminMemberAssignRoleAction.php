<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Application\Support\InstitutionalEmailTemplate;
use App\Domain\Member\MemberAuthRepository;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class AdminMemberAssignRoleAction extends AbstractPageAction
{
    private const EXCLUSIVE_INSTITUTIONAL_ROLES = [
        'Presidente CEDE',
        'Vice-presidente CEDE',
        'Secretário',
        'Diretor de Finanças',
        'Diretor de Eventos',
        'Diretor de Patrimônio',
        'Diretor de Estudos',
        'Diretor de Atendimento Fraterno',
        'Diretor de Comunicação',
    ];

    private const INSTITUTIONAL_ROLE_OPTIONS = [
        'Presidente CEDE',
        'Vice-presidente CEDE',
        'Secretário',
        'Diretor de Finanças',
        'Diretor de Eventos',
        'Diretor de Patrimônio',
        'Diretor de Estudos',
        'Diretor de Atendimento Fraterno',
        'Diretor de Comunicação',
        'Coordenador',
        'Conselheiro',
    ];

    private const MEMBER_TYPE_OPTIONS = [
        'fundador',
        'efetivo',
    ];

    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);
        $body = (array) ($request->getParsedBody() ?? []);
        $redirectTarget = $this->resolveRedirectTarget((string) ($body['redirect_to'] ?? ''));
        $roleId = (int) ($body['role_id'] ?? 0);
        $institutionalRoleInput = trim((string) ($body['institutional_role'] ?? ''));
        $hasInstitutionalRoleInput = $institutionalRoleInput !== '';
        $institutionalRole = in_array($institutionalRoleInput, self::INSTITUTIONAL_ROLE_OPTIONS, true)
            ? $institutionalRoleInput
            : null;
        $memberTypeInput = strtolower(trim((string) ($body['member_type'] ?? '')));
        $hasMemberTypeInput = $memberTypeInput !== '';
        $memberType = in_array($memberTypeInput, self::MEMBER_TYPE_OPTIONS, true)
            ? $memberTypeInput
            : null;

        if ($id <= 0) {
            return $this->redirectWithStatus($response, $redirectTarget, 'invalid-role');
        }

        if ($hasInstitutionalRoleInput && $institutionalRole === null) {
            return $this->redirectWithStatus($response, $redirectTarget, 'invalid-institutional-role');
        }

        if ($hasMemberTypeInput && $memberType === null) {
            return $this->redirectWithStatus($response, $redirectTarget, 'invalid-member-type');
        }

        try {
            $currentUser = $this->memberAuthRepository->findById($id);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao carregar usuário para atribuição de papel.', [
                'user_id' => $id,
                'exception' => $exception,
            ]);

            return $this->redirectWithStatus($response, $redirectTarget, 'assign-error');
        }

        if ($currentUser === null) {
            return $this->redirectWithStatus($response, $redirectTarget, 'assign-error');
        }

        $shouldSendApprovalEmail = strtolower(trim((string) ($currentUser['status'] ?? ''))) === 'pending';

        if ($roleId <= 0) {
            $roleId = (int) ($currentUser['role_id'] ?? 0);
        }

        if ($roleId <= 0) {
            return $this->redirectWithStatus($response, $redirectTarget, 'invalid-role');
        }

        if ($institutionalRole !== null && in_array($institutionalRole, self::EXCLUSIVE_INSTITUTIONAL_ROLES, true)) {
            try {
                $isOccupied = $this->memberAuthRepository->hasActiveInstitutionalRole($institutionalRole, $id);
            } catch (Throwable $exception) {
                $this->logger->error('Falha ao validar ocupação de função institucional exclusiva.', [
                    'user_id' => $id,
                    'institutional_role' => $institutionalRole,
                    'exception' => $exception,
                ]);

                return $this->redirectWithStatus($response, $redirectTarget, 'assign-error');
            }

            if ($isOccupied) {
                return $this->redirectWithStatus($response, $redirectTarget, 'institutional-role-conflict', [
                    'institutional_role' => $institutionalRole,
                ]);
            }
        }

        try {
            $this->memberAuthRepository->approveAndAssignRole($id, $roleId, $institutionalRole, $memberType);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao aprovar/atribuir papel de usuário.', [
                'user_id' => $id,
                'role_id' => $roleId,
                'institutional_role' => $institutionalRole,
                'member_type' => $memberType,
                'exception' => $exception,
            ]);

            return $this->redirectWithStatus($response, $redirectTarget, 'assign-error');
        }

        if ($shouldSendApprovalEmail) {
            try {
                $this->sendApprovalEmail(
                    (string) ($currentUser['full_name'] ?? ''),
                    (string) ($currentUser['email'] ?? ''),
                    $this->resolveRoleName($roleId, $currentUser),
                    $institutionalRole ?: $this->nullableText($currentUser['institutional_role'] ?? null),
                    $memberType ?: $this->nullableText($currentUser['member_type'] ?? null)
                );
            } catch (Throwable $exception) {
                $this->logger->warning('Usuário aprovado, mas falhou o envio do e-mail de liberação de acesso.', [
                    'user_id' => $id,
                    'email' => (string) ($currentUser['email'] ?? ''),
                    'exception' => $exception,
                ]);
            }
        }

        return $this->redirectWithStatus($response, $redirectTarget, 'approved');
    }

    /**
     * @throws Exception
     */
    private function sendApprovalEmail(
        string $fullName,
        string $email,
        ?string $roleName,
        ?string $institutionalRole,
        ?string $memberType
    ): void {
        $smtpHost = trim((string) ($_ENV['MAIL_HOST'] ?? 'smtp.hostinger.com'));
        $smtpPort = (int) ($_ENV['MAIL_PORT'] ?? 465);
        $smtpUser = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
        $smtpPass = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $fromEmail = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? $smtpUser));
        $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'CEDE - Contato'));
        $siteUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org'), '/');

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
            throw new \RuntimeException('Configuração SMTP incompleta para envio do e-mail de aprovação.');
        }

        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('E-mail inválido para envio da confirmação de aprovação.');
        }

        $resolvedFullName = trim($fullName) !== '' ? trim($fullName) : 'Membro CEDE';
        $resolvedRoleName = trim((string) $roleName);
        if ($resolvedRoleName === '') {
            $resolvedRoleName = 'Membro';
        }

        $headerMetaHtml = InstitutionalEmailTemplate::buildInstitutionHeaderMeta();
        $memberLoginUrl = $siteUrl . '/entrar';
        $contactUrl = $siteUrl . '/contato';
        $safeFullName = htmlspecialchars($resolvedFullName, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($normalizedEmail, ENT_QUOTES, 'UTF-8');
        $safeRoleName = htmlspecialchars($resolvedRoleName, ENT_QUOTES, 'UTF-8');
        $memberTypeLabel = $this->resolveMemberTypeLabel($memberType);
        $normalizedInstitutionalRole = $this->nullableText($institutionalRole);
        $detailLines = [
            '<p style="margin:0 0 8px;"><strong>Nome:</strong> ' . $safeFullName . '</p>',
            '<p style="margin:0 0 8px;"><strong>E-mail de acesso:</strong> ' . $safeEmail . '</p>',
            '<p style="margin:0 0 8px;"><strong>Perfil liberado:</strong> ' . $safeRoleName . '</p>',
        ];

        if ($memberTypeLabel !== null) {
            $detailLines[] = '<p style="margin:0 0 8px;"><strong>Tipo de sócio:</strong> '
                . htmlspecialchars($memberTypeLabel, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $detailLines[] = '<p style="margin:0;"><strong>Função CEDE:</strong> '
            . htmlspecialchars($normalizedInstitutionalRole ?? 'Não definida', ENT_QUOTES, 'UTF-8') . '</p>';

        $body = InstitutionalEmailTemplate::buildLayout(
            'Seu acesso ao CEDE foi liberado',
            '<p style="margin:0 0 14px;">Olá, <strong>' . $safeFullName . '</strong>.</p>'
            . '<p style="margin:0 0 14px;">Sua solicitação de cadastro foi validada e seu acesso à área de membros do CEDE já está liberado.</p>'
            . '<div style="margin:0 0 16px;padding:14px 16px;border:1px solid #dbe4ee;'
            . 'border-radius:12px;background:#f8fafc;">'
            . implode('', $detailLines)
            . '</div>'
            . '<div style="margin:0 0 16px;padding:16px;border-left:4px solid #2563eb;'
            . 'border-radius:10px;background:#f8fafc;">'
            . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;'
            . 'text-transform:uppercase;color:#64748b;">Próximos passos</p>'
            . '<p style="margin:0;">Entre usando o mesmo e-mail e a senha cadastrados no formulário. Depois disso, você já poderá acessar sua área do membro normalmente.</p>'
            . '</div>'
            . InstitutionalEmailTemplate::buildActionGroup([
                [
                    'href' => $memberLoginUrl,
                    'label' => 'Abrir área do membro',
                    'is_primary' => true,
                ],
                [
                    'href' => $contactUrl,
                    'label' => 'Falar com o CEDE',
                    'is_primary' => false,
                ],
            ])
            . '<div style="margin:0;padding:14px 16px;border:1px dashed #cbd5e1;'
            . 'border-radius:12px;background:#ffffff;">'
            . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;'
            . 'text-transform:uppercase;color:#64748b;">Observações</p>'
            . '<p style="margin:0;font-size:13px;color:#475569;">'
            . 'Se precisar de ajuda para entrar, atualizar seus dados ou esclarecer alguma dúvida, use o canal oficial de contato do CEDE.</p>'
            . '</div>',
            $this->resolveEmbeddedLogoSrc(),
            $headerMetaHtml
        );

        $this->sendMail(
            $smtpHost,
            $smtpPort,
            $smtpUser,
            $smtpPass,
            $fromEmail,
            $fromName,
            $normalizedEmail,
            $resolvedFullName,
            $fromEmail,
            $fromName,
            'Seu acesso ao CEDE foi liberado',
            $body,
            "Seu acesso ao CEDE foi liberado\n"
            . "Nome: {$resolvedFullName}\n"
            . "E-mail de acesso: {$normalizedEmail}\n"
            . "Perfil liberado: {$resolvedRoleName}\n"
            . ($memberTypeLabel !== null ? "Tipo de sócio: {$memberTypeLabel}\n" : '')
            . "Função CEDE: " . ($normalizedInstitutionalRole ?? 'Não definida') . "\n\n"
            . "Entre usando o mesmo e-mail e a senha cadastrados.\n"
            . "Área do membro: {$memberLoginUrl}\n"
            . "Contato: {$contactUrl}"
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
        string $htmlBody,
        string $altBody
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
        $mailer->AltBody = $altBody;

        $mailer->send();
    }

    /**
     * @param array<string, mixed>|null $currentUser
     */
    private function resolveRoleName(int $roleId, ?array $currentUser = null): ?string
    {
        try {
            foreach ($this->memberAuthRepository->findAllRoles() as $role) {
                if ((int) ($role['id'] ?? 0) !== $roleId) {
                    continue;
                }

                $resolved = trim((string) ($role['name'] ?? ''));
                if ($resolved !== '') {
                    return $resolved;
                }
            }
        } catch (Throwable) {
        }

        $fallback = trim((string) ($currentUser['role_name'] ?? ''));

        return $fallback !== '' ? $fallback : null;
    }

    private function resolveMemberTypeLabel(?string $memberType): ?string
    {
        return match (strtolower(trim((string) $memberType))) {
            'fundador' => 'Fundador',
            'efetivo' => 'Efetivo',
            default => null,
        };
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function resolveEmbeddedLogoSrc(): ?string
    {
        $logoPath = dirname(__DIR__, 4) . '/public/assets/img/brands/cede4_logo.png';

        return is_file($logoPath) ? 'cid:cedern-logo' : null;
    }

    private function resolveRedirectTarget(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '/painel/usuarios';
        }

        if (!str_starts_with($normalized, '/painel/usuarios')) {
            return '/painel/usuarios';
        }

        return $normalized;
    }

    /**
     * @param array<string, scalar|null> $extraQuery
     */
    private function redirectWithStatus(
        Response $response,
        string $basePath,
        string $status,
        array $extraQuery = []
    ): Response {
        $flash = ['status' => $status];
        foreach ($extraQuery as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '') {
                continue;
            }

            $flash[$key] = $normalized;
        }

        $this->storeSessionFlash($this->resolveFlashKey($basePath), $flash);

        return $response->withHeader('Location', $basePath)->withStatus(303);
    }

    private function resolveFlashKey(string $redirectTarget): string
    {
        $redirectPath = (string) (parse_url($redirectTarget, PHP_URL_PATH) ?? $redirectTarget);

        if (preg_match('#^/painel/usuarios/(\d+)/resumo$#', $redirectPath, $matches) === 1) {
            return 'admin_member_user_summary_' . (int) $matches[1];
        }

        return AdminMemberUsersPageAction::FLASH_KEY;
    }
}
