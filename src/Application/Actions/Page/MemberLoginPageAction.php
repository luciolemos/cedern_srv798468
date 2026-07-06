<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Application\Security\RecaptchaVerifier;
use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class MemberLoginPageAction extends AbstractPageAction
{
    public const FLASH_KEY = 'member_login';
    private const RECAPTCHA_ACTION = 'member_login';

    private MemberAuthRepository $memberAuthRepository;
    private RecaptchaVerifier $recaptchaVerifier;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        MemberAuthRepository $memberAuthRepository,
        RecaptchaVerifier $recaptchaVerifier
    ) {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
        $this->recaptchaVerifier = $recaptchaVerifier;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $redirectTo = $this->sanitizeRedirectTarget((string) ($queryParams['redirect_to'] ?? ''));

        if (!empty($_SESSION['member_authenticated'])) {
            return $response->withHeader('Location', $redirectTo)->withStatus(302);
        }

        $method = strtoupper($request->getMethod());
        $error = '';
        $success = '';
        $form = ['identifier' => ''];

        if ($method !== 'POST') {
            $flash = $this->consumeSessionFlash(self::FLASH_KEY);
            $error = trim((string) ($flash['error'] ?? ''));
            $success = trim((string) ($flash['success'] ?? ''));
            $flashForm = (array) ($flash['form'] ?? []);
            $form['identifier'] = trim((string) ($flashForm['identifier'] ?? ''));
            $redirectTo = $this->sanitizeRedirectTarget((string) ($flash['redirect_to'] ?? $redirectTo));
        }

        if ($method === 'POST') {
            $body = (array) ($request->getParsedBody() ?? []);
            $identifier = trim((string) ($body['identifier'] ?? $body['email'] ?? ''));
            $redirectTo = $this->sanitizeRedirectTarget((string) ($body['redirect_to'] ?? $redirectTo));
            $email = strtolower($identifier);
            $password = (string) ($body['password'] ?? '');

            $form['identifier'] = $identifier;

            $recaptchaValidation = $this->verifyRecaptchaToken(
                $request,
                $this->recaptchaVerifier,
                (string) ($body['recaptcha_token'] ?? ''),
                self::RECAPTCHA_ACTION
            );
            if (!$recaptchaValidation['ok']) {
                $error = $recaptchaValidation['message'];
            }

            $user = null;
            $isMemberAuth = false;

            if ($error === '') {
                try {
                    $user = $this->memberAuthRepository->findByEmail($email);
                } catch (Throwable $exception) {
                    $this->logger->error('Falha ao autenticar membro por e-mail.', [
                        'email' => $email,
                        'exception' => $exception,
                    ]);

                    $error = 'Não foi possível processar seu login agora. Tente novamente em instantes.';
                }

                $isMemberAuth = $user !== null
                    && password_verify($password, (string) ($user['password_hash'] ?? ''));
            }

            if ($isMemberAuth) {
                if ((string) ($user['status'] ?? '') === 'pending') {
                    $error = 'Seu cadastro está pendente de aprovação pelo administrador.';
                } elseif ((string) ($user['status'] ?? '') === 'blocked') {
                    $error = 'Seu acesso está bloqueado. Procure a administração.';
                } else {
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }

                    $_SESSION['member_authenticated'] = true;
                    $_SESSION['member_user_id'] = (int) ($user['id'] ?? 0);
                    $_SESSION['member_name'] = (string) ($user['full_name'] ?? 'Membro');
                    $_SESSION['member_email'] = (string) ($user['email'] ?? '');
                    $_SESSION['member_role_key'] = (string) ($user['role_key'] ?? 'member');
                    $_SESSION['member_role_name'] = (string) ($user['role_name'] ?? 'Membro');
                    $_SESSION['member_profile_photo_path'] = (string) ($user['profile_photo_path'] ?? '');

                    $profileCompleted = ((int) ($user['profile_completed'] ?? 0) === 1)
                        && trim((string) ($user['phone_mobile'] ?? '')) !== '';

                    if (!$profileCompleted) {
                        $profileRedirect = '/membro/perfil/completar';
                        if ($redirectTo !== '/membro') {
                            $profileRedirect .= '?redirect_to=' . rawurlencode($redirectTo);
                        }

                        return $response->withHeader('Location', $profileRedirect)->withStatus(302);
                    }

                    return $response->withHeader('Location', $redirectTo)->withStatus(302);
                }
            }

            if ($error === '') {
                $error = 'Credenciais inválidas.';
            }

            $this->storeSessionFlash(self::FLASH_KEY, [
                'error' => $error,
                'form' => [
                    'identifier' => $form['identifier'],
                ],
                'redirect_to' => $redirectTo,
            ]);

            return $response->withHeader('Location', '/entrar')->withStatus(303);
        }

        return $this->renderPage($response, 'pages/member-login.twig', [
            'member_login_error' => $error,
            'member_login_success' => $success,
            'member_login_form' => $form,
            'member_login_redirect_to' => $redirectTo,
            'page_title' => 'Acesso ao SISCEDE | CEDE',
            'page_url' => 'https://cedern.org/entrar',
            'page_description' => 'Acesso ao SISCEDE, o sistema do CEDE para membros e perfis internos autorizados.',
        ]);
    }

    private function sanitizeRedirectTarget(string $redirectTo): string
    {
        $redirectTo = trim($redirectTo);

        if ($redirectTo === '' || !str_starts_with($redirectTo, '/')) {
            return '/membro';
        }

        return $redirectTo;
    }
}
