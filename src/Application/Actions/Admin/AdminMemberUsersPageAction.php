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

class AdminMemberUsersPageAction extends AbstractPageAction
{
    public const FLASH_KEY = 'admin_member_users_list';

    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 25, 50, 100];

    private const SORT_FIELDS = ['id', 'full_name', 'email', 'status', 'role_name', 'member_type_label'];

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
        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $institutionalRoleConflict = trim((string) ($flash['institutional_role'] ?? ''));
        $selectedRoleFilter = strtolower(trim((string) ($queryParams['role_filter'] ?? '')));
        $selectedMemberTypeFilter = strtolower(trim((string) ($queryParams['member_type_filter'] ?? '')));
        $selectedInstitutionalRoleFilter = trim((string) ($queryParams['institutional_role_filter'] ?? ''));

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
                'label' => $roleName !== '' ? $roleName : ucfirst($roleKey),
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
            $user['member_type'] = array_key_exists($memberType, self::MEMBER_TYPE_OPTIONS)
                ? $memberType
                : '';
            $user['member_type_label'] = self::MEMBER_TYPE_OPTIONS[$user['member_type']] ?? 'Não definido';

            return $user;
        }, $users);

        $institutionalRoleFilterOptions = self::INSTITUTIONAL_ROLE_OPTIONS;
        foreach ($users as $user) {
            $role = trim((string) ($user['institutional_role'] ?? ''));
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
                static fn (array $user): bool =>
                    strtolower(trim((string) $user['member_type'])) === $selectedMemberTypeFilter
            ));
        }

        if ($selectedInstitutionalRoleFilter !== '') {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool =>
                    trim((string) ($user['institutional_role'] ?? '')) === $selectedInstitutionalRoleFilter
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
                        (string) ($user['role_name'] ?? ''),
                        (string) ($user['institutional_role'] ?? ''),
                        (string) $user['member_type_label'],
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

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

        $requestedPageSize = (int) ($queryParams['per_page'] ?? self::DEFAULT_PAGE_SIZE);
        $pageSize = in_array($requestedPageSize, self::PAGE_SIZE_OPTIONS, true)
            ? $requestedPageSize
            : self::DEFAULT_PAGE_SIZE;

        $totalItems = count($users);
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $currentPage = max(1, (int) ($queryParams['page'] ?? 1));
        $currentPage = min($currentPage, $totalPages);

        $offset = ($currentPage - 1) * $pageSize;
        $users = array_slice($users, $offset, $pageSize);

        $users = array_map(function (array $user): array {
            $user['phone_mobile_display'] = $this->formatMobilePhone((string) ($user['phone_mobile'] ?? ''));
            $user['phone_landline_display'] = $this->formatLandlinePhone((string) ($user['phone_landline'] ?? ''));

            return $user;
        }, $users);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($users), $totalItems) : 0;

        $basePath = '/painel/usuarios';
        $baseQuery = [
            'per_page' => $pageSize,
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
                    'per_page' => $pageSize,
                    'sort' => $field,
                    'dir' => $nextDirection,
                    'q' => $searchTerm,
                    'role_filter' => $selectedRoleFilter,
                    'member_type_filter' => $selectedMemberTypeFilter,
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
                'url' => $basePath . '?' . http_build_query($baseQuery + ['page' => $page]),
            ];
        }

        $previousPageUrl = $currentPage > 1
            ? $basePath . '?' . http_build_query($baseQuery + ['page' => $currentPage - 1])
            : null;
        $nextPageUrl = $currentPage < $totalPages
            ? $basePath . '?' . http_build_query($baseQuery + ['page' => $currentPage + 1])
            : null;

        $pageSizeOptions = array_map(static fn (int $option): array => [
            'value' => $option,
            'selected' => $option === $pageSize,
            'url' => $basePath . '?' . http_build_query([
                'page' => 1,
                'per_page' => $option,
                'sort' => $sortBy,
                'dir' => $sortDirection,
                'q' => $searchTerm,
                'role_filter' => $selectedRoleFilter,
                'member_type_filter' => $selectedMemberTypeFilter,
                'institutional_role_filter' => $selectedInstitutionalRoleFilter,
            ]),
        ], self::PAGE_SIZE_OPTIONS);

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
            'member_users_institutional_role_filter' => $selectedInstitutionalRoleFilter,
            'member_users_role_filter_options' => $roleFilterOptions,
            'member_users_institutional_role_filter_options' => $institutionalRoleFilterOptions,
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
            'page_title' => 'Usuários | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/usuarios',
            'page_description' => 'Validação de cadastro e atribuição de perfis de usuário.',
        ]);
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
}
