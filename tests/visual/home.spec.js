const { test, expect } = require('@playwright/test');

async function openHomeReady(page)
{
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

    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');
    await page.addStyleTag({
        content: `
            :root {
                --font-heading: "DejaVu Serif", Georgia, serif !important;
                --font-body: "DejaVu Sans", Arial, sans-serif !important;
                --font-mono: "DejaVu Sans Mono", Consolas, monospace !important;
            }
        `,
    });
    await page.locator('.nc-footer').waitFor();
    await page.waitForFunction(() => {
        const fontsStylesheet = document.querySelector('link[rel="stylesheet"][href*="fonts.googleapis.com"]');

        return !fontsStylesheet || fontsStylesheet.media === 'all';
    });
    await page.evaluate(async() => {
        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
        }

        const visibleImages = Array.from(document.images).filter((image) => {
            const rect = image.getBoundingClientRect();

            return rect.width > 0 && rect.height > 0;
        });

        await Promise.all(visibleImages.map(async(image) => {
            if (!image.complete) {
                await new Promise((resolve) => {
                    image.addEventListener('load', resolve, { once: true });
                    image.addEventListener('error', resolve, { once: true });
                });
            }

            if (typeof image.decode === 'function') {
                await image.decode().catch(() => {});
            }
        }));
    });
    await page.waitForLoadState('networkidle');
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
