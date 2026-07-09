<?php

declare(strict_types=1);

namespace App\Support;

final class ManagedUploadStorage
{
    private const STORAGE_PREFIX = 'var/storage/';

    private string $projectRoot;

    /** @var array<string, mixed> */
    private array $env;

    /**
     * @param array<string, mixed> $env
     */
    public function __construct(string $projectRoot, array $env = [])
    {
        $this->projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $this->env = $env;
    }

    public function managedWriteModeEnabled(): bool
    {
        return $this->readString('APP_MANAGED_STORAGE_ROOT') !== '';
    }

    public function resolveManagedStorageRoot(): ?string
    {
        return ManagedStorageRootResolver::resolve(
            $this->readString('APP_MANAGED_STORAGE_ROOT'),
            $this->projectRoot
        );
    }

    public function buildRelativePath(string $fileName, string $publicPrefix): string
    {
        return $this->normalizePublicPrefix($publicPrefix) . '/' . ltrim($fileName, '/');
    }

    public function resolveUploadDirectory(string $envKey, string $defaultDirectory): string
    {
        $configuredDirectory = $this->resolveConfiguredUploadDirectory($envKey);

        if ($configuredDirectory !== null) {
            return $configuredDirectory;
        }

        return $this->resolveManagedStorageDefaultDirectory($defaultDirectory);
    }

    public function resolveUploadPublicPrefix(string $envKey, string $defaultPrefix): string
    {
        $configuredPrefix = $this->resolveConfiguredUploadPublicPrefix($envKey);

        if ($configuredPrefix !== null) {
            return $configuredPrefix;
        }

        return $this->normalizePublicPrefix($defaultPrefix);
    }

    public function resolveConfiguredUploadDirectory(string $envKey): ?string
    {
        $configuredDirectory = $this->readString($envKey);

        if ($configuredDirectory === '') {
            return null;
        }

        return $this->resolveManagedStorageDirectory($configuredDirectory);
    }

    public function resolveConfiguredUploadPublicPrefix(string $envKey): ?string
    {
        $configuredPrefix = $this->readString($envKey);

        if ($configuredPrefix === '') {
            return null;
        }

        return $this->normalizePublicPrefix($configuredPrefix);
    }

    public function resolveManagedStorageDefaultDirectory(string $defaultDirectory): string
    {
        $managedStorageRoot = $this->resolveManagedStorageRoot();
        if ($managedStorageRoot !== null) {
            return $this->resolveManagedStorageDirectory($defaultDirectory);
        }

        $autoDetectedDirectory = $this->detectExistingSharedStorageDirectory($defaultDirectory);
        if ($autoDetectedDirectory !== null) {
            return $autoDetectedDirectory;
        }

        return $this->resolveManagedStorageDirectory($defaultDirectory);
    }

    public function resolveManagedStorageDirectory(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', trim($path));
        while (str_starts_with($normalizedPath, './')) {
            $normalizedPath = substr($normalizedPath, 2);
        }

        $managedStorageRoot = $this->resolveManagedStorageRoot();
        $normalizedRelativePath = ltrim($normalizedPath, '/');

        if (
            $managedStorageRoot !== null
            && !$this->isAbsolutePath($normalizedPath)
            && str_starts_with($normalizedRelativePath, self::STORAGE_PREFIX)
        ) {
            $storageSuffix = ltrim(substr($normalizedRelativePath, strlen(self::STORAGE_PREFIX)), '/');

            return $managedStorageRoot . '/' . $storageSuffix;
        }

        return $this->resolveProjectPath($normalizedPath);
    }

