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

class AdminMemberUserViewPageAction extends AbstractPageAction
{
    private const MEMBER_TYPE_OPTIONS = [
        'fundador' => 'Fundador',
        'efetivo' => 'Efetivo',
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
        $loadError = '';

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
        $displayName = trim((string) ($user['full_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user['email'] ?? 'Usuário');
        }

        return $this->renderPage($response, 'pages/admin-member-user-view.twig', [
            'view_user' => $user,
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

        $user['member_type'] = array_key_exists($memberType, self::MEMBER_TYPE_OPTIONS) ? $memberType : '';
        $user['member_type_label'] = self::MEMBER_TYPE_OPTIONS[$user['member_type']] ?? 'Não definido';
        $user['status_key'] = $statusKey;
        $user['status_label'] = self::STATUS_LABELS[$statusKey] ?? ucfirst($statusKey ?: 'pendente');
        $user['role_name_display'] = $roleName !== '' ? $roleName : 'Membro';
        $user['institutional_role_display'] = $institutionalRole !== '' ? $institutionalRole : 'Sem função definida';
        $user['phone_mobile_display'] = $this->formatMobilePhone((string) ($user['phone_mobile'] ?? ''));
        $user['phone_landline_display'] = $this->formatLandlinePhone((string) ($user['phone_landline'] ?? ''));
        $user['birth_date_display'] = $this->formatDate((string) ($user['birth_date'] ?? ''));
        $user['cpf_display'] = $this->formatCpf((string) ($user['cpf'] ?? ''));
        $user['postal_code_display'] = $this->formatPostalCode((string) ($user['postal_code'] ?? ''));
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
