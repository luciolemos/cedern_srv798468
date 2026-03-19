<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class MemberResetPasswordPageAction extends AbstractPageAction
{
    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $method = strtoupper($request->getMethod());
        $queryParams = $request->getQueryParams();
        $body = (array) ($request->getParsedBody() ?? []);
        $token = trim((string) (($method === 'POST' ? $body['token'] ?? '' : $queryParams['token'] ?? '')));
        $errors = [];
        $form = [
            'password' => '',
            'password_confirmation' => '',
        ];

        $resetRecord = $this->resolveResetRecord($token);
        $tokenValid = $token !== '' && $resetRecord !== null;

        if ($method === 'POST') {
            $form['password'] = (string) ($body['password'] ?? '');
            $form['password_confirmation'] = (string) ($body['password_confirmation'] ?? '');

            if (!$tokenValid) {
                $errors[] = 'Este link de redefinição é inválido ou expirou. Solicite um novo.';
            }

            if (strlen($form['password']) < 8) {
                $errors[] = 'A senha deve ter ao menos 8 caracteres.';
            }

            if (!preg_match('/[A-Z]/', $form['password']) || !preg_match('/[0-9]/', $form['password'])) {
                $errors[] = 'A senha deve conter ao menos 1 letra maiúscula e 1 número.';
            }

            if ($form['password'] !== $form['password_confirmation']) {
                $errors[] = 'A confirmação de senha não confere.';
            }

            if (empty($errors) && $resetRecord !== null) {
                try {
                    $passwordHash = password_hash($form['password'], PASSWORD_DEFAULT);
                    $wasConsumed = $this->memberAuthRepository->consumePasswordResetToken(
                        (int) ($resetRecord['id'] ?? 0),
                        (int) ($resetRecord['member_user_id'] ?? 0),
                        $passwordHash
                    );
                } catch (Throwable $exception) {
                    $this->logger->warning('Falha ao consumir token de redefinicao de senha.', [
                        'reset_id' => (int) ($resetRecord['id'] ?? 0),
                        'error' => $exception->getMessage(),
                    ]);
                    $wasConsumed = false;
                }

                if ($wasConsumed) {
                    $this->storeSessionFlash(MemberLoginPageAction::FLASH_KEY, [
                        'success' => 'Senha redefinida com sucesso. Entre com sua nova senha.',
                        'form' => [
                            'identifier' => strtolower(trim((string) ($resetRecord['user_email'] ?? ''))),
                        ],
                        'redirect_to' => '/membro',
                    ]);

                    return $response->withHeader('Location', '/entrar')->withStatus(303);
                }

                $errors[] = 'Não foi possível concluir a redefinição agora. Solicite um novo link.';
                $tokenValid = false;
            }
        }

        return $this->renderPage($response, 'pages/member-password-reset.twig', [
            'member_password_reset_errors' => $errors,
            'member_password_reset_token' => $token,
            'member_password_reset_token_valid' => $tokenValid,
            'member_password_reset_email' => strtolower(trim((string) ($resetRecord['user_email'] ?? ''))),
            'page_title' => 'Redefinir senha | CEDE',
            'page_url' => 'https://cedern.org/redefinir-senha',
            'page_description' => 'Defina uma nova senha para acessar sua área do membro no CEDE.',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveResetRecord(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        try {
            return $this->memberAuthRepository->findActivePasswordResetByToken(hash('sha256', $token));
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao validar token de redefinicao de senha.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
