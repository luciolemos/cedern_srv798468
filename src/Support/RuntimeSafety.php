<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ServerRequestInterface;

final class RuntimeSafety
{
    /**
     * @var list<string>
     */
    private const DEVELOPMENT_LIKE_ENVIRONMENTS = [
        'dev',
        'development',
        'local',
        'test',
        'testing',
        'qa',
        'homolog',
    ];

    /**
     * @param array<string, mixed> $env
     */
    public static function isDevelopmentLike(array $env = []): bool
    {
        $appEnv = strtolower(self::readString('APP_ENV', $env, 'production'));

        return in_array($appEnv, self::DEVELOPMENT_LIKE_ENVIRONMENTS, true);
    }

    /**
     * @param array<string, mixed> $env
     */
    public static function diagnosticsFeatureEnabled(array $env = []): bool
    {
        if (self::isDevelopmentLike($env)) {
            return true;
        }

        if (self::readBool('APP_ENABLE_DIAGNOSTIC_ROUTES', $env, false)) {
            return true;
        }

        return self::readString('APP_DIAGNOSTIC_TOKEN', $env) !== '';
    }

    /**
     * @param array<string, mixed> $env
     */
    public static function diagnosticRequestAuthorized(ServerRequestInterface $request, array $env = []): bool
    {
        if (!self::diagnosticsFeatureEnabled($env)) {
            return false;
        }

        if (self::isDevelopmentLike($env)) {
            return true;
        }

        $configuredToken = self::readString('APP_DIAGNOSTIC_TOKEN', $env);
        if ($configuredToken === '') {
            return false;
        }

        $providedToken = trim($request->getHeaderLine('X-Diagnostic-Token'));
        if ($providedToken === '') {
            $providedToken = trim((string) ($request->getQueryParams()['token'] ?? ''));
        }

        return $providedToken !== '' && hash_equals($configuredToken, $providedToken);
    }

    /**
     * @param array<string, mixed> $env
     */
    public static function repositoryFallbackAllowed(array $env = []): bool
    {
        $explicitValue = self::readNullableBool('APP_ALLOW_REPOSITORY_FALLBACK', $env);
        if ($explicitValue !== null) {
            return $explicitValue;
        }

        return self::isDevelopmentLike($env);
    }

    /**
     * @param array<string, mixed> $env
     */
    public static function readString(string $key, array $env = [], string $default = ''): string
    {
        if (array_key_exists($key, $env)) {
            return trim((string) $env[$key]);
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

        return $default;
    }

    /**
     * @param array<string, mixed> $env
     */
    public static function readBool(string $key, array $env = [], bool $default = false): bool
    {
        $resolved = self::readNullableBool($key, $env);

        return $resolved ?? $default;
    }

    /**
     * @param array<string, mixed> $env
     */
    private static function readNullableBool(string $key, array $env = []): ?bool
    {
        $rawValue = self::readString($key, $env);
        if ($rawValue === '') {
            return null;
        }

        return filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
