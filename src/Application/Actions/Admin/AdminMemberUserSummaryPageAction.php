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
    private const MEMBER_ROLE_DISPLAY_LABEL = 'Usuário SISCEDE';

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

    private const ASSOCIATION_STATUS_OPTIONS = [
        'applicant' => 'Solicitante',
        'member' => 'Associado',
        'former' => 'Desligado',
    ];

    private const ACCOUNT_STATUS_OPTIONS = [
        'pending' => 'Pendente',
        'active' => 'Ativo',
        'blocked' => 'Bloqueado',
    ];
    private const PAYMENT_METHOD_LABELS = [
        'boleto' => 'Boleto',
        'pix' => 'Pix',
        'pix_automatico' => 'Pix Automático',
        'manual' => 'Pagamento manual',
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
        $rolesForDisplay = $this->normalizeRolesForDisplay($roles);

        $memberTypeOptions = [];
        foreach (self::MEMBER_TYPE_OPTIONS as $value => $label) {
            $memberTypeOptions[] = [
                'value' => $value,
                'label' => $label,
            ];
        }
        $associationStatusOptions = [];
        foreach (self::ASSOCIATION_STATUS_OPTIONS as $value => $label) {
            $associationStatusOptions[] = [
                'value' => $value,
                'label' => $label,
            ];
        }
        $accountStatusOptions = [];
        foreach (self::ACCOUNT_STATUS_OPTIONS as $value => $label) {
            $accountStatusOptions[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        if ($user === null) {
            $summaryResponse = $this->renderPage($response, 'pages/admin-member-user-summary.twig', [
                'summary_user' => null,
                'summary_status' => $status,
                'summary_error_message' => $loadError !== '' ? $loadError : 'Usuário não encontrado.',
                'summary_roles' => $rolesForDisplay,
                'summary_member_type_options' => $memberTypeOptions,
                'summary_association_status_options' => $associationStatusOptions,
                'summary_account_status_options' => $accountStatusOptions,
                'summary_institutional_role_options' => self::INSTITUTIONAL_ROLE_OPTIONS,
                'page_title' => 'Editar Acesso do Usuário | Dashboard Agenda',
                'page_url' => 'https://cedern.org/painel/usuarios/' . max(0, $userId) . '/resumo',
                'page_description' => 'Edição administrativa de acesso do usuário no painel.',
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
        $associationStatus = strtolower(trim((string) ($user['association_status'] ?? '')));
        $user['association_status'] = array_key_exists($associationStatus, self::ASSOCIATION_STATUS_OPTIONS)
            ? $associationStatus
            : (strtolower(trim((string) ($user['status'] ?? ''))) === 'pending' ? 'applicant' : 'member');
        $user['association_status_label'] = self::ASSOCIATION_STATUS_OPTIONS[$user['association_status']];
        $user['status'] = array_key_exists(strtolower(trim((string) ($user['status'] ?? ''))), self::ACCOUNT_STATUS_OPTIONS)
            ? strtolower(trim((string) ($user['status'] ?? '')))
            : 'pending';
        $user['status_label'] = self::ACCOUNT_STATUS_OPTIONS[$user['status']];
        $user['is_contributor'] = (int) ($user['is_contributor'] ?? 0);
        $user['contributor_label'] = $user['is_contributor'] === 1 ? 'Sim' : 'Não';
        $user['birth_date_display'] = $this->formatDate((string) ($user['birth_date'] ?? ''));
        $user['role_name_display'] = $this->resolveRoleNameDisplay($user);

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

        $user = $this->normalizeFinancialSummary($user);

        $displayName = trim((string) ($user['full_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user['email'] ?? 'Usuário');
        }

        return $this->renderPage($response, 'pages/admin-member-user-summary.twig', [
            'summary_user' => $user,
            'summary_status' => $status,
            'summary_error_message' => $loadError,
            'summary_roles' => $rolesForDisplay,
            'summary_member_type_options' => $memberTypeOptions,
            'summary_association_status_options' => $associationStatusOptions,
            'summary_account_status_options' => $accountStatusOptions,
            'summary_institutional_role_options' => $institutionalRoleOptions,
            'dashboard_page_title' => 'Editar acesso de ' . $displayName,
            'page_title' => 'Editar Acesso do Usuário | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/usuarios/' . (int) ($user['id'] ?? 0) . '/resumo',
            'page_description' => 'Edição administrativa de acesso do usuário no painel.',
        ]);
    }

    private function resolveFlashKey(int $userId): string
    {
        return self::FLASH_KEY_PREFIX . max(0, $userId);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function resolveRoleNameDisplay(array $user): string
    {
        $associationStatus = strtolower(trim((string) ($user['association_status'] ?? '')));
        $roleKey = strtolower(trim((string) ($user['role_key'] ?? '')));
        $roleName = trim((string) ($user['role_name'] ?? ''));

        if ($associationStatus === 'applicant') {
            return 'Sem perfil liberado';
        }

        if ($associationStatus === 'former') {
            return 'Sem perfil ativo';
        }

        return $this->resolveRoleOptionLabel($roleKey, $roleName);
    }

    /**
     * @param array<int, array<string, mixed>> $roles
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRolesForDisplay(array $roles): array
    {
        foreach ($roles as $index => $role) {
            $roleKey = strtolower(trim((string) ($role['role_key'] ?? '')));
            $roleName = trim((string) ($role['name'] ?? ''));
            $roles[$index]['display_name'] = $this->resolveRoleOptionLabel($roleKey, $roleName);
        }

        return $roles;
    }

    private function resolveRoleOptionLabel(string $roleKey, string $roleName): string
    {
        if ($roleKey === 'member') {
            return self::MEMBER_ROLE_DISPLAY_LABEL;
        }

        return $roleName !== '' ? $roleName : 'Membro';
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function normalizeFinancialSummary(array $user): array
    {
        $user['preferred_due_day_display'] = $this->formatDueDay($user['preferred_due_day'] ?? null);
        $user['contribution_amount_display'] = $this->formatCurrency((string) ($user['contribution_amount'] ?? ''));

        $preferredPaymentMethod = strtolower(trim((string) ($user['preferred_payment_method'] ?? '')));
        $user['preferred_payment_method_display'] = self::PAYMENT_METHOD_LABELS[$preferredPaymentMethod] ?? 'Não definido';

        return $user;
    }

    private function formatDueDay(mixed $value): string
    {
        $day = (int) $value;
        if ($day < 1 || $day > 28) {
            return 'Não definido';
        }

        return 'Dia ' . sprintf('%02d', $day);
    }

    private function formatCurrency(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return 'Não definido';
        }

        return 'R$ ' . number_format((float) $normalized, 2, ',', '.');
    }

    private function formatDate(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y');
        } catch (Throwable) {
            return $value;
        }
    }
}
