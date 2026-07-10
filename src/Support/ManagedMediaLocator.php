<?php

declare(strict_types=1);

namespace App\Support;

final class ManagedMediaLocator
{
    /**
     * @param array<int, array{directory: string, public_prefix: string}> $definitions
     */
    public static function resolve(
        ?string $relativePath,
        array $definitions
    ): ?string {
        $normalizedPath = ltrim(trim((string) $relativePath), '/');
        $fallbackPath = null;

        foreach ($definitions as $definition) {
            $absolutePath = self::resolveScopedAbsolutePath(
                $normalizedPath,
                $definition['public_prefix'],
                $definition['directory']
            );

            if ($absolutePath === null) {
                continue;
            }

            if (is_file($absolutePath)) {
                return $absolutePath;
            }

            $fallbackPath ??= $absolutePath;
        }

        return $fallbackPath;
    }

    private static function resolveScopedAbsolutePath(
        string $normalizedPath,
        string $publicPrefix,
        string $directory
    ): ?string {
        if (
            $normalizedPath === ''
            || $publicPrefix === ''
            || !str_starts_with($normalizedPath, $publicPrefix . '/')
        ) {
            return null;
        }

        $relativeFilePath = ltrim(substr($normalizedPath, strlen($publicPrefix)), '/');
        if (
            $relativeFilePath === ''
            || str_contains($relativeFilePath, '../')
            || str_contains($relativeFilePath, '..\\')
        ) {
            return null;
        }

        return rtrim($directory, '/') . '/' . $relativeFilePath;
    }
}
