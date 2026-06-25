<?php

declare(strict_types=1);

namespace App\Support;

final class ThemeConfig
{
    /**
     * @var array<string, array{label: string, aria_label: string, swatch_dot_class?: string}>
     */
    private const THEME_DEFINITIONS = [
        'amber' => [
            'label' => 'Amber',
            'aria_label' => 'Ativar tema âmbar',
            'swatch_dot_class' => 'nc-dot-amber',
        ],
        'blue' => [
            'label' => 'Blue',
            'aria_label' => 'Ativar tema azul',
            'swatch_dot_class' => 'nc-dot-blue',
        ],
        'green' => [
            'label' => 'Green',
            'aria_label' => 'Ativar tema verde',
            'swatch_dot_class' => 'nc-dot-green',
        ],
        'red' => [
            'label' => 'Red',
            'aria_label' => 'Ativar tema vermelho',
            'swatch_dot_class' => 'nc-dot-red',
        ],
        'violet' => [
            'label' => 'Violet',
            'aria_label' => 'Ativar tema violeta',
            'swatch_dot_class' => 'nc-dot-violet',
        ],
    ];

    /**
     * @var array<string, array{label: string, aria_label: string}>
     */
    private const MODE_DEFINITIONS = [
        'light' => [
            'label' => 'Light',
            'aria_label' => 'Ativar modo claro',
        ],
        'dark' => [
            'label' => 'Dark',
            'aria_label' => 'Ativar modo escuro',
        ],
    ];

    /**
     * @var array<string, array{label: string, aria_label: string}>
     */
    private const DARK_INTENSITY_DEFINITIONS = [
        'neutral' => [
            'label' => 'Neutral',
            'aria_label' => 'Ativar escuro neutro',
        ],
        'vivid' => [
            'label' => 'Vivid',
            'aria_label' => 'Ativar escuro vívido',
        ],
    ];

    /**
     * @param array<string, mixed> $env
     * @return array{
     *     default_theme: string,
     *     default_mode: string,
     *     default_dark_intensity: string,
     *     theme_palette_enabled: bool,
     *     dashboard_theme_palette_enabled: bool,
     *     allowed_themes: list<string>,
     *     allowed_modes: list<string>,
     *     allowed_dark_intensities: list<string>,
     *     theme_options: list<array{value: string, label: string, aria_label: string, swatch_dot_class?: string}>,
     *     mode_options: list<array{value: string, label: string, aria_label: string}>,
     *     dark_intensity_options: list<array{value: string, label: string, aria_label: string}>
     * }
     */
    public static function resolve(array $env): array
    {
        $allowedThemes = self::resolveAllowedValues(
            (string) ($env['APP_THEME_ALLOWED_THEMES'] ?? ''),
            array_keys(self::THEME_DEFINITIONS)
        );
        $allowedModes = self::resolveAllowedValues(
            (string) ($env['APP_THEME_ALLOWED_MODES'] ?? ''),
            array_keys(self::MODE_DEFINITIONS)
        );
        $allowedDarkIntensities = self::resolveAllowedValues(
            (string) ($env['APP_THEME_ALLOWED_DARK_INTENSITIES'] ?? ''),
            array_keys(self::DARK_INTENSITY_DEFINITIONS)
        );

        $defaultTheme = self::resolveDefault(
            (string) ($env['APP_DEFAULT_THEME'] ?? ''),
            $allowedThemes,
            'amber'
        );
        $defaultMode = self::resolveDefault(
            (string) ($env['APP_DEFAULT_MODE'] ?? ''),
            $allowedModes,
            'light'
        );
        $defaultDarkIntensity = self::resolveDefault(
            (string) ($env['APP_DEFAULT_DARK_INTENSITY'] ?? ''),
            $allowedDarkIntensities,
            'neutral'
        );

        $hasInteractiveControls = self::hasInteractiveControls(
            $allowedThemes,
            $allowedModes,
            $allowedDarkIntensities
        );

        return [
            'default_theme' => $defaultTheme,
            'default_mode' => $defaultMode,
            'default_dark_intensity' => $defaultDarkIntensity,
            'theme_palette_enabled' => self::resolveBoolean(
                (string) ($env['APP_ENABLE_THEME_PALETTE'] ?? 'false'),
                false
            ) && $hasInteractiveControls,
            'dashboard_theme_palette_enabled' => self::resolveBoolean(
                (string) ($env['APP_ENABLE_DASHBOARD_THEME_PALETTE'] ?? 'true'),
                true
            ) && $hasInteractiveControls,
            'allowed_themes' => $allowedThemes,
            'allowed_modes' => $allowedModes,
            'allowed_dark_intensities' => $allowedDarkIntensities,
            'theme_options' => self::buildOptions(self::THEME_DEFINITIONS, $allowedThemes),
            'mode_options' => self::buildOptions(self::MODE_DEFINITIONS, $allowedModes),
            'dark_intensity_options' => self::buildOptions(self::DARK_INTENSITY_DEFINITIONS, $allowedDarkIntensities),
        ];
    }

    /**
     * @param list<string> $allowedThemes
     * @param list<string> $allowedModes
     * @param list<string> $allowedDarkIntensities
     */
    public static function hasInteractiveControls(
        array $allowedThemes,
        array $allowedModes,
        array $allowedDarkIntensities
    ): bool {
        if (count($allowedThemes) > 1 || count($allowedModes) > 1) {
            return true;
        }

        return in_array('dark', $allowedModes, true) && count($allowedDarkIntensities) > 1;
    }

    /**
     * @param array<string, array<string, string>> $definitions
     * @param list<string> $allowedValues
     * @return list<array{value: string, label: string, aria_label: string, swatch_dot_class?: string}>
     */
    private static function buildOptions(array $definitions, array $allowedValues): array
    {
        $options = [];

        foreach ($allowedValues as $value) {
            if (!isset($definitions[$value])) {
                continue;
            }

            $options[] = ['value' => $value] + $definitions[$value];
        }

        return $options;
    }

    /**
     * @param list<string> $allowedValues
     */
    private static function resolveDefault(string $value, array $allowedValues, string $fallback): string
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, $allowedValues, true)) {
            return $normalized;
        }

        if (in_array($fallback, $allowedValues, true)) {
            return $fallback;
        }

        return $allowedValues[0] ?? $fallback;
    }

    /**
     * @param list<string> $supportedValues
     * @return list<string>
     */
    private static function resolveAllowedValues(string $value, array $supportedValues): array
    {
        $requestedValues = array_filter(
            array_map('trim', explode(',', strtolower($value))),
            static fn (string $item): bool => $item !== ''
        );

        $resolvedValues = [];

        foreach ($requestedValues as $requestedValue) {
            if (!in_array($requestedValue, $supportedValues, true) || isset($resolvedValues[$requestedValue])) {
                continue;
            }

            $resolvedValues[$requestedValue] = true;
        }

        return $resolvedValues !== []
            ? array_keys($resolvedValues)
            : $supportedValues;
    }

    private static function resolveBoolean(string $value, bool $fallback): bool
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return $fallback;
        }

        $resolved = filter_var($normalized, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? $fallback;
    }
}
