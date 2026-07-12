<?php

declare(strict_types=1);

namespace App\Support;

final class ContributionParticipation
{
    public const FORM_VALUE_UNDECLARED = 'undeclared';

    private function __construct()
    {
    }

    public static function normalize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value)) {
            return match ($value) {
                1 => 1,
                0 => 0,
                default => null,
            };
        }

        if (is_float($value)) {
            return self::normalize((int) $value);
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '', 'null', 'undeclared', 'not_declared', 'nao declarou', 'não declarou' => null,
            '1', 'true', 'sim', 'participa' => 1,
            '0', 'false', 'nao', 'não', 'nao participa', 'não participa' => 0,
            default => null,
        };
    }

    public static function toNullableBool(mixed $value): ?bool
    {
        return match (self::normalize($value)) {
            1 => true,
            0 => false,
            default => null,
        };
    }

    public static function label(mixed $value): string
    {
        return match (self::normalize($value)) {
            1 => 'Participa',
            0 => 'Não participa',
            default => 'Não declarou',
        };
    }

    public static function isParticipating(mixed $value): bool
    {
        return self::normalize($value) === 1;
    }

    public static function isNotParticipating(mixed $value): bool
    {
        return self::normalize($value) === 0;
    }

    public static function isUndeclared(mixed $value): bool
    {
        return self::normalize($value) === null;
    }
}
