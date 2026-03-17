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

class AdminCedeManagementPageAction extends AbstractPageAction
{
    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 25, 50, 100];

    private const SORT_FIELDS = ['full_name', 'email', 'institutional_role', 'role_name', 'status'];

    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $selectedInstitutionalRole = trim((string) ($queryParams['institutional_role'] ?? ''));
        $selectedStatus = trim((string) ($queryParams['status_filter'] ?? ''));
        $status = (string) ($queryParams['status'] ?? '');

        $users = [];
        $loadError = '';

        try {
            $users = $this->memberAuthRepository->findAllUsersForAdmin();
        } catch (Throwable $exception) {
            $status = $status !== '' ? $status : 'load-error';
            $loadError = 'Não foi possível carregar a Gestão CEDE no momento.';

            $this->logger->error('Falha ao carregar lista de Gestão CEDE.', [
                'exception' => $exception,
            ]);
        }

        $users = array_values(array_filter(
            $users,
            static fn (array $user): bool => trim((string) ($user['institutional_role'] ?? '')) !== ''
        ));

        $institutionalRoleOptions = array_values(array_unique(array_map(
            static fn (array $user): string => trim((string) ($user['institutional_role'] ?? '')),
            $users
        )));
        $institutionalRoleOptions = array_values(array_filter(
            $institutionalRoleOptions,
            static fn (string $role): bool => $role !== ''
        ));
        natcasesort($institutionalRoleOptions);
        $institutionalRoleOptions = array_values($institutionalRoleOptions);

        if ($selectedInstitutionalRole !== '' && in_array($selectedInstitutionalRole, $institutionalRoleOptions, true)) {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool => (string) ($user['institutional_role'] ?? '') === $selectedInstitutionalRole
            ));
        } else {
            $selectedInstitutionalRole = '';
        }

        $statusOptions = ['active', 'pending', 'blocked'];
        if ($selectedStatus !== '' && in_array($selectedStatus, $statusOptions, true)) {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool => (string) ($user['status'] ?? '') === $selectedStatus
            ));
        } else {
            $selectedStatus = '';
        }

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($user['full_name'] ?? ''),
                        (string) ($user['email'] ?? ''),
                        (string) ($user['institutional_role'] ?? ''),
                        (string) ($user['role_name'] ?? ''),
                        (string) ($user['status'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        $sortBy = (string) ($queryParams['sort'] ?? 'full_name');
        if (!in_array($sortBy, self::SORT_FIELDS, true)) {
            $sortBy = 'full_name';
        }

        $sortDirection = strtolower((string) ($queryParams['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortMultiplier = $sortDirection === 'desc' ? -1 : 1;

        usort($users, static function (array $firstUser, array $secondUser) use ($sortBy, $sortMultiplier): int {
            $firstValue = (string) ($firstUser[$sortBy] ?? '');
            $secondValue = (string) ($secondUser[$sortBy] ?? '');

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

        $basePath = '/painel/gestao-cede';
        $baseQuery = [
            'per_page' => $pageSize,
            'sort' => $sortBy,
            'dir' => $sortDirection,
        ];

        if ($searchTerm !== '') {
            $baseQuery['q'] = $searchTerm;
        }
        if ($selectedInstitutionalRole !== '') {
            $baseQuery['institutional_role'] = $selectedInstitutionalRole;
        }
        if ($selectedStatus !== '') {
            $baseQuery['status_filter'] = $selectedStatus;
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
                    'institutional_role' => $selectedInstitutionalRole,
                    'status_filter' => $selectedStatus,
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
        ], self::PAGE_SIZE_OPTIONS);

        return $this->renderPage($response, 'pages/admin-cede-management.twig', [
            'cede_management_users' => $users,
            'cede_management_status' => $status,
            'cede_management_error_message' => $loadError,
            'cede_management_search' => $searchTerm,
            'cede_management_institutional_role' => $selectedInstitutionalRole,
            'cede_management_institutional_role_options' => $institutionalRoleOptions,
            'cede_management_status_filter' => $selectedStatus,
            'cede_management_sort_links' => $sortLinks,
            'cede_management_pagination' => [
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
            'dashboard_page_kicker' => 'Dashboard',
            'dashboard_page_title' => 'Gestão CEDE',
            'dashboard_page_lead' => 'Usuários com função institucional ativa no CEDE.',
            'page_title' => 'Gestão CEDE | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/gestao-cede',
            'page_description' => 'Lista administrativa de funções institucionais do CEDE.',
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
