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
    private const FLASH_KEY_PREFIX = 'admin_member_user_summary_';

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
        'fundador' => 'Fundador',
        'efetivo' => 'Efetivo',
    ];

    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $userId = (int) ($request->getAttribute('id') ?? 0);
        $flash = $this->consumeSessionFlash($this->resolveFlashKey($userId));
        $status = trim((string) ($flash['status'] ?? ''));
        $institutionalRoleConflict = trim((string) ($flash['institutional_role'] ?? ''));
        $user = null;
        $roles = [];
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

        try {
            $roles = $this->memberAuthRepository->findAllRoles();
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao carregar perfis para o resumo do usuário no painel.', [
                'user_id' => $userId,
                'exception' => $exception,
            ]);
        }

        $memberTypeOptions = [];
        foreach (self::MEMBER_TYPE_OPTIONS as $value => $label) {
            $memberTypeOptions[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        if ($user === null) {
            $summaryResponse = $this->renderPage($response, 'pages/admin-member-user-summary.twig', [
                'summary_user' => null,
                'summary_status' => $status,
                'summary_error_message' => $loadError !== '' ? $loadError : 'Usuário não encontrado.',
                'summary_roles' => $roles,
                'summary_member_type_options' => $memberTypeOptions,
                'summary_institutional_role_options' => self::INSTITUTIONAL_ROLE_OPTIONS,
                'page_title' => 'Resumo do Usuário | Dashboard Agenda',
                'page_url' => 'https://cedern.org/painel/usuarios/' . max(0, $userId) . '/resumo',
                'page_description' => 'Resumo de dados do usuário no painel administrativo.',
            ]);

            return $summaryResponse->withStatus(404);
        }

        $roleNameToKey = [];
        foreach ($roles as $role) {
            $roleName = strtolower(trim((string) ($role['name'] ?? '')));
            $roleKey = strtolower(trim((string) ($role['role_key'] ?? '')));
            if ($roleName !== '' && $roleKey !== '') {
                $roleNameToKey[$roleName] = $roleKey;
            }
        }

        $roleKey = strtolower(trim((string) ($user['role_key'] ?? '')));
        $roleName = strtolower(trim((string) ($user['role_name'] ?? '')));
        if ($roleKey === '' && $roleName !== '' && isset($roleNameToKey[$roleName])) {
            $roleKey = $roleNameToKey[$roleName];
        }
        $user['role_key'] = $roleKey;

        $memberType = strtolower(trim((string) ($user['member_type'] ?? '')));
        $user['member_type'] = array_key_exists($memberType, self::MEMBER_TYPE_OPTIONS)
            ? $memberType
            : '';
        $user['member_type_label'] = self::MEMBER_TYPE_OPTIONS[$user['member_type']] ?? 'Não definido';

        $institutionalRoleOptions = self::INSTITUTIONAL_ROLE_OPTIONS;
        $currentInstitutionalRole = trim((string) ($user['institutional_role'] ?? ''));
        if ($currentInstitutionalRole !== '' && !in_array($currentInstitutionalRole, $institutionalRoleOptions, true)) {
            $institutionalRoleOptions[] = $currentInstitutionalRole;
        }
        natcasesort($institutionalRoleOptions);
        $institutionalRoleOptions = array_values($institutionalRoleOptions);

        if ($status === 'institutional-role-conflict') {
            $roleLabel = $institutionalRoleConflict !== '' ? $institutionalRoleConflict : 'esta função institucional';
            $loadError = 'Já existe um usuário ativo com a função "'
                . $roleLabel
                . '". Remova ou altere a função atual antes de prosseguir.';
        }

        $displayName = trim((string) ($user['full_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user['email'] ?? 'Usuário');
        }

        return $this->renderPage($response, 'pages/admin-member-user-summary.twig', [
            'summary_user' => $user,
            'summary_status' => $status,
            'summary_error_message' => $loadError,
            'summary_roles' => $roles,
            'summary_member_type_options' => $memberTypeOptions,
            'summary_institutional_role_options' => $institutionalRoleOptions,
            'dashboard_page_title' => 'Resumo de ' . $displayName,
            'page_title' => 'Resumo de Usuário | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/usuarios/' . (int) ($user['id'] ?? 0) . '/resumo',
            'page_description' => 'Resumo de dados do usuário no painel administrativo.',
        ]);
    }

    private function resolveFlashKey(int $userId): string
    {
        return self::FLASH_KEY_PREFIX . max(0, $userId);
    }
}
