<?php

declare(strict_types=1);

namespace App\Support;

final class ManagedPublicMediaPath
{
    public static function normalize(?string $rawPath, string $publicPrefix): string
    {
        $normalizedPath = trim(str_replace('\\', '/', (string) $rawPath));
        $normalizedPrefix = trim(str_replace('\\', '/', $publicPrefix), '/');

        if ($normalizedPath === '' || $normalizedPrefix === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $normalizedPath) === 1) {
            return $normalizedPath;
        }

        $publicPath = ltrim($normalizedPath, '/');
        if (str_starts_with($publicPath, $normalizedPrefix . '/')) {
            return $publicPath;
        }

        $fileName = basename($publicPath);
        if (
            $fileName === ''
            || $fileName === '.'
            || $fileName === '..'
            || !str_contains($fileName, '.')
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $fileName) !== 1
        ) {
            return '';
        }

        return $normalizedPrefix . '/' . $fileName;
    }

    public static function toUrl(?string $rawPath, string $publicPrefix, string $baseUrl = ''): string
    {
        $normalizedPath = self::normalize($rawPath, $publicPrefix);

        if ($normalizedPath === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $normalizedPath) === 1) {
            return $normalizedPath;
        }

        $normalizedBaseUrl = rtrim(trim($baseUrl), '/');

        return ($normalizedBaseUrl !== '' ? $normalizedBaseUrl : '') . '/' . ltrim($normalizedPath, '/');
    }
}
