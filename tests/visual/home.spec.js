const { test, expect } = require('@playwright/test');

test.describe('Home visual regression', () => {
    test('home top fold', async({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        await expect(page).toHaveScreenshot('home-top.png', {
            fullPage: false,
            animations: 'disabled',
        });
    });

  test('home full page', async({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        await expect(page).toHaveScreenshot('home-full.png', {
            fullPage: true,
            animations: 'disabled',
        });
    });
});
