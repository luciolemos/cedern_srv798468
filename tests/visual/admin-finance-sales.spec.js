const { test, expect } = require('@playwright/test');

async function openFinancePreview(page)
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

    await page.goto('/health/preview/admin-finance-sales');
    await page.waitForLoadState('domcontentloaded');
    await page.addStyleTag({
        content: `
            :root {
                --font-heading: "DejaVu Serif", Georgia, serif !important;
                --font-body: "DejaVu Sans", Arial, sans-serif !important;
                --font-mono: "DejaVu Sans Mono", Consolas, monospace !important;
            }

            /* Keep the toolbar capture deterministic across viewports. */
            [data-aos] {
                opacity: 1 !important;
                transform: none !important;
                transition: none !important;
                animation: none !important;
            }

            .nc-utility-stack {
                display: none !important;
            }
        `,
    });
    await page.locator('.nc-admin-table-search').waitFor();
    await page.evaluate(async() => {
        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
        }
    });
    await page.waitForLoadState('networkidle');
}

test.describe('Admin finance sales visual regression', () => {
    test('finance filter toolbar keeps the livraria styling', async({ page }) => {
        const consoleErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        await openFinancePreview(page);

        await expect(page).toHaveURL(/\/health\/preview\/admin-finance-sales$/);
        await expect(page.getByRole('heading', { name: 'Vendas e cancelamentos' })).toBeVisible();
        await expect(page.locator('input[name="date_from"]')).toHaveValue('2026-06-01');
        await expect(page.locator('input[name="amount_min"]')).toHaveValue('40.00');

        await page.selectOption('select[name="status_filter"]', 'cancelled');
        await expect(page.locator('select[name="status_filter"]')).toHaveValue('cancelled');

        await page.locator('input[name="amount_max"]').fill('500');
        await expect(page.locator('input[name="amount_max"]')).toHaveValue('500');

        await expect(page.locator('.nc-finance-filter-controls')).toHaveScreenshot('admin-finance-filters.png', {
            animations: 'disabled',
        });

        expect(consoleErrors).toEqual([]);
    });
});
