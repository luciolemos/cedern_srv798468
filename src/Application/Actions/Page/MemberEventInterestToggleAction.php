<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Agenda\AgendaRepository;
use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class MemberEventInterestToggleAction extends AbstractMemberGuardedPageAction
{
    private AgendaRepository $agendaRepository;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        MemberAuthRepository $memberAuthRepository,
        AgendaRepository $agendaRepository
    ) {
        parent::__construct($logger, $twig, $memberAuthRepository);
        $this->agendaRepository = $agendaRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $member = $this->resolveAuthenticatedMember($response, true);

        if ($member instanceof Response) {
            return $member;
        }

        $memberId = (int) ($member['id'] ?? 0);
        $eventId = (int) ($request->getAttribute('id') ?? 0);
        $payload = (array) $request->getParsedBody();

        $intent = strtolower(trim((string) ($payload['intent'] ?? 'add')));
        $interested = $intent !== 'remove';

        $redirectTo = trim((string) ($payload['redirect_to'] ?? '/membro'));
        if ($redirectTo === '' || str_starts_with($redirectTo, '/')) {
            $safeRedirectTo = $redirectTo === '' ? '/membro' : $redirectTo;
        } else {
            $safeRedirectTo = '/membro';
        }

        $status = $interested ? 'interest-added' : 'interest-removed';

        try {
            $saved = $this->agendaRepository->setMemberEventInterest($memberId, $eventId, $interested);
            if (!$saved) {
                $status = 'interest-error';
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao atualizar interesse em evento.', [
                'member_id' => $memberId,
                'event_id' => $eventId,
                'error' => $exception->getMessage(),
            ]);
            $status = 'interest-error';
        }

        $this->storeSessionFlash(MemberHomePageAction::FLASH_KEY, [
            'status' => $status,
        ]);

        return $response->withHeader('Location', $safeRedirectTo)->withStatus(303);
    }
}
