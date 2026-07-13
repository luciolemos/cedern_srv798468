<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Member\MemberAuthRepository;
use App\Support\ContributionParticipation;
use App\Support\InstitutionalRole;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class AdminMemberUsersPageAction extends AbstractPageAction
{
    public const FLASH_KEY = 'admin_member_users_list';
    private const MEMBER_ROLE_DISPLAY_LABEL = 'Usuário SISCEDE';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private const ALL_PAGE_SIZE = 'all';

    private const SORT_FIELDS = ['id', 'full_name', 'email', 'status', 'role_name', 'member_type_label'];

    private const INSTITUTIONAL_ROLE_OPTIONS = [
        'Presidente CEDE',
        'Vice-presidente CEDE',
        '1º Secretário',
        '2º Secretário',
        'Diretor de Finanças',
        'Diretor de Eventos',
        'Diretor de Patrimônio',
        'Diretor de Estudos',
        'Diretor de Atendimento Fraterno',
        'Diretor de Comunicação',
        'Coordenador',
        'Coordenador(a) do Curso de Mediunidade',
        'Conselheiro',
    ];

    private const MEMBER_TYPE_OPTIONS = [
        'fundador' => 'Fundador',
        'efetivo' => 'Efetivo',
        'undefined' => 'Não definido',
    ];

    private const STATUS_FILTER_OPTIONS = [
        'active' => 'Ativo',
        'pending' => 'Pendente',
        'blocked' => 'Bloqueado',
    ];

    private const ASSOCIATION_STATUS_FILTER_OPTIONS = [
        'applicant' => 'Solicitante',
        'member' => 'Associado',
        'former' => 'Desligado',
    ];

    private const CONTRIBUTOR_FILTER_OPTIONS = [
        'yes' => 'Participa',
        'no' => 'Não participa',
        'undeclared' => 'Não declarou',
    ];

    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $exportFormat = strtolower(trim((string) ($queryParams['export'] ?? '')));
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $institutionalRoleConflict = trim((string) ($flash['institutional_role'] ?? ''));
        $selectedRoleFilter = strtolower(trim((string) ($queryParams['role_filter'] ?? '')));
        $selectedMemberTypeFilter = strtolower(trim((string) ($queryParams['member_type_filter'] ?? '')));
        $selectedStatusFilter = strtolower(trim((string) ($queryParams['status_filter'] ?? '')));
        $selectedAssociationStatusFilter = strtolower(trim((string) ($queryParams['association_status_filter'] ?? '')));
        $selectedContributorFilter = strtolower(trim((string) ($queryParams['contributor_filter'] ?? '')));
        $selectedInstitutionalRoleFilter = InstitutionalRole::normalize(
            trim((string) ($queryParams['institutional_role_filter'] ?? ''))
        ) ?? '';

        $users = [];
        $roles = [];
        $loadError = '';

        try {
            $users = $this->memberAuthRepository->findAllUsersForAdmin();
            $roles = $this->memberAuthRepository->findAllRoles();
        } catch (Throwable $exception) {
            $status = $status !== '' ? $status : 'load-error';
            $loadError = 'Não foi possível carregar os usuários no momento. Verifique o schema de membros no banco.';

            $this->logger->error('Falha ao carregar usuários do painel.', [
                'exception' => $exception,
            ]);
        }

        $roleNameToKey = [];
        $roleFilterKeys = [];
        $roleFilterOptions = [];
        foreach ($roles as $role) {
            $roleKey = strtolower(trim((string) ($role['role_key'] ?? '')));
            $roleName = trim((string) ($role['name'] ?? ''));

            if ($roleKey === '') {
                continue;
            }

            if ($roleName !== '') {
                $roleNameToKey[strtolower($roleName)] = $roleKey;
            }

            $roleFilterKeys[$roleKey] = true;
            $roleFilterOptions[] = [
                'value' => $roleKey,
                'label' => $this->resolveRoleOptionLabel($roleKey, $roleName !== '' ? $roleName : ucfirst($roleKey)),
            ];
        }

        $users = array_map(function (array $user) use ($roleNameToKey): array {
            $roleKey = strtolower(trim((string) ($user['role_key'] ?? '')));
            $roleName = strtolower(trim((string) ($user['role_name'] ?? '')));
            if ($roleKey === '' && $roleName !== '' && isset($roleNameToKey[$roleName])) {
                $roleKey = $roleNameToKey[$roleName];
            }
            $user['role_key'] = $roleKey;

            $memberType = strtolower(trim((string) ($user['member_type'] ?? '')));
            $user['member_type'] = array_key_exists($memberType, self::MEMBER_TYPE_OPTIONS) && $memberType !== 'undefined'
                ? $memberType
                : '';
            $user['member_type_label'] = $user['member_type'] !== ''
                ? self::MEMBER_TYPE_OPTIONS[$user['member_type']]
                : self::MEMBER_TYPE_OPTIONS['undefined'];
            $associationStatus = strtolower(trim((string) ($user['association_status'] ?? '')));
            $user['association_status'] = in_array($associationStatus, ['applicant', 'member', 'former'], true)
                ? $associationStatus
                : (strtolower(trim((string) ($user['status'] ?? ''))) === 'pending' ? 'applicant' : 'member');
            $user['association_status_label'] = match ($user['association_status']) {
                'member' => 'Associado',
                'former' => 'Desligado',
                default => 'Solicitante',
            };
            $user['institutional_role'] = InstitutionalRole::normalize((string) ($user['institutional_role'] ?? '')) ?? '';
            $user['is_contributor'] = ContributionParticipation::normalize($user['is_contributor'] ?? null);
            $user['contributor_label'] = ContributionParticipation::label($user['is_contributor']);
            $user['role_name_display'] = $this->resolveRoleNameDisplay($user);

            return $user;
        }, $users);

        $institutionalRoleFilterOptions = self::INSTITUTIONAL_ROLE_OPTIONS;
        foreach ($users as $user) {
            $role = trim((string) $user['institutional_role']);
            if ($role !== '' && !in_array($role, $institutionalRoleFilterOptions, true)) {
                $institutionalRoleFilterOptions[] = $role;
            }
        }
        natcasesort($institutionalRoleFilterOptions);
        $institutionalRoleFilterOptions = array_values($institutionalRoleFilterOptions);

        if ($selectedRoleFilter !== '' && !isset($roleFilterKeys[$selectedRoleFilter])) {
            $selectedRoleFilter = '';
        }
        if (
            $selectedMemberTypeFilter !== ''
            && !array_key_exists($selectedMemberTypeFilter, self::MEMBER_TYPE_OPTIONS)
        ) {
            $selectedMemberTypeFilter = '';
        }
        if ($selectedStatusFilter !== '' && !array_key_exists($selectedStatusFilter, self::STATUS_FILTER_OPTIONS)) {
            $selectedStatusFilter = '';
        }
        if (
            $selectedAssociationStatusFilter !== ''
            && !array_key_exists($selectedAssociationStatusFilter, self::ASSOCIATION_STATUS_FILTER_OPTIONS)
        ) {
            $selectedAssociationStatusFilter = '';
        }
        if (
            $selectedContributorFilter !== ''
            && !array_key_exists($selectedContributorFilter, self::CONTRIBUTOR_FILTER_OPTIONS)
        ) {
            $selectedContributorFilter = '';
        }
        if (
            $selectedInstitutionalRoleFilter !== ''
            && !in_array($selectedInstitutionalRoleFilter, $institutionalRoleFilterOptions, true)
        ) {
            $selectedInstitutionalRoleFilter = '';
        }

        if ($selectedRoleFilter !== '') {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool =>
                    strtolower(trim((string) $user['role_key'])) === $selectedRoleFilter
            ));
        }

        if ($selectedMemberTypeFilter !== '') {
            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($selectedMemberTypeFilter): bool {
                    $memberType = strtolower(trim((string) $user['member_type']));

                    if ($selectedMemberTypeFilter === 'undefined') {
                        return $memberType === '';
                    }

                    return $memberType === $selectedMemberTypeFilter;
                }
            ));
        }

        if ($selectedStatusFilter !== '') {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool =>
                    strtolower(trim((string) ($user['status'] ?? ''))) === $selectedStatusFilter
            ));
        }

        if ($selectedAssociationStatusFilter !== '') {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool =>
                    strtolower(trim((string) $user['association_status'])) === $selectedAssociationStatusFilter
            ));
        }

        if ($selectedContributorFilter !== '') {
            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($selectedContributorFilter): bool {
                    $contributorState = ContributionParticipation::normalize($user['is_contributor'] ?? null);

                    return match ($selectedContributorFilter) {
                        'yes' => $contributorState === 1,
                        'no' => $contributorState === 0,
                        'undeclared' => $contributorState === null,
                    };
                }
            ));
        }

        if ($selectedInstitutionalRoleFilter !== '') {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool =>
                    trim((string) $user['institutional_role']) === $selectedInstitutionalRoleFilter
            ));
        }

        if ($status === 'institutional-role-conflict') {
            $roleLabel = $institutionalRoleConflict !== '' ? $institutionalRoleConflict : 'esta função institucional';
            $loadError = 'Já existe um usuário ativo com a função "'
                . $roleLabel
                . '". Remova ou altere a função atual antes de prosseguir.';
        }

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($user['full_name'] ?? ''),
                        (string) ($user['email'] ?? ''),
                        (string) ($user['status'] ?? ''),
                        (string) $user['role_name_display'],
                        (string) $user['institutional_role'],
                        (string) $user['member_type_label'],
                        (string) $user['association_status_label'],
                        (string) $user['contributor_label'],
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        $summary = $this->buildUsersSummary($users);

        $sortBy = (string) ($queryParams['sort'] ?? 'id');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'id';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($users, static function (array $firstUser, array $secondUser) use ($sortBy, $sortMultiplier): int {
            $firstValue = (string) ($firstUser[$sortBy] ?? '');
            $secondValue = (string) ($secondUser[$sortBy] ?? '');

            if ($sortBy === 'id') {
                $comparison = (int) $firstValue <=> (int) $secondValue;

                return $comparison * $sortMultiplier;
            }

            $comparison = strnatcasecmp($firstValue, $secondValue);

            return $comparison * $sortMultiplier;
        });

        $users = array_map(function (array $user): array {
            $user['phone_mobile_display'] = $this->formatMobilePhone((string) ($user['phone_mobile'] ?? ''));
            $user['phone_landline_display'] = $this->formatLandlinePhone((string) ($user['phone_landline'] ?? ''));
            $user['status_label'] = $this->resolveAccessStatusLabel((string) ($user['status'] ?? ''));
            $user['institutional_role_display'] = trim((string) $user['institutional_role']) !== ''
                ? trim((string) $user['institutional_role'])
                : '-';

            return $user;
        }, $users);

        if ($exportFormat === 'csv') {
            return $this->renderCsvExport($response, $users);
        }

        $totalItems = count($users);
        $requestedPageSize = trim((string) ($queryParams['per_page'] ?? (string) self::DEFAULT_PAGE_SIZE));
        $showAllItems = $requestedPageSize === self::ALL_PAGE_SIZE;
        $pageSize = self::DEFAULT_PAGE_SIZE;

        if (!$showAllItems) {
            $requestedPageSizeNumber = (int) $requestedPageSize;
            $pageSize = in_array($requestedPageSizeNumber, self::PAGE_SIZE_OPTIONS, true)
                ? $requestedPageSizeNumber
                : self::DEFAULT_PAGE_SIZE;
        } else {
            $pageSize = max($totalItems, 1);
        }

        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $users = array_slice($users, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($users), $totalItems) : 0;

        $pageSizeQueryValue = $showAllItems ? self::ALL_PAGE_SIZE : (string) $pageSize;
        $basePath = '/painel/usuarios';
        $baseQuery = [
            'per_page' => $pageSizeQueryValue,
            'sort' => $sortBy,
            'dir' => $sortDirection,
        ];

        if ($searchTerm !== '') {
            $baseQuery['q'] = $searchTerm;
        }
        if ($selectedRoleFilter !== '') {
            $baseQuery['role_filter'] = $selectedRoleFilter;
        }
        if ($selectedMemberTypeFilter !== '') {
            $baseQuery['member_type_filter'] = $selectedMemberTypeFilter;
        }
        if ($selectedStatusFilter !== '') {
            $baseQuery['status_filter'] = $selectedStatusFilter;
        }
        if ($selectedAssociationStatusFilter !== '') {
            $baseQuery['association_status_filter'] = $selectedAssociationStatusFilter;
        }
        if ($selectedContributorFilter !== '') {
            $baseQuery['contributor_filter'] = $selectedContributorFilter;
        }
        if ($selectedInstitutionalRoleFilter !== '') {
            $baseQuery['institutional_role_filter'] = $selectedInstitutionalRoleFilter;
        }

        $sortLinks = [];
        foreach (self::SORT_FIELDS as $field) {
            $nextDirection = $sortBy === $field && $sortDirection === 'asc' ? 'desc' : 'asc';
            $indicator = '↕';

            if ($sortBy === $field) {
                $indicator = $sortDirection === 'asc' ? '↑' : '↓';
            }

            $sortLinks[$field] = [
                'url' => $basePath . '?' . http_build_query([
                    'page' => 1,
                    'per_page' => $pageSizeQueryValue,
                    'sort' => $field,
                    'dir' => $nextDirection,
                    'q' => $searchTerm,
                    'role_filter' => $selectedRoleFilter,
                    'member_type_filter' => $selectedMemberTypeFilter,
                    'status_filter' => $selectedStatusFilter,
                    'association_status_filter' => $selectedAssociationStatusFilter,
                    'contributor_filter' => $selectedContributorFilter,
                    'institutional_role_filter' => $selectedInstitutionalRoleFilter,
                ]),
                'indicator' => $indicator,
                'active' => $sortBy === $field,
            ];
        }

        $paginationLinks = [];
        for ($page = 1; $page <= $totalPages; $page++) {
            $paginationLinks[] = [
                'number' => $page,
                'active' => $page === $currentPage,
                'url' => $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $page])),
            ];
        }

        $previousPageUrl = $currentPage > 1
            ? $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $currentPage - 1]))
            : null;
        $nextPageUrl = $currentPage < $totalPages
            ? $basePath . '?' . http_build_query(array_merge($baseQuery, ['page' => $currentPage + 1]))
            : null;

        $pageSizeOptions = array_map(static fn (int $option): array => [
            'value' => (string) $option,
            'label' => (string) $option,
            'selected' => !$showAllItems && $option === $pageSize,
            'url' => $basePath . '?' . http_build_query([
                'page' => 1,
                'per_page' => $option,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'q' => $searchTerm,
                'role_filter' => $selectedRoleFilter,
                'member_type_filter' => $selectedMemberTypeFilter,
                'status_filter' => $selectedStatusFilter,
                'association_status_filter' => $selectedAssociationStatusFilter,
                'contributor_filter' => $selectedContributorFilter,
                'institutional_role_filter' => $selectedInstitutionalRoleFilter,
            ]),
        ], self::PAGE_SIZE_OPTIONS);
        $pageSizeOptions[] = [
            'value' => self::ALL_PAGE_SIZE,
            'label' => 'Todos',
            'selected' => $showAllItems,
            'url' => $basePath . '?' . http_build_query([
                'page' => 1,
                'per_page' => self::ALL_PAGE_SIZE,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'q' => $searchTerm,
                'role_filter' => $selectedRoleFilter,
                'member_type_filter' => $selectedMemberTypeFilter,
                'status_filter' => $selectedStatusFilter,
                'association_status_filter' => $selectedAssociationStatusFilter,
                'contributor_filter' => $selectedContributorFilter,
                'institutional_role_filter' => $selectedInstitutionalRoleFilter,
            ]),
        ];

        $memberTypeOptions = [];
        foreach (self::MEMBER_TYPE_OPTIONS as $value => $label) {
            $memberTypeOptions[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $this->renderPage($response, 'pages/admin-member-users.twig', [
            'member_users' => $users,
            'member_roles' => $roles,
            'member_institutional_role_options' => self::INSTITUTIONAL_ROLE_OPTIONS,
            'member_member_type_options' => $memberTypeOptions,
            'admin_status' => $status,
            'admin_error_message' => $loadError,
            'member_users_search' => $searchTerm,
            'member_users_role_filter' => $selectedRoleFilter,
            'member_users_member_type_filter' => $selectedMemberTypeFilter,
            'member_users_status_filter' => $selectedStatusFilter,
            'member_users_association_status_filter' => $selectedAssociationStatusFilter,
            'member_users_contributor_filter' => $selectedContributorFilter,
            'member_users_institutional_role_filter' => $selectedInstitutionalRoleFilter,
            'member_users_role_filter_options' => $roleFilterOptions,
            'member_users_status_filter_options' => self::STATUS_FILTER_OPTIONS,
            'member_users_association_status_filter_options' => self::ASSOCIATION_STATUS_FILTER_OPTIONS,
            'member_users_contributor_filter_options' => self::CONTRIBUTOR_FILTER_OPTIONS,
            'member_users_institutional_role_filter_options' => $institutionalRoleFilterOptions,
            'member_users_export_csv_url' => $basePath . '?' . http_build_query([
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'q' => $searchTerm,
                'role_filter' => $selectedRoleFilter,
                'member_type_filter' => $selectedMemberTypeFilter,
                'status_filter' => $selectedStatusFilter,
                'association_status_filter' => $selectedAssociationStatusFilter,
                'contributor_filter' => $selectedContributorFilter,
                'institutional_role_filter' => $selectedInstitutionalRoleFilter,
                'export' => 'csv',
            ]),
            'member_users_summary' => $summary,
            'member_users_sort_links' => $sortLinks,
            'member_users_pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'page_size' => $pageSize,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'links' => $paginationLinks,
                'previous_url' => $previousPageUrl,
                'next_url' => $nextPageUrl,
                'page_size_options' => $pageSizeOptions,
            ],
            'page_title' => 'Pessoas CEDE | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/usuarios',
            'page_description' => 'Validação de solicitações, gestão de associados e configuração administrativa de pessoas.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    private function renderCsvExport(Response $response, array $users): Response
    {
        $handle = fopen('php://temp', 'w+');
        if (!is_resource($handle)) {
            $response->getBody()->write('Falha ao preparar exportacao.');

            return $response->withStatus(500);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'id',
            'nome',
            'email',
            'telefone_fixo',
            'telefone_celular',
            'vinculo',
            'tipo_socio',
            'contribuinte',
            'funcao_cede',
            'perfil_siscede',
            'acesso_siscede',
        ], ';');

        foreach ($users as $user) {
            fputcsv($handle, [
                (string) ($user['id'] ?? ''),
                (string) ($user['full_name'] ?? ''),
                (string) ($user['email'] ?? ''),
                (string) ($user['phone_landline_display'] ?? '-'),
                (string) ($user['phone_mobile_display'] ?? '-'),
                (string) ($user['association_status_label'] ?? ''),
                (string) ($user['member_type_label'] ?? ''),
                (string) ($user['contributor_label'] ?? ''),
                (string) ($user['institutional_role_display'] ?? '-'),
                (string) ($user['role_name_display'] ?? ''),
                (string) ($user['status_label'] ?? ''),
            ], ';');
        }

        rewind($handle);
        $body = stream_get_contents($handle) ?: '';
        fclose($handle);

        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('America/Fortaleza')))->format('Ymd-His');
        $filename = 'pessoas-cede-' . $timestamp . '.csv';

        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    private function formatMobilePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '-';
        }

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return $value;
    }

    private function formatLandlinePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '-';
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return $value;
    }

    private function resolveAccessStatusLabel(string $status): string
    {
        $normalizedStatus = strtolower(trim($status));

        return self::STATUS_FILTER_OPTIONS[$normalizedStatus] ?? ($status !== '' ? $status : '-');
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

    private function resolveRoleOptionLabel(string $roleKey, string $roleName): string
    {
        if ($roleKey === 'member') {
            return self::MEMBER_ROLE_DISPLAY_LABEL;
        }

        return $roleName !== '' ? $roleName : 'Membro';
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<string, int>
     */
    private function buildUsersSummary(array $users): array
    {
        $summary = [
            'total_count' => count($users),
            'applicant_count' => 0,
            'member_count' => 0,
            'former_count' => 0,
            'active_count' => 0,
            'pending_count' => 0,
            'blocked_count' => 0,
            'contributor_count' => 0,
            'non_contributor_count' => 0,
            'undeclared_contributor_count' => 0,
            'institutional_role_count' => 0,
            'without_institutional_role_count' => 0,
        ];

        foreach ($users as $user) {
            $associationStatus = strtolower(trim((string) ($user['association_status'] ?? '')));
            if ($associationStatus === 'member') {
                $summary['member_count']++;
            } elseif ($associationStatus === 'former') {
                $summary['former_count']++;
            } else {
                $summary['applicant_count']++;
            }

            $accessStatus = strtolower(trim((string) ($user['status'] ?? '')));
            if ($accessStatus === 'active') {
                $summary['active_count']++;
            } elseif ($accessStatus === 'blocked') {
                $summary['blocked_count']++;
            } else {
                $summary['pending_count']++;
            }

            $contributorState = ContributionParticipation::normalize($user['is_contributor'] ?? null);
            if ($contributorState === 1) {
                $summary['contributor_count']++;
            } elseif ($contributorState === 0) {
                $summary['non_contributor_count']++;
            } else {
                $summary['undeclared_contributor_count']++;
            }

            if (trim((string) ($user['institutional_role'] ?? '')) !== '') {
                $summary['institutional_role_count']++;
            } else {
                $summary['without_institutional_role_count']++;
            }
        }

        return $summary;
    }
}
