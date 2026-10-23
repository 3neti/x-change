import { createRequire } from 'node:module';

const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

for (const width of [390, 1440]) {
    test(`keyboard scanner and holder-approved collection at ${width}px`, async ({ page }) => {
        const base = process.env.WORKFLOW_BROWSER_URL;
        if (!base || !new URL(base).hostname.endsWith('.test') && new URL(base).hostname !== '127.0.0.1') {
            throw new Error('An isolated local browser host is required.');
        }
        await page.setViewportSize({ width, height: 900 });
        const pageErrors = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        const requests = [];
        await page.route('**/x/cockpit/stored-value-collections**', async (route) => {
            const request = route.request();
            requests.push({ url: request.url(), body: request.postDataJSON() });
            const status = request.url().endsWith('/verification') ? 'verified'
                : request.url().endsWith('/commit') ? 'committed' : 'pending';
            await route.fulfill({ status: status === 'pending' ? 201 : 200, contentType: 'application/json', body: JSON.stringify({
                data: { reference: '01ARZ3NDEKTSV4RRFFQ69G5FAV', status, amount_minor: 2500, currency: 'PHP',
                    ...(status === 'verified' ? { available_minor: 10000 } : {}),
                    ...(status === 'committed' ? { balance_after_minor: 7500 } : {}),
                },
            }) });
        });
        await page.goto(base);
        await page.getByLabel('Pay Code').fill('ABCD-1234');
        await page.getByLabel('Amount to collect · PHP').fill('25.00');
        await page.getByLabel('Pay Code').press('Enter');
        await expect(page.getByLabel('Holder approval code')).toBeVisible();
        await expect(page.getByText('Available balance:', { exact: false })).toHaveCount(0);
        expect(requests[0].body).toEqual({ code: 'ABCD-1234', amount_minor: 2500, currency: 'PHP' });
        await page.getByLabel('Holder approval code').fill('000000');
        await page.getByRole('button', { name: 'Verify holder' }).click();
        await expect(page.getByText('Available balance:', { exact: false })).toBeVisible();
        expect(requests).toHaveLength(2);
        await page.getByRole('button', { name: 'Collect ₱25.00' }).click();
        await expect(page.getByText('Payment collected')).toBeVisible();
        await expect(page.getByText('₱75.00')).toBeVisible();
        expect(requests).toHaveLength(3);
        expect(pageErrors).toEqual([]);
        await expect.poll(() => page.locator('html').evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
    });
}

test('camera OCR reads one complete marked text Pay Code', async ({ page }) => {
    test.setTimeout(120_000);
    await page.goto(process.env.WORKFLOW_BROWSER_URL);
    const base64 = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 1400;
        canvas.height = 240;
        const context = canvas.getContext('2d');
        context.fillStyle = '#fff';
        context.fillRect(0, 0, 1400, 240);
        context.fillStyle = '#000';
        context.font = 'bold 88px Arial';
        context.fillText('|| ABCD-1234 ||', 120, 160);
        return canvas.toDataURL('image/png').split(',')[1];
    });
    await page.locator('input[type=file]').setInputFiles({
        name: 'marked-pay-code.png', mimeType: 'image/png', buffer: Buffer.from(base64, 'base64'),
    });
    await expect(page.getByLabel('Pay Code')).toHaveValue('ABCD-1234', { timeout: 60_000 });
});
