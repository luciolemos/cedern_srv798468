<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ThemeConfig;
use PHPUnit\Framework\TestCase;

final class ThemeConfigTest extends TestCase
{
    public function testResolveUsesStableDefaults(): void
    {
        $config = ThemeConfig::resolve([]);

        $this->assertSame('amber', $config['default_theme']);
        $this->assertSame('light', $config['default_mode']);
        $this->assertSame('neutral', $config['default_dark_intensity']);
        $this->assertFalse($config['theme_palette_enabled']);
        $this->assertTrue($config['dashboard_theme_palette_enabled']);
        $this->assertSame(['amber', 'blue', 'green', 'red', 'violet'], $config['allowed_themes']);
        $this->assertSame(['light', 'dark'], $config['allowed_modes']);
        $this->assertSame(['neutral', 'vivid'], $config['allowed_dark_intensities']);
    }

    public function testResolveFiltersAllowedValuesAndFallsBackToAvailableDefaults(): void
    {
        $config = ThemeConfig::resolve([
            'APP_ENABLE_THEME_PALETTE' => 'true',
            'APP_ENABLE_DASHBOARD_THEME_PALETTE' => 'false',
            'APP_THEME_ALLOWED_THEMES' => 'green, amber, green, invalid',
            'APP_THEME_ALLOWED_MODES' => 'dark',
            'APP_THEME_ALLOWED_DARK_INTENSITIES' => 'vivid',
            'APP_DEFAULT_THEME' => 'blue',
            'APP_DEFAULT_MODE' => 'light',
            'APP_DEFAULT_DARK_INTENSITY' => 'neutral',
        ]);

        $this->assertSame(['green', 'amber'], $config['allowed_themes']);
        $this->assertSame(['dark'], $config['allowed_modes']);
        $this->assertSame(['vivid'], $config['allowed_dark_intensities']);
        $this->assertSame('amber', $config['default_theme']);
        $this->assertSame('dark', $config['default_mode']);
        $this->assertSame('vivid', $config['default_dark_intensity']);
        $this->assertTrue($config['theme_palette_enabled']);
        $this->assertFalse($config['dashboard_theme_palette_enabled']);
    }

    public function testResolveDisablesPaletteWhenThereIsNothingToSwitch(): void
    {
        $config = ThemeConfig::resolve([
            'APP_ENABLE_THEME_PALETTE' => 'true',
            'APP_ENABLE_DASHBOARD_THEME_PALETTE' => 'true',
            'APP_THEME_ALLOWED_THEMES' => 'amber',
            'APP_THEME_ALLOWED_MODES' => 'light',
            'APP_THEME_ALLOWED_DARK_INTENSITIES' => 'neutral',
        ]);

        $this->assertFalse($config['theme_palette_enabled']);
        $this->assertFalse($config['dashboard_theme_palette_enabled']);
    }
}
