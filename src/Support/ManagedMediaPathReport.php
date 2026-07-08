<?php

declare(strict_types=1);

namespace App\Support;

final class ManagedMediaPathReport
{
    private const MEMBER_PHOTO_DEFAULT_DIR = 'var/storage/member-photos';
    private const MEMBER_PHOTO_DEFAULT_PREFIX = 'media/membros/fotos';
    private const MEMBER_PHOTO_LEGACY_DIR = 'public/assets/img/member-photos';
    private const MEMBER_PHOTO_LEGACY_PREFIX = 'assets/img/member-photos';
    private const MEMBER_AVATAR_LEGACY_DIR = 'public/assets/img/avatar';
    private const MEMBER_AVATAR_LEGACY_PREFIX = 'assets/img/avatar';
    private const MEMBER_GENERIC_LEGACY_DIR = 'public/assets/img';

    private const BOOKSHOP_COVER_DEFAULT_DIR = 'var/storage/bookshop/covers';
    private const BOOKSHOP_COVER_DEFAULT_PREFIX = 'media/livraria/capas';
    private const BOOKSHOP_COVER_LEGACY_DIR = 'public/assets/img/bookshop-covers';
    private const BOOKSHOP_COVER_LEGACY_PREFIX = 'assets/img/bookshop-covers';
    private const BOOKSHOP_COVER_FALLBACK_DIR = 'var/cache/bookshop-covers';

    private const LIBRARY_DOC_DEFAULT_DIR = 'var/storage/library/docs';
    private const LIBRARY_DOC_DEFAULT_PREFIX = 'media/biblioteca/docs';
    private const LIBRARY_DOC_LEGACY_DIR = 'public/assets/docs/library';
    private const LIBRARY_DOC_LEGACY_PREFIX = 'assets/docs/library';

    private const LIBRARY_COVER_DEFAULT_DIR = 'var/storage/library/covers';
    private const LIBRARY_COVER_DEFAULT_PREFIX = 'media/biblioteca/capas';
    private const LIBRARY_COVER_LEGACY_DIR = 'public/assets/img/library-covers';
    private const LIBRARY_COVER_LEGACY_PREFIX = 'assets/img/library-covers';

    private const PATRIMONY_DOC_DEFAULT_DIR = 'var/storage/patrimony/docs';
    private const PATRIMONY_DOC_DEFAULT_PREFIX = 'media/patrimonio/docs';
    private const PATRIMONY_DOC_LEGACY_DIR = 'public/assets/docs/patrimony';
    private const PATRIMONY_DOC_LEGACY_PREFIX = 'assets/docs/patrimony';

