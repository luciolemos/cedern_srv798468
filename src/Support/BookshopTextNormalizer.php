<?php

declare(strict_types=1);

namespace App\Support;

final class BookshopTextNormalizer
{
    /**
     * @var array<int, string>
     */
    private const AUTHOR_LOWERCASE_PARTICLES = [
        'da',
        'das',
        'de',
        'do',
        'dos',
        'e',
    ];

    /**
     * @var array<int, string>
     */
    private const AUTHOR_UPPERCASE_TOKENS = [
        'ii',
        'iii',
        'iv',
        'v',
        'vi',
        'vii',
        'viii',
        'ix',
        'x',
        'xi',
        'xii',
        'xiii',
        'xiv',
        'xv',
    ];

    private function __construct()
    {
    }

    public static function normalizeTitle(string $value): string
    {
        $normalized = self::normalizeWhitespace($value);

        if ($normalized === '') {
            return '';
        }

        return mb_strtoupper($normalized, 'UTF-8');
    }

    public static function normalizeAuthorName(string $value): string
    {
        $normalized = self::normalizeWhitespace($value);

        if ($normalized === '') {
            return '';
        }

        $segments = preg_split('/(\s*[,;\/&]\s*)/u', $normalized, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($segments)) {
            return self::normalizeAuthorSegment($normalized);
        }

        foreach ($segments as $index => $segment) {
            if ($index % 2 === 1) {
                $segments[$index] = trim($segment) === '&' ? ' & ' : ', ';
                continue;
            }

            $segments[$index] = self::normalizeAuthorSegment($segment);
        }

        return trim(implode('', $segments), " \t\n\r\0\x0B,");
    }

    private static function normalizeAuthorSegment(string $segment): string
    {
        $segment = self::normalizeWhitespace($segment);
        if ($segment === '') {
            return '';
        }

        $tokens = preg_split('/(\s+)/u', mb_strtolower($segment, 'UTF-8'), -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($tokens)) {
            return $segment;
        }

        $wordIndex = 0;

        foreach ($tokens as $index => $token) {
            if (trim($token) === '') {
                continue;
            }

            $tokens[$index] = self::normalizeAuthorToken($token, $wordIndex === 0);
            $wordIndex++;
        }

        return implode('', $tokens);
    }

    private static function normalizeAuthorToken(string $token, bool $isFirstWord): string
    {
        if (
            !preg_match('/^([^\p{L}\p{N}]*)([\p{L}\p{N}][\p{L}\p{M}\p{N}\.\'’-]*)([^\p{L}\p{N}]*)$/u', $token, $matches)
        ) {
            return mb_convert_case($token, MB_CASE_TITLE, 'UTF-8');
        }

        $prefix = (string) $matches[1];
        $core = (string) $matches[2];
        $suffix = (string) $matches[3];
        $lowerCore = mb_strtolower($core, 'UTF-8');
        $normalizedWord = trim($lowerCore, " \t\n\r\0\x0B.,;:!?()[]{}\"'");

        if ($normalizedWord !== '' && !$isFirstWord && in_array($normalizedWord, self::AUTHOR_LOWERCASE_PARTICLES, true)) {
            return $prefix . $lowerCore . $suffix;
        }

        if ($normalizedWord !== '' && in_array($normalizedWord, self::AUTHOR_UPPERCASE_TOKENS, true)) {
            return $prefix . mb_strtoupper($lowerCore, 'UTF-8') . $suffix;
        }

        if (preg_match('/^\p{L}\.$/u', $lowerCore) === 1) {
            return $prefix . mb_strtoupper(mb_substr($lowerCore, 0, 1, 'UTF-8'), 'UTF-8') . '.' . $suffix;
        }

        return $prefix . self::normalizeCompoundWord($lowerCore) . $suffix;
    }

    private static function normalizeCompoundWord(string $word): string
    {
        $parts = preg_split('/([\'’-])/u', $word, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }

        foreach ($parts as $index => $part) {
            if ($part === '\'' || $part === '’' || $part === '-') {
                continue;
            }

            $parts[$index] = self::uppercaseFirstLetter($part);
        }

        return implode('', $parts);
    }

    private static function uppercaseFirstLetter(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $first = mb_substr($value, 0, 1, 'UTF-8');
        $rest = mb_substr($value, 1, null, 'UTF-8');

        return mb_strtoupper($first, 'UTF-8') . $rest;
    }

    private static function normalizeWhitespace(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return $normalized !== null ? $normalized : trim($value);
    }
}
