<?php

declare(strict_types=1);

namespace App\Application\Security;

final class CsrfToken
{
    public const FIELD_NAME = '_csrf_token';
    private const SESSION_KEY = '_cedern_csrf_token';

    private function __construct()
    {
    }

    public static function get(): string
    {
        self::ensureSessionStarted();

        $token = $_SESSION[self::SESSION_KEY] ?? '';
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    public static function isValid(string $submittedToken): bool
    {
        self::ensureSessionStarted();

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '' || $submittedToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    public static function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }
    }
}
