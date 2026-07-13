<?php

declare(strict_types=1);

namespace App\Support;

final class InstitutionalRole
{
    public const FIRST_SECRETARY = '1º Secretário';
    public const SECOND_SECRETARY = '2º Secretário';
    public const LEGACY_SECRETARY = 'Secretário';

    private const OPTIONS = [
        'Presidente CEDE',
        'Vice-presidente CEDE',
        self::FIRST_SECRETARY,
        self::SECOND_SECRETARY,
        'Diretor de Finanças',
        'Diretor de Eventos',
        'Diretor de Patrimônio',
        'Diretor de Estudos',
        'Diretor de Atendimento Fraterno',
        'Diretor de Comunicação',
        'Coordenador',
        'Coordenador(a) do Curso de Mediunidade',
        'Conselheiro',
    ];

    private const EXCLUSIVE_OPTIONS = [
        'Presidente CEDE',
        'Vice-presidente CEDE',
        self::FIRST_SECRETARY,
        self::SECOND_SECRETARY,
        'Diretor de Finanças',
        'Diretor de Eventos',
        'Diretor de Patrimônio',
        'Diretor de Estudos',
        'Diretor de Atendimento Fraterno',
        'Diretor de Comunicação',
    ];

    private const ALIASES = [
        self::LEGACY_SECRETARY => self::FIRST_SECRETARY,
    ];

    private function __construct()
    {
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return self::OPTIONS;
    }

    /**
     * @return array<int, string>
     */
    public static function exclusiveOptions(): array
    {
        return self::EXCLUSIVE_OPTIONS;
    }

    public static function normalize(?string $role): ?string
    {
        $normalizedRole = trim((string) preg_replace('/\s+/', ' ', trim((string) $role)));

        if ($normalizedRole === '') {
            return null;
        }

        return self::ALIASES[$normalizedRole] ?? $normalizedRole;
    }

    /**
     * @return array<int, string>
     */
    public static function equivalents(?string $role): array
    {
        $normalizedRole = self::normalize($role);

        if ($normalizedRole === null) {
            return [];
        }

        if ($normalizedRole === self::FIRST_SECRETARY) {
            return [
                self::FIRST_SECRETARY,
                self::LEGACY_SECRETARY,
            ];
        }

        return [$normalizedRole];
    }
}
