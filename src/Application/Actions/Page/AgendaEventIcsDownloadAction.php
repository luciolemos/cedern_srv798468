<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Agenda\AgendaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AgendaEventIcsDownloadAction extends AbstractPageAction
{
    private AgendaRepository $agendaRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, AgendaRepository $agendaRepository)
    {
        parent::__construct($logger, $twig);
        $this->agendaRepository = $agendaRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $slug = trim((string) ($request->getAttribute('slug') ?? ''));

        if ($slug === '') {
            return $response->withHeader('Location', '/agenda')->withStatus(302);
        }

        $event = null;
        try {
            $event = $this->agendaRepository->findPublishedBySlug($slug);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao gerar arquivo ICS para evento.', [
                'slug' => $slug,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($event === null) {
            return $response->withHeader('Location', '/agenda')->withStatus(302);
        }

        $startsAt = $this->parseDateTime((string) ($event['starts_at'] ?? ''));
        if ($startsAt === null) {
            return $response->withHeader('Location', '/agenda/' . $slug)->withStatus(302);
        }

        $endsAt = $this->parseDateTime((string) ($event['ends_at'] ?? ''));
        if ($endsAt === null) {
            $endsAt = $startsAt->modify('+90 minutes');
        }

        $title = $this->escapeIcsText((string) ($event['title'] ?? 'Atividade do CEDE'));
        $description = $this->escapeIcsText((string) ($event['description'] ?? ''));
        $locationParts = array_filter([
            trim((string) ($event['location_name'] ?? '')),
            trim((string) ($event['location_address'] ?? '')),
        ]);
        $location = $this->escapeIcsText(implode(' - ', $locationParts));

        $uid = sha1((string) ($event['slug'] ?? $slug) . '|' . (string) ($event['starts_at'] ?? '')) . '@cedern.org';
        $dtStamp = gmdate('Ymd\\THis\\Z');
        $dtStart = $startsAt->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
        $dtEnd = $endsAt->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');

        $icsLines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CEDE//Agenda//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtStamp,
            'DTSTART:' . $dtStart,
            'DTEND:' . $dtEnd,
            'SUMMARY:' . $title,
            'DESCRIPTION:' . $description,
            'LOCATION:' . $location,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        $filename = 'evento-' . preg_replace('/[^a-z0-9\-]+/i', '-', $slug) . '.ics';
        $body = implode("\r\n", $icsLines) . "\r\n";

        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    private function parseDateTime(string $value): ?\DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function escapeIcsText(string $value): string
    {
        $value = str_replace("\r\n", "\\n", $value);
        $value = str_replace("\n", "\\n", $value);

        return str_replace([',', ';'], ['\\,', '\\;'], $value);
    }
}
