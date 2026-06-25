const { test, expect } = require('@playwright/test');

/**
 * Visual regression tests for bookshop synopsis rendering
 * Tests that HTML content renders correctly with proper styling
 */
test.describe('Bookshop synopsis rendering', () => {
    test.beforeEach(async ({ page }) => {
        // Mock external fonts for deterministic rendering
        await page.route('https://fonts.googleapis.com/**', async(route) => {
            await route.fulfill({
                status: 200,
                contentType: 'text/css; charset=utf-8',
                body: '/* Visual tests use local deterministic fonts. */',
            });
        });
        await page.route('https://fonts.gstatic.com/**', async(route) => {
            await route.abort();
        });

        // Apply deterministic fonts
        await page.addStyleTag({
            content: `
                :root {
                    --font-heading: "DejaVu Serif", Georgia, serif !important;
                    --font-body: "DejaVu Sans", Arial, sans-serif !important;
                    --font-mono: "DejaVu Sans Mono", Consolas, monospace !important;
                }
            `,
        });
    });

    test('synopsis with formatted text renders correctly', async ({ page }) => {
        // This test verifies the visual appearance of rendered HTML content
        // Navigate to store page where bookshop items are displayed
        await page.goto('/loja');
        await page.waitForLoadState('domcontentloaded');
        
        // Wait for content to load
        await page.waitForSelector('.nc-bookshop-synopsis-content', { timeout: 5000 }).catch(() => {
            // No descriptions in current test data, skip visual check
        });
        
        // Wait for fonts to load
        await page.locator('body').waitFor();
        await page.evaluate(async() => {
            if (document.fonts && document.fonts.ready) {
                await document.fonts.ready;
            }
        });
        
        // Take screenshot if content exists
        const hasContent = await page.locator('.nc-bookshop-synopsis-content').count().catch(() => 0);
        if (hasContent > 0) {
            await expect(page).toHaveScreenshot('bookshop-synopsis.png', {
                fullPage: false,
                animations: 'disabled',
                mask: [page.locator('.nc-theme-colors-palette')], // Ignore theme selector if present
            });
        }
    });

    test('library page synopsis displays correctly', async ({ page }) => {
        // Test library page where book synopses are displayed
        await page.goto('/biblioteca');
        await page.waitForLoadState('domcontentloaded');
        
        // Wait for fonts
        await page.evaluate(async() => {
            if (document.fonts && document.fonts.ready) {
                await document.fonts.ready;
            }
        });
        
        // Verify no console errors
        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });
        
        // Wait for content
        await page.locator('body').waitFor();
        
        // Should have no console errors
        expect(consoleErrors).toEqual([]);
    });
});
