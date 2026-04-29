<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutFounderPageAction extends AbstractPageAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $homeContent = require __DIR__ . '/../../../../app/content/home.php';
        $founder = $homeContent['aboutPages']['fundador'] ?? [];
        if (is_array($founder)) {
            $founder = $this->normalizeFounderContent($founder);
        }

        $founderPhoto = trim((string) ($founder['photo'] ?? ''));

        if ($founderPhoto !== '' && !str_starts_with($founderPhoto, '/')) {
            $founder['photo'] = '/' . ltrim($founderPhoto, '/');
        }

        $founderName = trim((string) ($founder['name'] ?? ''));
        $pageDescription = trim((string) ($founder['lead'] ?? ''));

        return $this->renderPage($response, 'pages/about-founder.twig', [
            'founder' => $founder,
            'page_title' => $founderName !== ''
                ? $founderName . ' | Nosso Fundador | CEDE'
                : 'Nosso Fundador | CEDE',
            'page_url' => 'https://cedern.org/quem-somos/fundador',
            'page_description' => $pageDescription !== ''
                ? $pageDescription
                : 'Conheça o legado espiritual e institucional que inspirou a origem do CEDE.',
        ]);
    }

    /**
     * @param array<string, mixed> $founder
     * @return array<string, mixed>
     */
    private function normalizeFounderContent(array $founder): array
    {
        $intro = $founder['intro'] ?? [];
        if (is_string($intro)) {
            $intro = [$intro];
        }

        if (!is_array($intro)) {
            $intro = [];
        }

        $normalizedIntro = [];
        foreach ($intro as $segment) {
            if (!is_string($segment)) {
                continue;
            }

            $trimmedSegment = trim($segment);
            if ($trimmedSegment === '') {
                continue;
            }

            $normalizedIntro[] = $trimmedSegment;
        }

        $founder['intro'] = $normalizedIntro;
        $founder['intro_blocks'] = $this->buildIntroBlocks($normalizedIntro);

        return $founder;
    }

    /**
     * @param array<int, string> $introSegments
     * @return array<int, array<string, mixed>>
     */
    private function buildIntroBlocks(array $introSegments): array
    {
        $blocks = [];
        $orderedListItems = [];

        foreach ($introSegments as $segment) {
            $normalized = str_replace(["\r\n", "\r"], "\n", trim($segment));
            if ($normalized === '') {
                continue;
            }

            $lines = explode("\n", $normalized);
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if ($trimmedLine === '') {
                    $this->flushOrderedListBlock($blocks, $orderedListItems);
                    continue;
                }

                $listItem = $this->parseOrderedListItem($trimmedLine);
                if ($listItem !== null) {
                    $orderedListItems[] = $listItem;
                    continue;
                }

                $this->flushOrderedListBlock($blocks, $orderedListItems);

                if ($this->isIntroHeading($trimmedLine)) {
                    $blocks[] = [
                        'type' => 'heading',
                        'text' => $trimmedLine,
                    ];
                    continue;
                }

                $blocks[] = [
                    'type' => 'paragraph',
                    'text' => $trimmedLine,
                ];
            }

            $this->flushOrderedListBlock($blocks, $orderedListItems);
        }

        return $blocks;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<int, array<string, string>> $orderedListItems
     */
    private function flushOrderedListBlock(array &$blocks, array &$orderedListItems): void
    {
        if ($orderedListItems === []) {
            return;
        }

        $blocks[] = [
            'type' => 'ordered_list',
            'items' => $orderedListItems,
        ];

        $orderedListItems = [];
    }

    /**
     * @return array<string, string>|null
     */
    private function parseOrderedListItem(string $line): ?array
    {
        if (preg_match('/^\d+\.\s*(.+)$/u', $line, $match) !== 1) {
            return null;
        }

        $content = trim($match[1]);
        if ($content === '') {
            return null;
        }

        if (preg_match('/^([^:]+):\s*(.+)$/u', $content, $splitMatch) === 1) {
            return [
                'title' => trim($splitMatch[1]),
                'description' => trim($splitMatch[2]),
            ];
        }

        return [
            'title' => $content,
            'description' => '',
        ];
    }

    private function isIntroHeading(string $line): bool
    {
        if (mb_strlen($line, 'UTF-8') > 80) {
            return false;
        }

        if (preg_match('/[.!?]$/u', $line) === 1) {
            return false;
        }

        return preg_match('/\p{L}/u', $line) === 1;
    }
}
