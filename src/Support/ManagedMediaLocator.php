<?php

declare(strict_types=1);

namespace App\Support;

final class ManagedMediaLocator
{
    /**
     * @param array<int, array{directory: string, public_prefix: string}> $definitions
     * @param array<int, string> $additionalFileNameDirectories
     * @param array<int, string> $recursiveFileNameRoots
     */
    public static function resolve(
        ?string $relativePath,
        array $definitions,
        bool $allowFileNameFallback = true,
        array $additionalFileNameDirectories = [],
        array $recursiveFileNameRoots = []
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

        if ($allowFileNameFallback) {
            $fileNameFallbackPath = self::resolveByFileName(
                $normalizedPath,
                $definitions,
                $additionalFileNameDirectories
            );

            if ($fileNameFallbackPath !== null) {
                return $fileNameFallbackPath;
            }
        }

        $recursiveFallbackPath = self::resolveRecursivelyByFileName($normalizedPath, $recursiveFileNameRoots);
        if ($recursiveFallbackPath !== null) {
            return $recursiveFallbackPath;
        }

        return $fallbackPath;
    }

    /**
     * @param array<int, array{directory: string, public_prefix: string}> $definitions
     * @param array<int, string> $additionalDirectories
     */
    private static function resolveByFileName(
        string $normalizedPath,
        array $definitions,
        array $additionalDirectories
    ): ?string {
        if ($normalizedPath === '') {
            return null;
        }

        $fileName = basename(str_replace('\\', '/', $normalizedPath));
        if (
            $fileName === ''
            || $fileName === '.'
            || $fileName === '..'
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $fileName) !== 1
        ) {
            return null;
        }

        $directories = [];
        $seenDirectories = [];

        foreach ($definitions as $definition) {
            $directory = rtrim($definition['directory'], '/');
            if ($directory === '' || isset($seenDirectories[$directory])) {
                continue;
            }

            $seenDirectories[$directory] = true;
            $directories[] = $directory;
        }

        foreach ($additionalDirectories as $directory) {
            $normalizedDirectory = rtrim((string) $directory, '/');
            if ($normalizedDirectory === '' || isset($seenDirectories[$normalizedDirectory])) {
                continue;
            }

            $seenDirectories[$normalizedDirectory] = true;
            $directories[] = $normalizedDirectory;
        }

        foreach ($directories as $directory) {
            $candidatePath = $directory . '/' . $fileName;

            if (is_file($candidatePath)) {
                return $candidatePath;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $roots
     */
    private static function resolveRecursivelyByFileName(string $normalizedPath, array $roots): ?string
    {
        if ($normalizedPath === '') {
            return null;
        }

        $fileName = basename(str_replace('\\', '/', $normalizedPath));
        if (
            $fileName === ''
            || $fileName === '.'
            || $fileName === '..'
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $fileName) !== 1
        ) {
            return null;
        }

        $seenRoots = [];

        foreach ($roots as $root) {
            $normalizedRoot = rtrim(str_replace('\\', '/', (string) $root), '/');
            if ($normalizedRoot === '' || isset($seenRoots[$normalizedRoot]) || !is_dir($normalizedRoot)) {
                continue;
            }

            $seenRoots[$normalizedRoot] = true;

            try {
                $directoryIterator = new \RecursiveDirectoryIterator(
                    $normalizedRoot,
                    \FilesystemIterator::SKIP_DOTS
                );
                $iterator = new \RecursiveIteratorIterator($directoryIterator);

                foreach ($iterator as $fileInfo) {
                    if (
                        !$fileInfo instanceof \SplFileInfo
                        || $iterator->getDepth() > 4
                        || !$fileInfo->isFile()
                        || $fileInfo->getFilename() !== $fileName
                    ) {
                        continue;
                    }

                    return str_replace('\\', '/', $fileInfo->getPathname());
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
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
