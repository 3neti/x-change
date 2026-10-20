import { createRequire } from 'node:module';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

test('compares native rendering profiles on an isolated local host', async ({ page }) => {
    test.setTimeout(120000);
    const base = process.env.WORKFLOW_BROWSER_URL;
    if (!base?.startsWith('https://') || !new URL(base).hostname.endsWith('.test')) throw new Error('Local HTTPS host required');
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/x/stamps/rendering-comparison`);
    await page.getByRole('button', { name: 'Compare renderers' }).click();
    await expect(page.locator('article')).toHaveCount(3, { timeout: 90000 });
    await expect(page.getByRole('alert')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Download PNG' })).toHaveCount(3);
    const dimensions = await page.locator('article img').evaluateAll(images => images.map(image => [image.naturalWidth, image.naturalHeight]));
    expect(dimensions).toEqual([[1200, 630], [1200, 630], [2400, 1260]]);
    const directory = process.env.WORKFLOW_BROWSER_ARTIFACTS;
    if (directory) {
        await mkdir(directory, { recursive: true });
        const images = await page.locator('article img').evaluateAll(images => images.map(image => image.src));
        for (const [index, name] of ['standard', 'browser', 'browser-hd'].entries()) await writeFile(`${directory}/${name}.png`, Buffer.from(images[index].split(',')[1], 'base64'));
        await writeFile(`${directory}/measurements.txt`, await page.locator('article').allTextContents().then(text => text.join('\n')));
        await page.screenshot({ path: `${directory}/comparison.png`, fullPage: true });
    }
    for (const width of [390, 1440]) {
        await page.setViewportSize({ width, height: 844 });
        await page.emulateMedia({ colorScheme: 'dark' });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    }
    expect(errors).toEqual([]);
});


test('renders the Planetary Rose HTML artwork through all three profiles', async ({ page }) => {
    test.setTimeout(120000);
    const base = process.env.WORKFLOW_BROWSER_URL;
    if (!base?.startsWith('https://') || !new URL(base).hostname.endsWith('.test')) throw new Error('Local HTTPS host required');
    await page.goto(`${base}/x/stamps/rendering-comparison`);
    await page.getByRole('textbox', { name: 'Optional rider splash HTML' }).fill(await readFile(new URL('../Fixtures/stamp-browser-splash.html', import.meta.url), 'utf8'));
    await page.getByRole('button', { name: 'Compare renderers' }).click();
    await expect(page.locator('article')).toHaveCount(3, { timeout: 90000 });
    await expect(page.getByRole('alert')).toHaveCount(0);
    const directory = process.env.WORKFLOW_BROWSER_ARTIFACTS;
    if (directory) {
        const images = await page.locator('article img').evaluateAll(images => images.map(image => image.src));
        for (const [index, name] of ['standard', 'browser', 'browser-hd'].entries()) await writeFile(`${directory}/splash-${name}.png`, Buffer.from(images[index].split(',')[1], 'base64'));
        await page.screenshot({ path: `${directory}/splash-comparison.png`, fullPage: true });
    }
});
