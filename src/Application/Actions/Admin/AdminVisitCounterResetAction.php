<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Analytics\SiteVisitRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AdminVisitCounterResetAction extends AbstractPageAction
{
    private SiteVisitRepository $siteVisitRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, SiteVisitRepository $siteVisitRepository)
    {
        parent::__construct($logger, $twig);
        $this->siteVisitRepository = $siteVisitRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $memberId = (int) ($_SESSION['member_user_id'] ?? 0);

        try {
            $this->siteVisitRepository->startNewCountingPeriod($memberId, new \DateTimeImmutable('today'));
            $this->storeSessionFlash(AdminDashboardPageAction::FLASH_KEY, [
                'status' => 'visit-count-reset',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao iniciar nova contagem de visitas.', [
                'member_id' => $memberId,
                'exception' => $exception,
            ]);

            $this->storeSessionFlash(AdminDashboardPageAction::FLASH_KEY, [
                'status' => 'visit-count-reset-error',
            ]);
        }

        return $response->withHeader('Location', '/painel')->withStatus(303);
    }
}
