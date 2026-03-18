<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminAgendaDeleteAction extends AbstractAdminAgendaAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminAgendaListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/eventos')->withStatus(303);
        }

        try {
            $this->agendaRepository->deleteEvent($id);
            $this->storeSessionFlash(AdminAgendaListPageAction::FLASH_KEY, [
                'status' => 'deleted',
            ]);

            return $response->withHeader('Location', '/painel/eventos')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao excluir evento no admin.', [
                'event_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(AdminAgendaListPageAction::FLASH_KEY, [
                'status' => 'delete-error',
            ]);

            return $response->withHeader('Location', '/painel/eventos')->withStatus(303);
        }
    }
}
