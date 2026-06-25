<?php

declare(strict_types=1);

namespace App\Support;

final class BookshopDescriptionSanitizer
{
    private const MAX_DESCRIPTION_LENGTH = 5000;

    /**
     * @var array<string, true>
     */
    private const ALLOWED_TAGS = [
        'a' => true,
        'b' => true,
        'blockquote' => true,
        'br' => true,
        'em' => true,
        'i' => true,
        'li' => true,
        'ol' => true,
        'p' => true,
        'strong' => true,
        'u' => true,
        'ul' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const REMOVE_WITH_CONTENT_TAGS = [
        'base' => true,
        'button' => true,
        'embed' => true,
        'form' => true,
        'iframe' => true,
        'input' => true,
        'link' => true,
        'math' => true,
        'meta' => true,
        'object' => true,
        'script' => true,
        'select' => true,
        'style' => true,
        'svg' => true,
        'textarea' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const SAFE_HREF_SCHEMES = [
        'http' => true,
        'https' => true,
        'mailto' => true,
        'tel' => true,
    ];

    /**
     * @return array{content: string, error: string|null}
     */
    public static function sanitize(string $rawDescription): array
    {
        $trimmed = trim($rawDescription);

        if ($trimmed === '') {
            return ['content' => '', 'error' => null];
        }

        $textLength = self::textLength($trimmed);
        if ($textLength > self::MAX_DESCRIPTION_LENGTH) {
            return [
                'content' => '',
                'error' => sprintf(
                    'Descrição/Sinopse não pode ultrapassar %d caracteres. Atual: %d.',
                    self::MAX_DESCRIPTION_LENGTH,
                    $textLength
                ),
            ];
        }

        return ['content' => self::sanitizeForDisplay($trimmed), 'error' => null];
    }

    public static function sanitizeForDisplay(string $rawDescription): string
    {
        $trimmed = trim($rawDescription);

        if ($trimmed === '') {
            return '';
        }

        return trim(self::sanitizeHtml($trimmed));
    }

    private static function sanitizeHtml(string $html): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="bookshop-description-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $root = $document->getElementById('bookshop-description-root');
        if (!$root instanceof \DOMElement) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        self::sanitizeChildNodes($root);

        return self::innerHtml($root);
    }

    private static function sanitizeChildNodes(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }

            if (!$child instanceof \DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tagName = strtolower($child->tagName);
            if (isset(self::REMOVE_WITH_CONTENT_TAGS[$tagName])) {
                $node->removeChild($child);
                continue;
            }

            if (!isset(self::ALLOWED_TAGS[$tagName])) {
                self::sanitizeChildNodes($child);
                self::unwrapElement($child);
                continue;
            }

            self::sanitizeElementAttributes($child, $tagName);
            self::sanitizeChildNodes($child);
        }
    }

    private static function sanitizeElementAttributes(\DOMElement $element, string $tagName): void
    {
        $href = $tagName === 'a' ? trim($element->getAttribute('href')) : '';

        while ($element->attributes->length > 0) {
            $attribute = $element->attributes->item(0);
            if (!$attribute instanceof \DOMAttr) {
                break;
            }

            $element->removeAttributeNode($attribute);
        }

        if ($tagName !== 'a' || !self::isSafeHref($href)) {
            return;
        }

        $element->setAttribute('href', $href);
        $element->setAttribute('rel', 'nofollow noopener noreferrer');
    }

    private static function isSafeHref(string $href): bool
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($href === '' || str_starts_with($href, '//')) {
            return false;
        }

        $compactHref = strtolower((string) preg_replace('/[\x00-\x20\x7f]+/', '', $href));
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $compactHref, $matches) === 1) {
            return isset(self::SAFE_HREF_SCHEMES[strtolower($matches[1])]);
        }

        return true;
    }

    private static function unwrapElement(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (!$parent instanceof \DOMNode) {
            return;
        }

        while ($element->firstChild instanceof \DOMNode) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function innerHtml(\DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            if ($element->ownerDocument instanceof \DOMDocument) {
                $html .= $element->ownerDocument->saveHTML($child);
            }
        }

        return $html;
    }

    private static function textLength(string $html): int
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    public static function getMaxLength(): int
    {
        return self::MAX_DESCRIPTION_LENGTH;
    }

    public static function getAllowedTagsDoc(): string
    {
        return 'Tags permitidas: <p>, <br>, <strong>, <b>, <em>, <i>, <u>, <ul>, <ol>, <li>, <blockquote>, <a>';
    }
}