    private const PATRIMONY_IMAGE_DEFAULT_DIR = 'var/storage/patrimony/img';
    private const PATRIMONY_IMAGE_DEFAULT_PREFIX = 'media/patrimonio/img';
    private const PATRIMONY_IMAGE_LEGACY_DIR = 'public/assets/img/patrimony';
    private const PATRIMONY_IMAGE_LEGACY_PREFIX = 'assets/img/patrimony';

    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?string $kind = null, ?string $file = null): array
    {
        $targets = $this->buildTargets();
        $report = [
            'project_root' => $this->projectRoot,
            'environment' => $this->resolveEnvironmentReport(),
            'managed_storage_root' => $this->describePath(
                ManagedStorageRootResolver::resolve(
                    (string) ($_ENV['APP_MANAGED_STORAGE_ROOT'] ?? ''),
                    $this->projectRoot
                ),
                true
            ) + [
                'raw' => trim((string) ($_ENV['APP_MANAGED_STORAGE_ROOT'] ?? '')),
            ],
            'targets' => $targets,
        ];

        $normalizedKind = trim((string) $kind);
        $normalizedFile = basename(trim((string) $file));

        if ($normalizedKind !== '' && isset($targets[$normalizedKind])) {
            $report['probe'] = $this->buildProbe($normalizedKind, $normalizedFile, $targets[$normalizedKind]);
        }

        return $report;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildTargets(): array
    {
        return [
            'member_photos' => $this->buildTargetReport(
                'MEMBER_PROFILE_PHOTO_UPLOAD_DIR',
                'MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX',
                self::MEMBER_PHOTO_DEFAULT_DIR,
                self::MEMBER_PHOTO_DEFAULT_PREFIX,
                [
                    [
                        'label' => 'legacy_member_photos',
                        'directory' => $this->resolveProjectPath(self::MEMBER_PHOTO_LEGACY_DIR),
                        'public_prefix' => self::MEMBER_PHOTO_LEGACY_PREFIX,
                    ],
                    [
                        'label' => 'legacy_member_avatar',
                        'directory' => $this->resolveProjectPath(self::MEMBER_AVATAR_LEGACY_DIR),
                        'public_prefix' => self::MEMBER_AVATAR_LEGACY_PREFIX,
                    ],
                    [
                        'label' => 'legacy_generic_assets_img',
                        'directory' => $this->resolveProjectPath(self::MEMBER_GENERIC_LEGACY_DIR),
                        'public_prefix' => null,
                    ],
                ]
            ),
            'bookshop_covers' => $this->buildTargetReport(
                'BOOKSHOP_COVER_UPLOAD_DIR',
                'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
                self::BOOKSHOP_COVER_DEFAULT_DIR,
                self::BOOKSHOP_COVER_DEFAULT_PREFIX,
                [
                    [
                        'label' => 'legacy_bookshop_covers',
                        'directory' => $this->resolveProjectPath(self::BOOKSHOP_COVER_LEGACY_DIR),
                        'public_prefix' => self::BOOKSHOP_COVER_LEGACY_PREFIX,
                    ],
                    [
                        'label' => 'bookshop_fallback_cache',
                        'directory' => $this->resolveProjectPath(self::BOOKSHOP_COVER_FALLBACK_DIR),
                        'public_prefix' => self::BOOKSHOP_COVER_DEFAULT_PREFIX,
                    ],
                    [
                        'label' => 'bookshop_temporary_cache',
                        'directory' => rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/')
                            . '/natalcode/bookshop-covers',
                        'public_prefix' => self::BOOKSHOP_COVER_DEFAULT_PREFIX,
                    ],
                ]
            ),
            'library_docs' => $this->buildTargetReport(
                'LIBRARY_UPLOAD_DIR',
                'LIBRARY_UPLOAD_PUBLIC_PREFIX',
                self::LIBRARY_DOC_DEFAULT_DIR,
                self::LIBRARY_DOC_DEFAULT_PREFIX,
                [
                    [
                        'label' => 'legacy_library_docs',
                        'directory' => $this->resolveProjectPath(self::LIBRARY_DOC_LEGACY_DIR),
                        'public_prefix' => self::LIBRARY_DOC_LEGACY_PREFIX,
                    ],
                ]
            ),
            'library_covers' => $this->buildTargetReport(
                'LIBRARY_COVER_UPLOAD_DIR',
                'LIBRARY_COVER_UPLOAD_PUBLIC_PREFIX',
                self::LIBRARY_COVER_DEFAULT_DIR,
                self::LIBRARY_COVER_DEFAULT_PREFIX,
                [
                    [
                        'label' => 'legacy_library_covers',
                        'directory' => $this->resolveProjectPath(self::LIBRARY_COVER_LEGACY_DIR),
                        'public_prefix' => self::LIBRARY_COVER_LEGACY_PREFIX,
                    ],
                ]
            ),
            'patrimony_docs' => $this->buildTargetReport(
                'PATRIMONY_DOCUMENT_UPLOAD_DIR',
                'PATRIMONY_DOCUMENT_UPLOAD_PUBLIC_PREFIX',
                self::PATRIMONY_DOC_DEFAULT_DIR,
                self::PATRIMONY_DOC_DEFAULT_PREFIX,
                [
                    [
                        'label' => 'legacy_patrimony_docs',
                        'directory' => $this->resolveProjectPath(self::PATRIMONY_DOC_LEGACY_DIR),
                        'public_prefix' => self::PATRIMONY_DOC_LEGACY_PREFIX,
                    ],
                ]
            ),
            'patrimony_images' => $this->buildTargetReport(
                'PATRIMONY_IMAGE_UPLOAD_DIR',
                'PATRIMONY_IMAGE_UPLOAD_PUBLIC_PREFIX',
                self::PATRIMONY_IMAGE_DEFAULT_DIR,
                self::PATRIMONY_IMAGE_DEFAULT_PREFIX,
                [
                    [
                        'label' => 'legacy_patrimony_images',
                        'directory' => $this->resolveProjectPath(self::PATRIMONY_IMAGE_LEGACY_DIR),
                        'public_prefix' => self::PATRIMONY_IMAGE_LEGACY_PREFIX,
                    ],
                ]
            ),
        ];
    }

    /**
     * @param array<int, array{label: string, directory: string, public_prefix: string|null}> $extraReadCandidates
     * @return array<string, mixed>
     */
    private function buildTargetReport(
        string $directoryEnvKey,
        string $publicPrefixEnvKey,
        string $defaultDirectory,
        string $defaultPublicPrefix,
        array $extraReadCandidates
    ): array {
        $managedRoot = ManagedStorageRootResolver::resolve(
            (string) ($_ENV['APP_MANAGED_STORAGE_ROOT'] ?? ''),
            $this->projectRoot
        );

        $rawDirectory = trim((string) ($_ENV[$directoryEnvKey] ?? ''));
        $rawPublicPrefix = trim((string) ($_ENV[$publicPrefixEnvKey] ?? ''));
        $configuredDirectory = $rawDirectory !== '' ? $rawDirectory : $defaultDirectory;
        $configuredPublicPrefix = $this->normalizePrefix($rawPublicPrefix !== '' ? $rawPublicPrefix : $defaultPublicPrefix);
        $resolvedDirectory = $this->resolveManagedDirectory($configuredDirectory, $managedRoot);
        $defaultResolvedDirectory = $this->resolveManagedDirectory($defaultDirectory, $managedRoot);

        $readCandidates = [
            [
                'label' => 'configured',
                'directory' => $resolvedDirectory,
                'public_prefix' => $configuredPublicPrefix,
            ],
            [
                'label' => 'default_managed',
                'directory' => $defaultResolvedDirectory,
                'public_prefix' => $this->normalizePrefix($defaultPublicPrefix),
            ],
        ];

        foreach ($extraReadCandidates as $candidate) {
            $readCandidates[] = $candidate;
        }

        $readCandidates = $this->uniqueCandidates($readCandidates);

        return [
            'directory_env_key' => $directoryEnvKey,
            'directory_env_raw' => $rawDirectory,
            'public_prefix_env_key' => $publicPrefixEnvKey,
            'public_prefix_env_raw' => $rawPublicPrefix,
            'configured_directory' => $this->describePath($resolvedDirectory, true),
            'configured_public_prefix' => $configuredPublicPrefix,
            'default_managed_directory' => $this->describePath($defaultResolvedDirectory, true),
            'default_public_prefix' => $this->normalizePrefix($defaultPublicPrefix),
            'read_candidates' => array_map(
                fn (array $candidate): array => $this->describeCandidate($candidate),
                $readCandidates
            ),
        ];
    }

    /**
     * @param array<string, mixed> $target
     * @return array<string, mixed>
     */
    private function buildProbe(string $kind, string $fileName, array $target): array
    {
        $probe = [
            'kind' => $kind,
            'file' => $fileName,
            'candidates' => [],
            'existing_matches' => [],
        ];

        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            $probe['error'] = 'Informe um nome de arquivo válido no parâmetro `file`.';

            return $probe;
        }

        /** @var array<int, array<string, mixed>> $readCandidates */
        $readCandidates = (array) ($target['read_candidates'] ?? []);

        foreach ($readCandidates as $candidate) {
            $directory = (string) ($candidate['directory'] ?? '');
            $candidatePath = rtrim($directory, '/') . '/' . $fileName;
            $entry = [
                'label' => (string) ($candidate['label'] ?? 'candidate'),
                'directory' => $directory,
                'public_prefix' => $candidate['public_prefix'] ?? null,
                'path' => $candidatePath,
                'exists' => is_file($candidatePath),
                'readable' => is_file($candidatePath) && is_readable($candidatePath),
                'permissions' => is_file($candidatePath)
                    ? substr(sprintf('%o', (int) @fileperms($candidatePath)), -4)
                    : null,
                'public_url' => isset($candidate['public_prefix']) && is_string($candidate['public_prefix'])
                    && trim((string) $candidate['public_prefix']) !== ''
                    ? '/' . trim((string) $candidate['public_prefix'], '/') . '/' . rawurlencode($fileName)
                    : null,
            ];

            $probe['candidates'][] = $entry;

            if (!empty($entry['exists'])) {
                $probe['existing_matches'][] = $entry;
            }
        }

        return $probe;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveEnvironmentReport(): array
    {
        $appEnvFileFromGetenv = getenv('APP_ENV_FILE');
        $envFileFromServer = $appEnvFileFromGetenv !== false
            ? trim((string) $appEnvFileFromGetenv)
            : (isset($_SERVER['APP_ENV_FILE'])
                ? trim((string) $_SERVER['APP_ENV_FILE'])
                : trim((string) ($_ENV['APP_ENV_FILE'] ?? '')));

        $resolvedEnvFilePath = $envFileFromServer !== ''
            ? (str_starts_with($envFileFromServer, '/')
                ? $envFileFromServer
                : $this->projectRoot . '/' . ltrim($envFileFromServer, '/'))
            : null;

        return [
            'app_env' => trim((string) ($_ENV['APP_ENV'] ?? '')),
            'app_env_file_getenv' => $appEnvFileFromGetenv !== false ? trim((string) $appEnvFileFromGetenv) : null,
            'app_env_file_server' => isset($_SERVER['APP_ENV_FILE']) ? trim((string) $_SERVER['APP_ENV_FILE']) : null,
            'app_env_file_env' => isset($_ENV['APP_ENV_FILE']) ? trim((string) $_ENV['APP_ENV_FILE']) : null,
            'bootstrap_resolved_env_file' => $resolvedEnvFilePath,
            'bootstrap_resolved_env_file_exists' => $resolvedEnvFilePath !== null && is_file($resolvedEnvFilePath),
            'project_env_path' => $this->projectRoot . '/.env',
            'project_env_exists' => is_file($this->projectRoot . '/.env'),
        ];
    }

    /**
     * @param array{label: string, directory: string, public_prefix: string|null} $candidate
     * @return array<string, mixed>
     */
    private function describeCandidate(array $candidate): array
    {
        return [
            'label' => $candidate['label'],
            'directory' => $candidate['directory'],
            'public_prefix' => $candidate['public_prefix'],
        ] + $this->describePath($candidate['directory'], true);
    }

    /**
     * @param array<int, array{label: string, directory: string, public_prefix: string|null}> $candidates
     * @return array<int, array{label: string, directory: string, public_prefix: string|null}>
     */
    private function uniqueCandidates(array $candidates): array
    {
        $unique = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $hash = $candidate['directory'] . '|' . (string) ($candidate['public_prefix'] ?? '');
            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;
            $unique[] = $candidate;
        }

        return $unique;
    }

    /**
     * @return array<string, mixed>
     */
    private function describePath(?string $path, bool $directory = false): array
    {
        $normalizedPath = trim((string) $path);

        if ($normalizedPath === '') {
            return [
                'path' => '',
                'exists' => false,
                'readable' => false,
                'writable' => false,
                'permissions' => null,
            ];
        }

        $exists = $directory ? is_dir($normalizedPath) : file_exists($normalizedPath);
        $readable = $exists && is_readable($normalizedPath);
        $writable = $exists && is_writable($normalizedPath);

        return [
            'path' => $normalizedPath,
            'exists' => $exists,
            'readable' => $readable,
            'writable' => $writable,
            'permissions' => $exists ? substr(sprintf('%o', (int) @fileperms($normalizedPath)), -4) : null,
        ];
    }

    private function resolveManagedDirectory(string $path, ?string $managedRoot): string
    {
        $normalizedPath = str_replace('\\', '/', trim($path));
        while (str_starts_with($normalizedPath, './')) {
            $normalizedPath = substr($normalizedPath, 2);
        }

        $storagePrefix = 'var/storage/';
        $normalizedRelativePath = ltrim($normalizedPath, '/');

        if (
            $managedRoot !== null
            && !$this->isAbsolutePath($normalizedPath)
            && str_starts_with($normalizedRelativePath, $storagePrefix)
        ) {
            $storageSuffix = ltrim(substr($normalizedRelativePath, strlen($storagePrefix)), '/');

            return rtrim($managedRoot, '/') . '/' . $storageSuffix;
        }

        return $this->resolveProjectPath($normalizedPath);
    }

    private function resolveProjectPath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', trim($path));

        if ($this->isAbsolutePath($normalizedPath)) {
            return rtrim($normalizedPath, '/');
        }

        return $this->projectRoot . '/' . ltrim($normalizedPath, '/');
    }

    private function normalizePrefix(string $prefix): string
    {
        return trim(str_replace('\\', '/', $prefix), '/');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
