<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Member\MemberAuthRepository;
use App\Support\ContributionParticipation;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class AdminMemberUserViewPageAction extends AbstractPageAction
{
    private const MEMBER_ROLE_DISPLAY_LABEL = 'Usuário SISCEDE';

    private const MEMBER_TYPE_OPTIONS = [
        'fundador' => 'Fundador',
        'efetivo' => 'Efetivo',
    ];

    private const ASSOCIATION_STATUS_OPTIONS = [
        'applicant' => 'Solicitante',
        'member' => 'Associado',
        'former' => 'Desligado',
    ];

    private const STATUS_LABELS = [
        'active' => 'Ativo',
        'pending' => 'Pendente',
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
        $user = null;
        $history = [];
        $loadError = '';
        $historyError = '';

        if ($userId > 0) {
            try {
                $user = $this->memberAuthRepository->findById($userId);
            } catch (Throwable $exception) {
                $loadError = 'Não foi possível carregar os dados do usuário no momento.';

                $this->logger->error('Falha ao carregar cadastro do usuário no painel.', [
                    'user_id' => $userId,
                    'exception' => $exception,
                ]);
            }
        }

        if ($user === null) {
            $viewResponse = $this->renderPage($response, 'pages/admin-member-user-view.twig', [
                'view_user' => null,
                'view_error_message' => $loadError !== '' ? $loadError : 'Usuário não encontrado.',
                'page_title' => 'Cadastro do Usuário | Dashboard Agenda',
                'page_url' => 'https://cedern.org/painel/usuarios/' . max(0, $userId),
                'page_description' => 'Visualização completa do cadastro do usuário no painel administrativo.',
            ]);

            return $viewResponse->withStatus(404);
        }

        $user = $this->normalizeUser($user);
        try {
            $history = $this->normalizeHistory(
                $this->memberAuthRepository->findUserAdministrationHistory($userId)
            );
        } catch (Throwable $exception) {
            $historyError = 'Não foi possível carregar o histórico administrativo desta conta.';

            $this->logger->error('Falha ao carregar histórico administrativo do usuário no painel.', [
                'user_id' => $userId,
                'exception' => $exception,
            ]);
        }

        $displayName = trim((string) ($user['full_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user['email'] ?? 'Usuário');
        }

        return $this->renderPage($response, 'pages/admin-member-user-view.twig', [
            'view_user' => $user,
            'view_user_history' => $history,
            'view_user_history_error' => $historyError,
            'view_error_message' => '',
            'dashboard_page_title' => 'Cadastro de ' . $displayName,
            'page_title' => 'Cadastro do Usuário | Dashboard Agenda',
            'page_url' => 'https://cedern.org/painel/usuarios/' . (int) ($user['id'] ?? 0),
            'page_description' => 'Visualização completa do cadastro do usuário no painel administrativo.',
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function normalizeUser(array $user): array
    {
        $memberType = strtolower(trim((string) ($user['member_type'] ?? '')));
        $statusKey = strtolower(trim((string) ($user['status'] ?? '')));
        $institutionalRole = trim((string) ($user['institutional_role'] ?? ''));
        $roleName = trim((string) ($user['role_name'] ?? ''));
        $associationStatus = strtolower(trim((string) ($user['association_status'] ?? '')));

        $user['member_type'] = array_key_exists($memberType, self::MEMBER_TYPE_OPTIONS) ? $memberType : '';
        $user['member_type_label'] = self::MEMBER_TYPE_OPTIONS[$user['member_type']] ?? 'Não definido';
        $user['status_key'] = $statusKey;
        $user['status_label'] = self::STATUS_LABELS[$statusKey] ?? ucfirst($statusKey ?: 'pendente');
        $user['association_status'] = array_key_exists($associationStatus, self::ASSOCIATION_STATUS_OPTIONS)
            ? $associationStatus
            : ($statusKey === 'pending' ? 'applicant' : 'member');
        $user['association_status_label'] = self::ASSOCIATION_STATUS_OPTIONS[$user['association_status']];
        $user['is_contributor'] = ContributionParticipation::normalize($user['is_contributor'] ?? null);
        $user['contributor_label'] = ContributionParticipation::label($user['is_contributor']);
        $user['role_name_display'] = $this->resolveRoleNameDisplay($user, $roleName);
        $user['institutional_role_display'] = $institutionalRole !== '' ? $institutionalRole : 'Sem função definida';
        $user['phone_mobile_display'] = $this->formatMobilePhone((string) ($user['phone_mobile'] ?? ''));
        $user['phone_landline_display'] = $this->formatLandlinePhone((string) ($user['phone_landline'] ?? ''));
        $user['birth_date_display'] = $this->formatDate((string) ($user['birth_date'] ?? ''));
        $user['cpf_display'] = $this->formatCpf((string) ($user['cpf'] ?? ''));
        $user['postal_code_display'] = $this->formatPostalCode((string) ($user['postal_code'] ?? ''));
        $user['address_line_one_display'] = $this->formatAddressLineOne($user);
        $user['address_neighborhood_display'] = $this->formatSimpleText((string) ($user['neighborhood'] ?? ''));
        $user['address_city_state_display'] = $this->formatCityState(
            (string) ($user['address_city'] ?? ''),
            (string) ($user['address_state'] ?? '')
        );
        $user['address_display'] = $this->formatAddress($user);
        $user['preferred_due_day_display'] = $this->formatDueDay($user['preferred_due_day'] ?? null);
        $user['contribution_amount_display'] = $this->formatCurrency((string) ($user['contribution_amount'] ?? ''));
        $user['contribution_plan_label_display'] = trim((string) ($user['contribution_plan_label'] ?? '')) !== ''
            ? (string) $user['contribution_plan_label']
            : 'Não definido';
        $preferredPaymentMethod = strtolower(trim((string) ($user['preferred_payment_method'] ?? '')));
        $user['preferred_payment_method_display'] = self::PAYMENT_METHOD_LABELS[$preferredPaymentMethod] ?? 'Não definido';
        $user['billing_email_opt_in_label'] = (int) ($user['billing_email_opt_in'] ?? 0) === 1 ? 'Autorizado' : 'Não autorizado';
        $user['billing_whatsapp_opt_in_label'] = (int) ($user['billing_whatsapp_opt_in'] ?? 0) === 1 ? 'Autorizado' : 'Não autorizado';
        $user['privacy_notice_accepted_at_display'] = $this->formatDateTime((string) ($user['privacy_notice_accepted_at'] ?? ''));
        $user['privacy_notice_version_display'] = trim((string) ($user['privacy_notice_version'] ?? '')) !== ''
            ? (string) $user['privacy_notice_version']
            : 'Não registrado';
        $user['profile_completed_label'] = (int) ($user['profile_completed'] ?? 0) === 1 ? 'Sim' : 'Não';

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function resolveRoleNameDisplay(array $user, string $roleName): string
    {
        $associationStatus = strtolower(trim((string) ($user['association_status'] ?? '')));
        $roleKey = strtolower(trim((string) ($user['role_key'] ?? '')));

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
     * @param array<int, array<string, mixed>> $history
     * @return array<int, array<string, mixed>>
     */
    private function normalizeHistory(array $history): array
    {
        return array_map(function (array $event): array {
            $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
            $event['created_at_display'] = $this->formatDateTime((string) ($event['created_at'] ?? ''));
            $event['acted_by_user_display'] = trim((string) ($event['acted_by_user_display'] ?? '')) !== ''
                ? (string) $event['acted_by_user_display']
                : 'Sistema';
            $event['event_description'] = trim((string) ($event['event_description'] ?? '')) !== ''
                ? (string) $event['event_description']
                : 'Atualização administrativa registrada.';
            $event['current_state_summary'] = $this->formatAdministrativeSnapshotSummary(
                is_array($payload['current'] ?? null) ? $payload['current'] : []
            );
            $event['rules_applied_display'] = $this->formatAdministrativeRules(
                is_array($payload['rules_applied'] ?? null) ? $payload['rules_applied'] : []
            );

            return $event;
        }, $history);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function formatAdministrativeSnapshotSummary(array $snapshot): string
    {
        if ($snapshot === []) {
            return '';
        }

        $status = strtolower(trim((string) ($snapshot['status'] ?? 'pending')));
        $associationStatus = strtolower(trim((string) ($snapshot['association_status'] ?? 'applicant')));
        $memberType = strtolower(trim((string) ($snapshot['member_type'] ?? '')));
        $institutionalRole = trim((string) ($snapshot['institutional_role'] ?? ''));

        $parts = [
            'Acesso ' . (self::STATUS_LABELS[$status] ?? 'Pendente'),
            'vínculo ' . (self::ASSOCIATION_STATUS_OPTIONS[$associationStatus] ?? 'Solicitante'),
            'contribuição ' . ContributionParticipation::label($snapshot['is_contributor'] ?? null),
        ];

        if (array_key_exists($memberType, self::MEMBER_TYPE_OPTIONS)) {
            $parts[] = 'tipo de sócio ' . self::MEMBER_TYPE_OPTIONS[$memberType];
        }

        if ($institutionalRole !== '') {
            $parts[] = 'função ' . $institutionalRole;
        }

        return implode(' | ', $parts);
    }

    /**
     * @param array<int, mixed> $rules
     */
    private function formatAdministrativeRules(array $rules): string
    {
        $labels = [];

        foreach ($rules as $rule) {
            $normalizedRule = strtolower(trim((string) $rule));
            if ($normalizedRule === '') {
                continue;
            }

            $labels[] = match ($normalizedRule) {
                'new_signup_defaults' => 'Novo cadastro iniciado como solicitante pendente',
                'contributor_defaulted_from_member_type' => 'Contribuição herdada do tipo de sócio',
                'applicant_pending_access' => 'Solicitante permanece com acesso pendente',
                'applicant_without_member_metadata' => 'Solicitante não mantém dados de sócio ou diretoria',
                'former_blocked_access' => 'Desligado permanece com acesso bloqueado',
                'former_without_member_metadata' => 'Desligado não mantém dados de sócio ou diretoria',
                'member_access_normalized_to_active' => 'Associado foi normalizado para acesso ativo',
                'inactive_member_without_institutional_role' => 'Função institucional foi removida fora do status ativo',
                default => ucfirst(str_replace('_', ' ', $normalizedRule)),
            };
        }

        return implode('; ', $labels);
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

    private function formatCpf(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) !== 11) {
            return trim($value) !== '' ? $value : '-';
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }

    private function formatPostalCode(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) !== 8) {
            return trim($value) !== '' ? $value : '-';
        }

        return sprintf('%s-%s', substr($digits, 0, 5), substr($digits, 5, 3));
    }

    /**
     * @param array<string, mixed> $user
     */
    private function formatAddressLineOne(array $user): string
    {
        $street = trim((string) ($user['street_address'] ?? ''));
        $number = trim((string) ($user['address_number'] ?? ''));
        $complement = trim((string) ($user['address_complement'] ?? ''));

        $line = trim(implode(', ', array_filter([$street, $number], static fn (string $part): bool => $part !== '')));
        if ($complement !== '') {
            $line = trim($line . ' - ' . $complement);
        }

        return $line !== '' ? $line : '-';
    }

    private function formatCityState(string $city, string $state): string
    {
        $city = trim($city);
        $state = strtoupper(trim($state));
        $value = trim(implode(' / ', array_filter([$city, $state], static fn (string $part): bool => $part !== '')));

        return $value !== '' ? $value : '-';
    }

    private function formatSimpleText(string $value): string
    {
        $normalized = trim($value);

        return $normalized !== '' ? $normalized : '-';
    }

    /**
     * @param array<string, mixed> $user
     */
    private function formatAddress(array $user): string
    {
        $street = trim((string) ($user['street_address'] ?? ''));
        $number = trim((string) ($user['address_number'] ?? ''));
        $complement = trim((string) ($user['address_complement'] ?? ''));
        $neighborhood = trim((string) ($user['neighborhood'] ?? ''));
        $city = trim((string) ($user['address_city'] ?? ''));
        $state = strtoupper(trim((string) ($user['address_state'] ?? '')));
        $postalCode = $this->formatPostalCode((string) ($user['postal_code'] ?? ''));

        $lineOne = trim(implode(', ', array_filter([$street, $number], static fn (string $part): bool => $part !== '')));
        if ($complement !== '') {
            $lineOne = trim($lineOne . ' - ' . $complement);
        }

        $lineTwo = trim(implode(' - ', array_filter([
            $neighborhood,
            trim(implode('/', array_filter([$city, $state], static fn (string $part): bool => $part !== ''))),
            $postalCode !== '-' ? 'CEP ' . $postalCode : '',
        ], static fn (string $part): bool => $part !== '')));

        $address = trim(implode("\n", array_filter([$lineOne, $lineTwo], static fn (string $part): bool => $part !== '')));

        return $address !== '' ? $address : '-';
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

    private function formatDateTime(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return 'Não registrado';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }
}
