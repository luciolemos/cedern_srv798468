<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Institutional\InstitutionalContentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class AccessDataPageAction extends AbstractPageAction
{
    private const DOCUMENT_SLUG = 'dados-de-acesso';

    private InstitutionalContentRepository $institutionalContentRepository;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        InstitutionalContentRepository $institutionalContentRepository
    ) {
        parent::__construct($logger, $twig);
        $this->institutionalContentRepository = $institutionalContentRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $document = $this->loadDefaultDocument();

        try {
            $storedContent = $this->institutionalContentRepository->findBySlug(self::DOCUMENT_SLUG);

            if ($storedContent !== null && trim((string) ($storedContent['body'] ?? '')) !== '') {
                $document['title'] = trim((string) ($storedContent['title'] ?? $document['title']));
                $document['body'] = str_replace(
                    ["\r\n", "\r"],
                    "\n",
                    (string) ($storedContent['body'] ?? $document['body'])
                );

                $updatedAt = trim((string) ($storedContent['updated_at'] ?? ''));
                $document['updated_at_label'] = $this->formatDateTimeLabel($updatedAt);
            }
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao carregar dados de acesso.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $this->renderPage($response, 'pages/access-data.twig', [
            'legal_document' => $document,
            'access_data_sections' => $this->parseAccessDataBody((string) ($document['body'] ?? '')),
            'page_title' => 'Dados de acesso | CEDE',
            'page_url' => 'https://cedern.org/dados-de-acesso',
            'page_description' => 'Página institucional reservada para dados de acesso do CEDE.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function loadDefaultDocument(): array
    {
        $defaultsPath = dirname(__DIR__, 4) . '/app/content/access-data.php';
        $defaults = [];

        if (is_file($defaultsPath) && is_readable($defaultsPath)) {
            $loaded = require $defaultsPath;

            if (is_array($loaded)) {
                $defaults = $loaded;
            }
        }

        return [
            'kicker' => 'Institucional',
            'title' => trim((string) ($defaults['title'] ?? 'Dados de acesso')),
            'lead' => 'Página institucional reservada para registro de dados de acesso.',
            'body' => str_replace(["\r\n", "\r"], "\n", (string) ($defaults['body'] ?? '')),
            'updated_at_label' => '',
        ];
    }

    private function formatDateTimeLabel(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $dateTime = new \DateTimeImmutable($value);

            return $dateTime->format('d/m/Y H:i');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseAccessDataBody(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $sections = [];
        $currentSection = null;
        $currentEntry = null;

        $flushEntry = static function (?array &$section, ?array &$entry): void {
            if ($section === null || $entry === null) {
                $entry = null;

                return;
            }

            $fields = (array) ($entry['fields'] ?? []);
            $displayTitle = '';

            foreach ($fields as $field) {
                $label = strtolower((string) ($field['label'] ?? ''));
                if (str_contains($label, 'nome')) {
                    $displayTitle = (string) ($field['value'] ?? '');
                    break;
                }
            }

            if ($displayTitle === '') {
                foreach ($fields as $field) {
                    $label = strtolower((string) ($field['label'] ?? ''));
                    if (str_contains($label, 'usuário') || str_contains($label, 'username') || str_contains($label, 'login')) {
                        $displayTitle = (string) ($field['value'] ?? '');
                        break;
                    }
                }
            }

            $entry['display_title'] = $displayTitle;
            $section['entries'][] = $entry;
            $entry = null;
        };

        $flushSection = static function (array &$allSections, ?array &$section, ?array &$entry) use ($flushEntry): void {
            if ($section === null) {
                return;
            }

            $flushEntry($section, $entry);

            if (($section['entries'] ?? []) !== []) {
                $allSections[] = $section;
            }

            $section = null;
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || preg_match('/^=+$/', $line) === 1) {
                continue;
            }

            if (preg_match('/^https?:\/\//i', $line) === 1) {
                if ($currentSection === null) {
                    $currentSection = [
                        'title' => 'Acesso',
                        'entries' => [],
                    ];
                }

                $flushEntry($currentSection, $currentEntry);
                $currentEntry = [
                    'url' => $line,
                    'fields' => [],
                ];

                continue;
            }

            if (preg_match('/^[-•]\s*(.+?)\s*:\s*(.+)$/u', $line, $matches) === 1) {
                if ($currentSection === null) {
                    $currentSection = [
                        'title' => 'Acesso',
                        'entries' => [],
                    ];
                }

                if ($currentEntry === null) {
                    $currentEntry = [
                        'url' => '',
                        'fields' => [],
                    ];
                }

                $label = trim($matches[1]);
                $value = trim($matches[2]);
                $labelLower = strtolower($label);
                $kind = 'text';

                if (str_contains($labelLower, 'senha')) {
                    $kind = 'secret';
                } elseif (str_contains($labelLower, 'usuário') || str_contains($labelLower, 'username') || str_contains($labelLower, 'login')) {
                    $kind = 'login';
                } elseif (str_contains($labelLower, 'perfil')) {
                    $kind = 'badge';
                }

                $currentEntry['fields'][] = [
                    'label' => $label,
                    'value' => $value,
                    'kind' => $kind,
                ];

                continue;
            }

            $flushSection($sections, $currentSection, $currentEntry);
            $currentSection = [
                'title' => $line,
                'entries' => [],
            ];
        }

        $flushSection($sections, $currentSection, $currentEntry);

        return $sections;
    }
}
