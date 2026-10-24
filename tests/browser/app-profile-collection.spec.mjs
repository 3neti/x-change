import { createRequire } from 'node:module';

const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

for (const width of [390, 1440]) {
    test(`collector activity and exception recovery at ${width}px`, async ({ page }) => {
        const base = process.env.WORKFLOW_BROWSER_URL;
        if (!base || !['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) {
            throw new Error('An isolated local browser harness is required.');
        }
        const pageErrors = [];
        const consoleWarnings = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        page.on('console', (message) => {
            if (['error', 'warning'].includes(message.type())) consoleWarnings.push(message.text());
        });
        await page.setViewportSize({ width, height: 900 });
        await page.route('**/x/cockpit/stored-value-collections/01ARZ3NDEKTSV4RRFFQ69G5FAV', async (route) => {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
                reference: '01ARZ3NDEKTSV4RRFFQ69G5FAV', status: 'ready', amount_minor: 2500,
                currency: 'PHP', available_minor: 10000,
            } }) });
        });

        await page.goto(`${base}?view=activity`);
        await expect(page.getByRole('heading', { name: 'Collection activity' })).toBeVisible();
        await expect(page.getByText('₱25.00 · committed')).toBeVisible();
        await page.goto(`${base}?view=exceptions`);
        await expect(page.getByRole('heading', { name: 'Unfinished collections' })).toBeVisible();
        await page.getByRole('button', { name: 'Open' }).click();
        await expect(page.getByRole('button', { name: 'Collect ₱25.00' })).toBeVisible();
        expect(pageErrors).toEqual([]);
        expect(consoleWarnings).toEqual([]);
        await expect.poll(() => page.locator('html').evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
    });
}
