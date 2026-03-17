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
        $roleId = (int) ($body['role_id'] ?? 0);
        $institutionalRoleInput = trim((string) ($body['institutional_role'] ?? ''));
        $hasInstitutionalRoleInput = $institutionalRoleInput !== '';
        $institutionalRole = in_array($institutionalRoleInput, self::INSTITUTIONAL_ROLE_OPTIONS, true)
            ? $institutionalRoleInput
            : null;

        if ($id <= 0) {
            return $response->withHeader('Location', '/painel/usuarios?status=invalid-role')->withStatus(302);
        }

        if ($hasInstitutionalRoleInput && $institutionalRole === null) {
            return $response
                ->withHeader('Location', '/painel/usuarios?status=invalid-institutional-role')
                ->withStatus(302);
        }

        if ($roleId <= 0) {
            try {
                $currentUser = $this->memberAuthRepository->findById($id);
                $roleId = (int) ($currentUser['role_id'] ?? 0);
            } catch (Throwable $exception) {
                $this->logger->error('Falha ao carregar usuário para atribuição de papel.', [
                    'user_id' => $id,
                    'exception' => $exception,
                ]);

                return $response->withHeader('Location', '/painel/usuarios?status=assign-error')->withStatus(302);
            }
        }

        if ($roleId <= 0) {
            return $response->withHeader('Location', '/painel/usuarios?status=invalid-role')->withStatus(302);
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

                return $response->withHeader('Location', '/painel/usuarios?status=assign-error')->withStatus(302);
            }

            if ($isOccupied) {
                $query = http_build_query([
                    'status' => 'institutional-role-conflict',
                    'institutional_role' => $institutionalRole,
                ]);

                return $response
                    ->withHeader('Location', '/painel/usuarios?' . $query)
                    ->withStatus(302);
            }
        }

        try {
            $this->memberAuthRepository->approveAndAssignRole($id, $roleId, $institutionalRole);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao aprovar/atribuir papel de usuário.', [
                'user_id' => $id,
                'role_id' => $roleId,
                'institutional_role' => $institutionalRole,
                'exception' => $exception,
            ]);

            return $response->withHeader('Location', '/painel/usuarios?status=assign-error')->withStatus(302);
        }

        return $response->withHeader('Location', '/painel/usuarios?status=approved')->withStatus(302);
    }
}
