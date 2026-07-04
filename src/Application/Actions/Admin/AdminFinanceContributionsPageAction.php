<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionsPageAction extends AbstractAdminFinanceContributionsAction
{
    private const DEFAULT_PAGE_SIZE = 10;

    private const PAGE_SIZE_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private const ALL_PAGE_SIZE = 'all';

    private const STATUS_OPTIONS = [
        'all' => 'Todas as situações',
        'paid' => 'Recebidas',
        'open' => 'Em aberto',
        'overdue' => 'Inadimplentes',
        'critical' => 'Críticas',
        'exempt' => 'Isentas',
        'not_generated' => 'Aguardando geração',
        'config_pending' => 'Configuração financeira pendente',
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $competence = $this->normalizeCompetence($queryParams['competence'] ?? null);
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));
        $statusFilter = trim((string) ($queryParams['status_filter'] ?? 'all'));

        if (!array_key_exists($statusFilter, self::STATUS_OPTIONS)) {
            $statusFilter = 'all';
        }

        $rows = [];

        try {
            $rows = array_map(
                fn (array $row): array => $this->normalizeContributionRow($row, $competence),
                $this->memberAuthRepository->findContributionMembersByCompetence($competence)
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar painel de contribuições.', [
                'competence' => $competence,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($searchTerm !== '') {
            $normalizedSearch = strtolower($searchTerm);

            $rows = array_values(array_filter(
                $rows,
                static function (array $row) use ($normalizedSearch): bool {
                    $haystack = implode(' ', [
                        (string) ($row['full_name'] ?? ''),
                        (string) ($row['email'] ?? ''),
                        (string) ($row['cpf_display'] ?? ''),
                        (string) ($row['member_type_label'] ?? ''),
                        (string) ($row['institutional_role'] ?? ''),
                        (string) ($row['status_label'] ?? ''),
                        (string) ($row['payment_method_display'] ?? ''),
                    ]);

                    return stripos(strtolower($haystack), $normalizedSearch) !== false;
                }
            ));
        }

        if ($statusFilter !== 'all') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (string) ($row['status_key'] ?? '') === $statusFilter
            ));
        }

        $summary = $this->buildSummary($rows, $competence);
        $totalItems = count($rows);
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
        $rows = array_slice($rows, $offset, $pageSize);

        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = $totalItems > 0 ? min($offset + count($rows), $totalItems) : 0;

        $basePath = urldecode($request->getUri()->getPath());
        $querySeparatorPosition = strpos($basePath, '?');
        if ($querySeparatorPosition !== false) {
            $basePath = substr($basePath, 0, $querySeparatorPosition);
        }
        if ($basePath === '') {
            $basePath = '/painel/financas/contribuicoes';
        }

        $pageSizeQueryValue = $showAllItems ? self::ALL_PAGE_SIZE : (string) $pageSize;
        $baseQuery = [
            'competence' => $competence,
            'q' => $searchTerm,
            'status_filter' => $statusFilter,
            'per_page' => $pageSizeQueryValue,
        ];

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
        ], self::PAGE_SIZE_OPTIONS);
        $pageSizeOptions[] = [
            'value' => self::ALL_PAGE_SIZE,
            'label' => 'Todos',
            'selected' => $showAllItems,
        ];

        $flashMessage = trim((string) ($flash['message'] ?? ''));
        $flashTone = trim((string) ($flash['tone'] ?? 'success'));

        return $this->renderPage($response, 'pages/admin-finance-contributions.twig', [
            'finance_contributions' => $rows,
            'finance_contributions_filters' => [
                'competence' => $competence,
                'competence_label' => $this->formatCompetenceLabel($competence),
                'q' => $searchTerm,
                'status_filter' => $statusFilter,
            ],
            'finance_contributions_status_options' => self::STATUS_OPTIONS,
            'finance_contributions_payment_methods' => self::PAYMENT_METHOD_LABELS,
            'finance_contributions_pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'start_item' => $startItem,
                'end_item' => $endItem,
                'page_size' => $pageSizeQueryValue,
                'links' => $paginationLinks,
                'previous_url' => $previousPageUrl,
                'next_url' => $nextPageUrl,
                'page_size_options' => $pageSizeOptions,
            ],
            'finance_contributions_summary' => $summary,
            'finance_contributions_toast_message' => $flashMessage,
            'finance_contributions_toast_tone' => $flashTone !== '' ? $flashTone : 'success',
            'page_title' => 'Contribuições | Painel Financeiro',
            'page_url' => $this->buildAbsoluteAppUrl($request, '/painel/financas/contribuicoes'),
            'page_description' => 'Controle administrativo das contribuições mensais dos associados.',
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeContributionRow(array $row, string $competence): array
    {
        $chargeStatus = strtolower(trim((string) ($row['charge_status'] ?? '')));
        $overdueChargeCount = (int) ($row['overdue_charge_count'] ?? 0);
        $preferredDueDay = (int) ($row['preferred_due_day'] ?? 0);
        $configuredAmount = is_numeric((string) ($row['contribution_amount'] ?? null))
            ? (float) ($row['contribution_amount'] ?? 0)
            : 0.0;
        $currentChargeAmount = is_numeric((string) ($row['charge_amount_due'] ?? null))
            ? (float) ($row['charge_amount_due'] ?? 0)
            : 0.0;
        $hasCurrentCharge = ($row['charge_id'] ?? null) !== null;
        $gatewayPaymentId = trim((string) ($row['charge_gateway_payment_id'] ?? ''));
        $gatewayStatus = strtoupper(trim((string) ($row['charge_gateway_status'] ?? '')));
        $gatewayBillingType = strtoupper(trim((string) ($row['charge_gateway_billing_type'] ?? '')));
        $effectiveChargeStatus = $this->resolveEffectiveChargeStatus($chargeStatus, $gatewayStatus);
        $statusKey = $this->resolveStatusKey(
            $row,
            $effectiveChargeStatus,
            $overdueChargeCount,
            $configuredAmount,
            $preferredDueDay
        );
        $statusLabel = $this->resolveStatusLabel($statusKey);
        $statusTone = $this->resolveStatusTone($statusKey);
        $paymentMethodKey = strtolower(trim((string) (
            $row['charge_payment_recorded_method']
            ?? $row['charge_preferred_payment_method']
            ?? $row['preferred_payment_method']
            ?? 'manual'
        )));
        $statusNotes = $this->buildStatusNotes($row, $statusKey, $competence);
        $contactNotes = [];

        if ((int) ($row['billing_email_opt_in'] ?? 0) === 1) {
            $contactNotes[] = 'Cobrança por e-mail autorizada';
        }

        if ((int) ($row['billing_whatsapp_opt_in'] ?? 0) === 1) {
            $contactNotes[] = 'Lembrete por WhatsApp autorizado';
        }

        if ($gatewayPaymentId !== '') {
            $contactNotes[] = 'Cobrança externa ' . ($gatewayBillingType === 'PIX' ? 'Pix' : 'boleto')
                . ' em ' . ($gatewayStatus !== '' ? $this->resolveGatewayStatusLabel($gatewayStatus) : 'processamento') . '.';
        }

        $row['cpf_display'] = $this->formatCpf((string) ($row['cpf'] ?? ''));
        $row['due_date_display'] = $this->formatDate((string) ($row['charge_due_date'] ?? ''));
        $row['amount_due_display'] = $currentChargeAmount > 0
            ? $this->formatCurrency($currentChargeAmount)
            : ($configuredAmount > 0 ? $this->formatCurrency($configuredAmount) : 'Não definido');
        $row['configured_amount_numeric'] = $configuredAmount;
        $row['charge_amount_numeric'] = $currentChargeAmount;
        $row['status_key'] = $statusKey;
        $row['status_label'] = $statusLabel;
        $row['status_tone'] = $statusTone;
        $row['status_notes'] = $statusNotes;
        $row['effective_charge_status'] = $effectiveChargeStatus;
        $row['payment_method_key'] = array_key_exists($paymentMethodKey, self::PAYMENT_METHOD_LABELS) ? $paymentMethodKey : 'manual';
        $row['payment_method_display'] = self::PAYMENT_METHOD_LABELS[$row['payment_method_key']];
        $row['member_type_label'] = trim((string) ($row['member_type_label'] ?? '')) !== ''
            ? (string) $row['member_type_label']
            : 'Não definido';
        $row['institutional_role_display'] = trim((string) ($row['institutional_role'] ?? '')) !== ''
            ? (string) $row['institutional_role']
            : 'Sem função diretiva';
        $row['contact_notes'] = $contactNotes;
        $row['charge_competence_label'] = $this->formatCompetenceLabel((string) ($row['charge_competence'] ?? $competence));
        $row['has_gateway_charge'] = $gatewayPaymentId !== '';
        $row['gateway_status_label'] = $this->resolveGatewayStatusLabel($gatewayStatus);
        $row['gateway_status_tone'] = $this->resolveGatewayStatusTone($gatewayStatus);
        $row['gateway_payment_id'] = $gatewayPaymentId;
        $row['gateway_billing_type_label'] = $gatewayBillingType === 'PIX' ? 'Pix' : ($gatewayBillingType === 'BOLETO' ? 'Boleto' : 'Cobrança');
        $row['can_mark_paid'] = $hasCurrentCharge && $effectiveChargeStatus === 'pending';
        $row['can_mark_exempt'] = $hasCurrentCharge && $effectiveChargeStatus === 'pending';
        $row['can_send_email_reminder'] = $hasCurrentCharge
            && $effectiveChargeStatus === 'pending'
            && (int) ($row['billing_email_opt_in'] ?? 0) === 1
            && filter_var(strtolower(trim((string) ($row['email'] ?? ''))), FILTER_VALIDATE_EMAIL) !== false;
        $row['can_open_whatsapp_reminder'] = $hasCurrentCharge
            && $effectiveChargeStatus === 'pending'
            && (int) ($row['billing_whatsapp_opt_in'] ?? 0) === 1
            && $this->hasWhatsappMobileNumber((string) ($row['phone_mobile'] ?? ''));
        $row['has_current_charge'] = $hasCurrentCharge;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveStatusKey(
        array $row,
        string $chargeStatus,
        int $overdueChargeCount,
        float $configuredAmount,
        int $preferredDueDay
    ): string {
        if ($chargeStatus === 'paid') {
            return 'paid';
        }

        if ($chargeStatus === 'exempt') {
            return 'exempt';
        }

        if ($chargeStatus === 'pending') {
            $chargeDueDate = trim((string) ($row['charge_due_date'] ?? ''));

            if ($chargeDueDate !== '' && $chargeDueDate < date('Y-m-d')) {
                return $overdueChargeCount >= 6 ? 'critical' : 'overdue';
            }

            return 'open';
        }

        if ($overdueChargeCount >= 6) {
            return 'critical';
        }

        if ($overdueChargeCount > 0) {
            return 'overdue';
        }

        if ($configuredAmount <= 0 || $preferredDueDay < 1 || $preferredDueDay > 28) {
            return 'config_pending';
        }

        return 'not_generated';
    }

    private function resolveStatusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'paid' => 'Recebida',
            'open' => 'Em aberto',
            'overdue' => 'Inadimplente',
            'critical' => 'Crítica',
            'exempt' => 'Isenta',
            'not_generated' => 'Aguardando geração',
            'config_pending' => 'Configuração pendente',
            default => 'Não definido',
        };
    }

    private function resolveStatusTone(string $statusKey): string
    {
        return match ($statusKey) {
            'paid' => 'is-on',
            'open', 'exempt', 'not_generated' => 'is-info',
            'overdue' => 'is-warning',
            'critical' => 'is-critical',
            default => 'is-off',
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function buildStatusNotes(array $row, string $statusKey, string $competence): array
    {
        $notes = [];
        $overdueChargeCount = (int) ($row['overdue_charge_count'] ?? 0);
        $oldestOverdueDueDate = trim((string) ($row['oldest_overdue_due_date'] ?? ''));
        $paidAt = trim((string) ($row['charge_paid_at'] ?? ''));
        $exemptionReason = trim((string) ($row['charge_exemption_reason'] ?? ''));
        $gatewayStatus = strtoupper(trim((string) ($row['charge_gateway_status'] ?? '')));

        if (in_array($statusKey, ['overdue', 'critical'], true)) {
            $notes[] = sprintf(
                '%d mensalidade%s vencida%s.',
                $overdueChargeCount,
                $overdueChargeCount === 1 ? '' : 's',
                $overdueChargeCount === 1 ? '' : 's'
            );

            if ($oldestOverdueDueDate !== '') {
                $notes[] = 'Mais antiga em ' . $this->formatDate($oldestOverdueDueDate) . '.';
            }

            if (($row['charge_id'] ?? null) === null) {
                $notes[] = 'Competência ' . $this->formatCompetenceLabel($competence) . ' ainda sem cobrança gerada.';
            }

            return $notes;
        }

        if ($statusKey === 'paid') {
            if ($paidAt !== '') {
                $notes[] = 'Baixada em ' . $this->formatDateTime($paidAt) . '.';
            } elseif (in_array($gatewayStatus, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
                $notes[] = 'Recebimento confirmado no gateway.';
            }
        }

        if ($statusKey === 'exempt') {
            $notes[] = $exemptionReason !== '' ? $exemptionReason : 'Cobrança isentada manualmente.';
        }

        if ($statusKey === 'open' && trim((string) ($row['charge_due_date'] ?? '')) !== '') {
            $notes[] = 'Vence em ' . $this->formatDate((string) $row['charge_due_date']) . '.';
        }

        if ($statusKey === 'not_generated') {
            $notes[] = 'Cobrança da competência ainda não foi gerada.';
        }

        if ($statusKey === 'config_pending') {
            $missingItems = [];

            if (!is_numeric((string) ($row['contribution_amount'] ?? null)) || (float) ($row['contribution_amount'] ?? 0) <= 0) {
                $missingItems[] = 'valor';
            }

            $preferredDueDay = (int) ($row['preferred_due_day'] ?? 0);
            if ($preferredDueDay < 1 || $preferredDueDay > 28) {
                $missingItems[] = 'dia de vencimento';
            }

            $notes[] = 'Completar: ' . implode(' e ', $missingItems) . '.';
        }

        if ($overdueChargeCount > 0) {
            $notes[] = sprintf(
                'Há %d mensalidade%s anterior%s em atraso.',
                $overdueChargeCount,
                $overdueChargeCount === 1 ? '' : 's',
                $overdueChargeCount === 1 ? '' : 'es'
            );

            if ($oldestOverdueDueDate !== '') {
                $notes[] = 'Mais antiga em ' . $this->formatDate($oldestOverdueDueDate) . '.';
            }
        }

        return $notes;
    }

    private function resolveEffectiveChargeStatus(string $chargeStatus, string $gatewayStatus): string
    {
        if (
            $chargeStatus === 'pending'
            && in_array($gatewayStatus, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)
        ) {
            return 'paid';
        }

        return $chargeStatus;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildSummary(array $rows, string $competence): array
    {
        $trackedCount = count($rows);
        $generatedTotal = 0.0;
        $receivedTotal = 0.0;
        $openTotal = 0.0;
        $goodStandingCount = 0;
        $overdueCount = 0;
        $configPendingCount = 0;
        $notGeneratedCount = 0;

        foreach ($rows as $row) {
            $currentAmount = (float) ($row['charge_amount_numeric'] ?? 0);
            $statusKey = (string) ($row['status_key'] ?? '');

            if (($row['has_current_charge'] ?? false) === true) {
                $generatedTotal += $currentAmount;
            }

            if ($statusKey === 'paid') {
                $receivedTotal += $currentAmount;
            }

            if ($statusKey === 'open') {
                $openTotal += $currentAmount;
            }

            if (in_array($statusKey, ['paid', 'open', 'exempt'], true)) {
                $goodStandingCount++;
            }

            if (in_array($statusKey, ['overdue', 'critical'], true)) {
                $overdueCount++;
            }

            if ($statusKey === 'config_pending') {
                $configPendingCount++;
            }

            if ($statusKey === 'not_generated') {
                $notGeneratedCount++;
            }
        }

        return [
            'competence' => $competence,
            'competence_label' => $this->formatCompetenceLabel($competence),
            'tracked_count' => $trackedCount,
            'tracked_count_label' => $trackedCount . ' contribuinte' . ($trackedCount === 1 ? '' : 's'),
            'generated_total_label' => $this->formatCurrency($generatedTotal),
            'received_total_label' => $this->formatCurrency($receivedTotal),
            'open_total_label' => $this->formatCurrency($openTotal),
            'good_standing_count' => $goodStandingCount,
            'overdue_count' => $overdueCount,
            'config_pending_count' => $configPendingCount,
            'not_generated_count' => $notGeneratedCount,
        ];
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
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

    private function formatDate(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    private function formatDateTime(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y H:i');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    private function hasWhatsappMobileNumber(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) === 10
            || strlen($digits) === 11
            || (str_starts_with($digits, '55') && strlen($digits) >= 12);
    }

    private function resolveGatewayStatusLabel(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'PENDING' => 'Pendente',
            'RECEIVED' => 'Recebida',
            'CONFIRMED' => 'Confirmada',
            'OVERDUE' => 'Vencida',
            'RECEIVED_IN_CASH' => 'Recebida em dinheiro',
            'REFUNDED' => 'Estornada',
            'DUNNING_REQUESTED' => 'Em negativação',
            default => trim($value) !== '' ? trim($value) : 'Sem status',
        };
    }

    private function resolveGatewayStatusTone(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'is-on',
            'OVERDUE' => 'is-critical',
            'PENDING', 'DUNNING_REQUESTED' => 'is-warning',
            default => 'is-info',
        };
    }
}
