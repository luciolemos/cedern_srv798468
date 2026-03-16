<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class AdminMemberUserSummaryPageAction extends AbstractPageAction
{
    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $userId = (int) ($request->getAttribute('id') ?? 0);
        $user = null;
        $loadError = '';

        if ($userId > 0) {
            try {
                $user = $this->memberAuthRepository->findById($userId);
            } catch (Throwable $exception) {
                $loadError = 'Não foi possível carregar os dados do usuário no momento.';

                $this->logger->error('Falha ao carregar resumo do usuário no painel.', [
                    'user_id' => $userId,
                    'exception' => $exception,
                ]);
            }
        }

        if ($user === null) {
            $summaryResponse = $this->renderPage($response, 'pages/admin-member-user-summary.twig', [
                'summary_user' => null,
                'summary_error_message' => $loadError !== '' ? $loadError : 'Usuário não encontrado.',
                'page_title' => 'Resumo do Usuário | Dashboard Agenda',
                'page_url' => 'https://cedern.org/painel/usuarios/' . max(0, $userId) . '/resumo',
                'page_description' => 'Resumo de dados do usuário no painel administrativo.',
            ]);

            return $summaryResponse->withStatus(404);
        }

        $displayName = trim((string) ($user['full_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user['email'] ?? 'Usuário');
        }

        return $this->renderPage($response, 'pages/admin-member-user-summary.twig', [
            'summary_user' => $user,
            'summary_error_message' => '',
            'dashboard_page_title' => 'Resumo de ' . $displayName,
            'page_title' => 'Resumo de Usuário | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/usuarios/' . (int) ($user['id'] ?? 0) . '/resumo',
            'page_description' => 'Resumo de dados do usuário no painel administrativo.',
        ]);
    }
}
