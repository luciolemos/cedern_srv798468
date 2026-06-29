<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminFinanceContributionGatewayViewPageAction extends AbstractAdminFinanceContributionGatewayAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $chargeId = (int) ($request->getAttribute('id') ?? 0);
        $context = $this->loadChargeContext($chargeId);
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);

        if ($context === null) {
            $viewResponse = $this->renderPage($response, 'pages/admin-finance-contribution-charge.twig', [
                'finance_contribution_charge' => null,
                'finance_contribution_charge_error' => 'Cobrança não encontrada.',
                'finance_contributions_toast_message' => trim((string) ($flash['message'] ?? '')),
                'finance_contributions_toast_tone' => trim((string) ($flash['tone'] ?? 'success')),
                'page_title' => 'Cobrança de Contribuição | Painel Financeiro',
                'page_url' => $this->buildAbsoluteAppUrl(
                    $request,
                    '/painel/financas/contribuicoes/' . max(0, $chargeId) . '/cobranca'
                ),
                'page_description' => 'Detalhamento da cobrança externa de contribuição no painel financeiro.',
            ]);

            return $viewResponse->withStatus(404);
        }

        $charge = $context['charge'];
        $member = $context['member'];
        $competence = $context['competence'];

        $viewData = [
            'id' => (int) ($charge['id'] ?? 0),
            'member_full_name' => trim((string) ($member['full_name'] ?? 'Associado CEDE')),
            'member_email' => strtolower(trim((string) ($member['email'] ?? ''))),
            'member_cpf_display' => $this->formatCpf((string) ($member['cpf'] ?? '')),
            'competence' => $competence,
            'competence_label' => $this->formatCompetenceLabel($competence),
            'amount_due_label' => $this->formatCurrency($charge['amount_due'] ?? 0),
            'due_date_label' => $this->formatDate((string) ($charge['due_date'] ?? '')),
            'local_status_label' => $this->normalizeLocalChargeStatusLabel((string) ($charge['status'] ?? 'pending')),
            'gateway_configured' => $this->gatewayConfigured(),
            'gateway_provider' => trim((string) ($charge['gateway_provider'] ?? '')) !== ''
                ? strtoupper(trim((string) ($charge['gateway_provider'] ?? '')))
                : 'ASAAS',
            'gateway_payment_id' => trim((string) ($charge['gateway_payment_id'] ?? '')),
            'gateway_billing_type' => strtoupper(trim((string) ($charge['gateway_billing_type'] ?? ''))),
            'gateway_billing_type_label' => $this->normalizeGatewayBillingType((string) ($charge['gateway_billing_type'] ?? '')),
            'gateway_status' => strtoupper(trim((string) ($charge['gateway_status'] ?? ''))),
            'gateway_status_label' => $this->normalizeGatewayStatusLabel((string) ($charge['gateway_status'] ?? '')),
            'gateway_status_tone' => $this->normalizeGatewayStatusTone((string) ($charge['gateway_status'] ?? '')),
            'gateway_invoice_url' => trim((string) ($charge['gateway_invoice_url'] ?? '')),
            'gateway_bank_slip_url' => trim((string) ($charge['gateway_bank_slip_url'] ?? '')),
            'gateway_transaction_receipt_url' => trim((string) ($charge['gateway_transaction_receipt_url'] ?? '')),
            'gateway_pix_payload' => trim((string) ($charge['gateway_pix_payload'] ?? '')),
            'gateway_pix_encoded_image' => trim((string) ($charge['gateway_pix_encoded_image'] ?? '')),
            'gateway_pix_expiration_date_label' => $this->formatDateTime((string) ($charge['gateway_pix_expiration_date'] ?? '')),
            'gateway_last_synced_at_label' => $this->formatDateTime((string) ($charge['gateway_last_synced_at'] ?? '')),
            'gateway_webhook_url' => $this->buildAbsoluteAppUrl($request, '/webhooks/asaas/contribuicoes'),
            'has_gateway_charge' => trim((string) ($charge['gateway_payment_id'] ?? '')) !== '',
        ];

        return $this->renderPage($response, 'pages/admin-finance-contribution-charge.twig', [
            'finance_contribution_charge' => $viewData,
            'finance_contribution_charge_error' => '',
            'finance_contributions_toast_message' => trim((string) ($flash['message'] ?? '')),
            'finance_contributions_toast_tone' => trim((string) ($flash['tone'] ?? 'success')),
            'page_title' => 'Cobrança de Contribuição | Painel Financeiro',
            'page_url' => $this->buildAbsoluteAppUrl(
                $request,
                '/painel/financas/contribuicoes/' . (int) ($charge['id'] ?? 0) . '/cobranca'
            ),
            'page_description' => 'Detalhamento da cobrança externa de contribuição no painel financeiro.',
        ]);
    }
}