    public function resolveProjectPath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);

        if ($this->isAbsolutePath($normalizedPath)) {
            return rtrim($normalizedPath, '/');
        }

        return $this->projectRoot . '/' . ltrim($normalizedPath, '/');
    }

    public function normalizePublicPrefix(string $prefix): string
    {
        return trim(str_replace('\\', '/', $prefix), '/');
    }

    public function prepareWritableDirectory(string $directory): bool
    {
        clearstatcache(true, $directory);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        if (!is_writable($directory)) {
            @chmod($directory, 0775);
            clearstatcache(true, $directory);
        }

        return is_writable($directory);
    }

    /**
     * @param array<int, array{directory: string, public_prefix: string}> $definitions
     * @return array{directory: string, public_prefix: string}|null
     */
    public function firstWritableDefinition(array $definitions): ?array
    {
        foreach ($definitions as $definition) {
            if ($this->prepareWritableDirectory($definition['directory'])) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{directory: string, public_prefix: string, directory_mode?: string}> $additionalDefinitions
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    public function buildReadDefinitions(
        string $directoryEnvKey,
        string $publicPrefixEnvKey,
        string $defaultDirectory,
        string $defaultPublicPrefix,
        array $additionalDefinitions = []
    ): array {
        $definitions = [];
        $configuredDirectory = $this->resolveConfiguredUploadDirectory($directoryEnvKey);
        $configuredPublicPrefix = $this->resolveConfiguredUploadPublicPrefix($publicPrefixEnvKey);

        if ($configuredDirectory !== null || $configuredPublicPrefix !== null) {
            $definitions[] = [
                'directory' => $configuredDirectory ?? $this->resolveManagedStorageDefaultDirectory($defaultDirectory),
                'public_prefix' => $configuredPublicPrefix ?? $this->normalizePublicPrefix($defaultPublicPrefix),
            ];
        }

        $definitions[] = [
            'directory' => $this->resolveManagedStorageDefaultDirectory($defaultDirectory),
            'public_prefix' => $this->normalizePublicPrefix($defaultPublicPrefix),
        ];
        $definitions[] = [
            'directory' => $this->resolveProjectPath($defaultDirectory),
            'public_prefix' => $this->normalizePublicPrefix($defaultPublicPrefix),
        ];

        foreach ($additionalDefinitions as $definition) {
            $definitions[] = [
                'directory' => $this->resolveDefinitionDirectory($definition),
                'public_prefix' => $this->normalizePublicPrefix($definition['public_prefix']),
            ];
        }

        return $this->uniqueDefinitions($definitions);
    }

    /**
     * @param array<int, array{directory: string, public_prefix: string}> $definitions
     * @return array<int, array{directory: string, public_prefix: string}>
     */
    public function uniqueDefinitions(array $definitions): array
    {
        $uniqueDefinitions = [];
        $seenDefinitions = [];

        foreach ($definitions as $definition) {
            $definitionHash = $definition['directory'] . '|' . $definition['public_prefix'];
            if (isset($seenDefinitions[$definitionHash])) {
                continue;
            }

            $seenDefinitions[$definitionHash] = true;
            $uniqueDefinitions[] = $definition;
        }

        return $uniqueDefinitions;
    }

    /**
     * @param array{directory: string, public_prefix: string, directory_mode?: string} $definition
     */
    private function resolveDefinitionDirectory(array $definition): string
    {
        return match ($definition['directory_mode'] ?? 'resolved') {
            'managed' => $this->resolveManagedStorageDirectory($definition['directory']),
            'project' => $this->resolveProjectPath($definition['directory']),
            default => rtrim(str_replace('\\', '/', $definition['directory']), '/'),
        };
    }

    private function readString(string $key): string
    {
        if (array_key_exists($key, $this->env)) {
            return trim((string) $this->env[$key]);
        }

        $value = getenv($key);
        if ($value !== false) {
            return trim((string) $value);
        }

        if (isset($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }

        if (isset($_ENV[$key])) {
            return trim((string) $_ENV[$key]);
        }

        return '';
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function detectExistingSharedStorageDirectory(string $defaultDirectory): ?string
    {
        $normalizedDirectory = ltrim(str_replace('\\', '/', trim($defaultDirectory)), '/');

        if (!str_starts_with($normalizedDirectory, self::STORAGE_PREFIX)) {
            return null;
        }

        $storageSuffix = ltrim(substr($normalizedDirectory, strlen(self::STORAGE_PREFIX)), '/');
        if ($storageSuffix === '') {
            return null;
        }

        foreach ($this->buildAncestorBases() as $basePath) {
            if (!is_dir($basePath) || !is_readable($basePath)) {
                continue;
            }

            $entries = @scandir($basePath);
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $normalizedEntry = strtolower((string) $entry);
                if (!str_contains($normalizedEntry, 'storage')) {
                    continue;
                }

                $candidateRoot = rtrim($basePath, '/') . '/' . $entry;
                if (!is_dir($candidateRoot)) {
                    continue;
                }

                $candidateDirectory = $candidateRoot . '/' . $storageSuffix;
                if (is_dir($candidateDirectory)) {
                    return $candidateDirectory;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function buildAncestorBases(): array
    {
        $bases = [];
        $seen = [];
        $current = dirname($this->projectRoot);

        while ($current !== '' && $current !== '.' && !isset($seen[$current])) {
            $seen[$current] = true;
            $bases[] = $current;

            $next = dirname($current);
            if ($next === $current) {
                break;
            }

            $current = $next;
        }

        return $bases;
    }
}
