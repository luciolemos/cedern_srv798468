<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Analytics\SiteVisitRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AdminDashboardPageAction extends AbstractPageAction
{
    public const FLASH_KEY = 'admin_dashboard_home';

    private SiteVisitRepository $siteVisitRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, SiteVisitRepository $siteVisitRepository)
    {
        parent::__construct($logger, $twig);
        $this->siteVisitRepository = $siteVisitRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $visitMetrics = [
            'baseline_started_at' => null,
            'total_views' => 0,
            'total_unique_visitors' => 0,
            'today_views' => 0,
            'today_unique_visitors' => 0,
            'last_7_days_views' => 0,
            'last_7_days_unique_visitors' => 0,
            'top_pages' => [],
        ];
        $visitMetricsError = '';

        try {
            $visitMetrics = $this->siteVisitRepository->getDashboardSummary();
        } catch (\Throwable $exception) {
            $visitMetricsError = 'As métricas de visita não puderam ser carregadas agora.';
            $this->logger->warning('Falha ao carregar métricas de visita do dashboard.', [
                'exception' => $exception,
            ]);
        }

        return $this->renderPage($response, 'pages/admin-dashboard-home.twig', [
            'page_title' => 'Dashboard Admin | CEDE',
            'page_url' => 'https://cedern.org/painel',
            'page_description' => 'Painel administrativo da agenda.',
            'dashboard_visit_metrics' => $visitMetrics,
            'dashboard_visit_metrics_top_pages' => $this->formatTopPages(
                $visitMetrics['top_pages']
            ),
            'dashboard_visit_metrics_error' => $visitMetricsError,
            'dashboard_visit_metrics_flash_status' => trim((string) ($flash['status'] ?? '')),
        ]);
    }

    /**
     * @param array<int, array{page_key?: string, page_views?: int, unique_visitors?: int}> $topPages
     * @return array<int, array{label: string, page_key: string, page_views: int, unique_visitors: int}>
     */
    private function formatTopPages(array $topPages): array
    {
        return array_map(function (array $row): array {
            $pageKey = trim((string) ($row['page_key'] ?? '/'));

            return [
                'label' => $this->formatPageLabel($pageKey),
                'page_key' => $pageKey,
                'page_views' => (int) ($row['page_views'] ?? 0),
                'unique_visitors' => (int) ($row['unique_visitors'] ?? 0),
            ];
        }, $topPages);
    }

    private function formatPageLabel(string $pageKey): string
    {
        $normalized = trim($pageKey);

        if ($normalized === '' || $normalized === '/') {
            return 'Home';
        }

        $segmentLabels = [
            'agenda' => 'Agenda',
            'atendimento-fraterno' => 'Atendimento Fraterno',
            'cadastro' => 'Cadastro',
            'contato' => 'Contato',
            'entrar' => 'Entrar',
            'esde' => 'ESDE',
            'estatuto' => 'Estatuto',
            'estudos' => 'Estudos',
            'gestao-cede' => 'Gestão CEDE',
            'historia' => 'História',
            'missao' => 'Missão',
            'nossa-marca' => 'Nossa Marca',
            'palestras' => 'Palestras',
            'politica-de-privacidade' => 'Política de Privacidade',
            'quem-somos' => 'Quem Somos',
            'termos-de-uso' => 'Termos de Uso',
            'valores' => 'Valores',
        ];

        $segments = array_values(array_filter(explode('/', trim($normalized, '/'))));
        $labels = array_map(static function (string $segment) use ($segmentLabels): string {
            $normalizedSegment = strtolower(trim($segment));

            if (isset($segmentLabels[$normalizedSegment])) {
                return $segmentLabels[$normalizedSegment];
            }

            $humanized = str_replace(['-', '_'], ' ', $segment);

            return function_exists('mb_convert_case')
                ? mb_convert_case($humanized, MB_CASE_TITLE, 'UTF-8')
                : ucwords($humanized);
        }, $segments);

        return implode(' / ', $labels);
    }
}
