<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Agenda\AgendaRepository;
use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AgendaDetailPageAction extends AbstractPageAction
{
    public const FLASH_KEY = 'agenda_detail';

    private AgendaRepository $agendaRepository;
    private MemberAuthRepository $memberAuthRepository;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        AgendaRepository $agendaRepository,
        MemberAuthRepository $memberAuthRepository
    ) {
        parent::__construct($logger, $twig);
        $this->agendaRepository = $agendaRepository;
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $this->ensureSessionStarted();

        $slug = (string) ($request->getAttribute('slug') ?? '');

        if ($slug === '') {
            return $response->withHeader('Location', '/agenda')->withStatus(302);
        }

        $agendaEvent = null;

        try {
            $agendaEvent = $this->agendaRepository->findPublishedBySlug($slug);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar detalhe da agenda no banco.', [
                'slug' => $slug,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($agendaEvent === null) {
            return $response->withHeader('Location', '/agenda')->withStatus(302);
        }

        $member = $this->resolveAuthenticatedMemberForAgendaDetail();
        $memberProfileCompleted = false;
        $memberInterested = false;

        if ($member !== null) {
            $memberId = (int) ($member['id'] ?? 0);
            $memberProfileCompleted = ((int) ($member['profile_completed'] ?? 0) === 1)
                && trim((string) ($member['phone_mobile'] ?? '')) !== '';

            if ($memberId > 0 && $memberProfileCompleted) {
                try {
                    $interestedEventIds = $this->agendaRepository->listInterestedEventIdsByMember($memberId);
                    $memberInterested = in_array((int) ($agendaEvent['id'] ?? 0), $interestedEventIds, true);
                } catch (\Throwable $exception) {
                    $this->logger->warning('Falha ao carregar interesse do membro no detalhe da agenda.', [
                        'member_id' => $memberId,
                        'event_slug' => $slug,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $agendaEvent['member_authenticated'] = $member !== null;
        $agendaEvent['member_profile_completed'] = $memberProfileCompleted;
        $agendaEvent['member_interested'] = $memberInterested;

        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));

        $title = (string) ($agendaEvent['title'] ?? 'Atividade do CEDE');
        $pageDescription = (string) ($agendaEvent['description'] ?? 'Detalhes da atividade da agenda do CEDE.');

        return $this->renderPage($response, 'pages/agenda-detail.twig', [
            'agenda_event' => $agendaEvent,
            'agenda_event_status' => $status,
            'page_title' => $title . ' | CEDE',
            'page_url' => 'https://cedern.org/agenda/' . $slug,
            'page_description' => $pageDescription,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAuthenticatedMemberForAgendaDetail(): ?array
    {
        if (empty($_SESSION['member_authenticated'])) {
            return null;
        }

        $memberId = (int) ($_SESSION['member_user_id'] ?? 0);
        if ($memberId <= 0) {
            return null;
        }

        try {
            $member = $this->memberAuthRepository->findById($memberId);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar membro autenticado no detalhe da agenda.', [
                'member_id' => $memberId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($member === null || (string) ($member['status'] ?? '') !== 'active') {
            return null;
        }

        return $member;
    }
}
