<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractAdminFinanceContributionsAction extends AbstractPageAction
{
    protected const FLASH_KEY = 'admin_finance_contributions';

    protected const PAYMENT_METHOD_LABELS = [
        'boleto' => 'Boleto',
        'pix' => 'Pix',
        'pix_automatico' => 'Pix automático',
        'manual' => 'Pagamento manual',
    ];

    protected MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    protected function normalizeCompetence(mixed $value): string
    {
        $normalized = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}$/', $normalized) === 1) {
            return $normalized;
        }

        return date('Y-m');
    }

    protected function formatCompetenceLabel(string $competence): string
    {
        $normalizedCompetence = $this->normalizeCompetence($competence);
        [$year, $month] = array_map('intval', explode('-', $normalizedCompetence));

        $months = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];

        return ($months[$month] ?? $normalizedCompetence) . ' de ' . $year;
    }

    protected function redirectToList(Response $response, string $competence): Response
    {
        $location = '/painel/financas/contribuicoes?competence=' . rawurlencode($this->normalizeCompetence($competence));

        return $response->withHeader('Location', $location)->withStatus(302);
    }

    protected function resolveActorUserId(): ?int
    {
        $this->ensureSessionStarted();

        $memberUserId = (int) ($_SESSION['member_user_id'] ?? 0);

        return $memberUserId > 0 ? $memberUserId : null;
    }
}
