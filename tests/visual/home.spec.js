const { test, expect } = require('@playwright/test');

async function openHomeReady(page)
{
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');
    await page.locator('.nc-footer').waitFor();
}

test.describe('Home visual regression', () => {
    test('home top fold', async({ page }) => {
        await openHomeReady(page);

        await expect(page).toHaveScreenshot('home-top.png', {
            fullPage: false,
            animations: 'disabled',
        });
    });

    test('home full page', async({ page }) => {
        await openHomeReady(page);

        await expect(page).toHaveScreenshot('home-full.png', {
            fullPage: true,
            animations: 'disabled',
        });
    });
});
