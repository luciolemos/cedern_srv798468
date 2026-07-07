<?php

declare(strict_types=1);

namespace App\Support;

final class ManagedStorageRootResolver
{
    public static function resolve(?string $configuredRoot, string $projectRoot): ?string
    {
        $normalizedRoot = trim(str_replace('\\', '/', (string) $configuredRoot));

        if ($normalizedRoot === '') {
            return null;
        }

        if (self::isAbsolutePath($normalizedRoot)) {
            return rtrim($normalizedRoot, '/');
        }

        if (str_starts_with($normalizedRoot, './') || str_starts_with($normalizedRoot, '../')) {
            return self::resolveRelativeToBase($projectRoot, $normalizedRoot);
        }

        $relativeRoot = ltrim($normalizedRoot, '/');
        foreach (self::buildAncestorCandidates($projectRoot, $relativeRoot) as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return self::resolveRelativeToBase($projectRoot, $relativeRoot);
    }

    /**
     * @return list<string>
     */
    private static function buildAncestorCandidates(string $projectRoot, string $relativeRoot): array
    {
        $normalizedProjectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $candidates = [];
        $seen = [];
        $current = dirname($normalizedProjectRoot);

        while ($current !== '' && $current !== '.' && !isset($seen[$current])) {
            $seen[$current] = true;
            $candidates[] = self::resolveRelativeToBase($current, $relativeRoot);

            $next = dirname($current);
            if ($next === $current) {
                break;
            }

            $current = $next;
        }

        $projectCandidate = self::resolveRelativeToBase($normalizedProjectRoot, $relativeRoot);
        if (!in_array($projectCandidate, $candidates, true)) {
            $candidates[] = $projectCandidate;
        }

        return $candidates;
    }

    private static function resolveRelativeToBase(string $basePath, string $relativePath): string
    {
        $normalizedBasePath = str_replace('\\', '/', trim($basePath));
        $normalizedRelativePath = str_replace('\\', '/', trim($relativePath));

        $drivePrefix = '';
        $isAbsolute = str_starts_with($normalizedBasePath, '/');

        if (preg_match('/^[A-Za-z]:/', $normalizedBasePath) === 1) {
            $drivePrefix = substr($normalizedBasePath, 0, 2);
            $normalizedBasePath = substr($normalizedBasePath, 2);
            $isAbsolute = true;
        }

        $segments = [];

        foreach (explode('/', trim($normalizedBasePath, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            $segments[] = $segment;
        }

        foreach (explode('/', $normalizedRelativePath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments !== []) {
                    array_pop($segments);
                }

                continue;
            }

            $segments[] = $segment;
        }

        $resolvedPath = implode('/', $segments);

        if ($isAbsolute) {
            $resolvedPath = '/' . $resolvedPath;
        }

        if ($drivePrefix !== '') {
            return $drivePrefix . $resolvedPath;
        }

        return $resolvedPath !== '' ? $resolvedPath : ($isAbsolute ? '/' : '.');
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
