<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Agenda\AgendaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractAdminAgendaAction extends AbstractPageAction
{
    protected AgendaRepository $agendaRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, AgendaRepository $agendaRepository)
    {
        parent::__construct($logger, $twig);
        $this->agendaRepository = $agendaRepository;
    }

    protected function redirect(Response $response, string $location): Response
    {
        return $response->withHeader('Location', $location)->withStatus(302);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $input): array
    {
        $mode = (string) ($input['mode'] ?? 'presencial');
        $status = (string) ($input['status'] ?? 'draft');
        $audience = $this->normalizeAudience((string) ($input['audience'] ?? ''));

        if (!in_array($mode, ['presencial', 'online', 'hibrido'], true)) {
            $mode = 'presencial';
        }

        if (!in_array($status, ['draft', 'published', 'cancelled'], true)) {
            $status = 'draft';
        }

        return [
            'category_id' => (int) ($input['category_id'] ?? 0),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'theme' => trim((string) ($input['theme'] ?? '')),
            'location_name' => trim((string) ($input['location_name'] ?? '')),
            'location_address' => trim((string) ($input['location_address'] ?? '')),
            'mode' => $mode,
            'meeting_url' => trim((string) ($input['meeting_url'] ?? '')),
            'audience' => $audience,
            'notes' => trim((string) ($input['notes'] ?? '')),
            'starts_at' => $this->normalizeDateTime((string) ($input['starts_at'] ?? '')),
            'ends_at' => $this->normalizeDateTime((string) ($input['ends_at'] ?? '')),
            'status' => $status,
            'is_featured' => (int) (($input['is_featured'] ?? '') === '1'),
        ];
    }

    protected function normalizeAudience(string $value): string
    {
        $normalized = trim($value);

        $allowed = [
            'Jovens',
            'Adultos',
            'Crianças',
            'Público interno',
            'Livre',
        ];

        if (!in_array($normalized, $allowed, true)) {
            return 'Livre';
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    protected function validatePayload(array $payload): array
    {
        $errors = [];

        if ((int) $payload['category_id'] <= 0) {
            $errors[] = 'Selecione uma categoria válida.';
        }

        if ((string) $payload['title'] === '') {
            $errors[] = 'Título é obrigatório.';
        }

        if ((string) $payload['slug'] === '') {
            $errors[] = 'Slug é obrigatório.';
        }

        if ((string) $payload['starts_at'] === '') {
            $errors[] = 'Data e horário de início são obrigatórios.';
        }

        return $errors;
    }

    protected function normalizeDateTime(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        try {
            $dateTime = new \DateTimeImmutable($normalized);
            return $dateTime->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    protected function toDateTimeLocal(?string $value): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return '';
        }

        try {
            $dateTime = new \DateTimeImmutable($normalized);
            return $dateTime->format('Y-m-d\\TH:i');
        } catch (\Throwable $exception) {
            return '';
        }
    }
}
