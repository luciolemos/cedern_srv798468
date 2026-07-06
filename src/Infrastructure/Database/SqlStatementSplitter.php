<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

final class SqlStatementSplitter
{
    /**
     * @return array<int, string>
     */
    public function split(string $sql): array
    {
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        $length = strlen($normalized);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $normalized[$index];
            $next = $index + 1 < $length ? $normalized[$index + 1] : '';
            $nextNext = $index + 2 < $length ? $normalized[$index + 2] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }

                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $index++;
                }

                continue;
            }

            if ($inSingleQuote) {
                $buffer .= $char;

                if ($char === '\\' && $next !== '') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }

                if ($char === "'" && $next === "'") {
                    $buffer .= $next;
                    $index++;
                    continue;
                }

                if ($char === "'") {
                    $inSingleQuote = false;
                }

                continue;
            }

            if ($inDoubleQuote) {
                $buffer .= $char;

                if ($char === '\\' && $next !== '') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }

                if ($char === '"' && $next === '"') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }

                if ($char === '"') {
                    $inDoubleQuote = false;
                }

                continue;
            }

            if ($inBacktick) {
                $buffer .= $char;

                if ($char === '`' && $next === '`') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }

                if ($char === '`') {
                    $inBacktick = false;
                }

                continue;
            }

            if ($char === '-' && $next === '-' && ($nextNext === '' || ctype_space($nextNext))) {
                $inLineComment = true;
                $index++;
                continue;
            }

            if ($char === '#') {
                $inLineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $index++;
                continue;
            }

            if ($char === "'") {
                $inSingleQuote = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '"') {
                $inDoubleQuote = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '`') {
                $inBacktick = true;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }

                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
