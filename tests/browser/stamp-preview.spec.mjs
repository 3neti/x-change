import { createRequire } from 'node:module';
const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

// Synthetic page bootstrap; the preview POST reaches the real isolated local backend.
// All other POSTs are intercepted so these checks cannot execute financial actions.
for (const surface of ['public', 'cockpit']) {
    for (const width of [390, 1440]) {
        test(`canonical ${surface} Stamp renders and updates at ${width}px in both themes`, async ({ page }) => {
            const base = process.env.WORKFLOW_BROWSER_URL;
            if (!base || !new URL(base).hostname.endsWith('.test') || !base.startsWith('https://')) {
                throw new Error('An isolated local HTTPS host is required.');
            }
            await page.setViewportSize({ width, height: 844 });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/login', async route => {
                const response = await route.fetch();
                let html = await response.text();
                const pattern = /(<script[^>]*data-page="app"[^>]*>)([\s\S]*?)(<\/script>)/;
                const match = html.match(pattern);
                if (!match) throw new Error('Expected Inertia bootstrap.');
                const original = JSON.parse(match[2]);
                const fixture = { ...original, component: `x-change/${surface === 'public' ? 'public/AutoGenerate' : 'cockpit/QuickGenerate'}`, props: {
                    ...original.props,
                    xchange: { ...original.props.xchange, stamp_default: 'steampunk', stamps: [
                        { id: 'steampunk', revision: 8, name: 'Steampunk', customization: { controls: ['qr', 'tagline'], defaults: {} } },
                        { id: 'cats', revision: 7, name: 'Cats', customization: { controls: ['qr', 'tagline'], defaults: {} } },
                    ] },
                    auth: { user: { id: 0, name: 'Preview fixture', email: 'fixture@example.test' } },
                    ...(surface === 'public' ? { surface_profile: { kind: 'public_auto_generate' } } : {}),
                    public_prefill: { amount: 500, currency: 'PHP' },
                    initial_workspace: 'composer',
                    quick_generate_read_model: { status: 'available', authorized: true, mutation_contract: { runtime_enabled: false, route_url: null, allowed_methods: [] } },
                }};
                html = html.replace(pattern, (_, start, json, end) => `${start}${JSON.stringify(fixture)}${end}`);
                return route.fulfill({ response, body: html });
            });
            await page.route('**/x/**', route => {
                if (route.request().method() === 'POST' && !route.request().url().endsWith('/x/stamps/preview')) {
                    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ data: {} }) });
                }
                return route.continue();
            });
            await page.goto(`${base}/login`);
            const image = page.locator('[data-testid="canonical-stamp-preview"] img');
            await expect(image).toBeVisible();
            await expect.poll(() => image.evaluate(element => [element.naturalWidth, element.naturalHeight])).toEqual([1200, 630]);
            const before = await image.getAttribute('src');
            await page.emulateMedia({ colorScheme: 'dark' });
            await expect(image).toHaveAttribute('src', before);
            await page.emulateMedia({ colorScheme: 'light' });
            await expect(image).toHaveAttribute('src', before);
            await page.getByTestId('cockpit-quick-generate-primary-purpose').fill('A different meaning');
            await expect.poll(() => image.getAttribute('src')).not.toBe(before);
            await expect(page.locator('[data-testid="canonical-stamp-preview"] iframe')).toHaveCount(0);
            await image.screenshot({ path: test.info().outputPath(`stamp-${surface}-${width}.png`) });
            expect(errors).toEqual([]);
        });
    }
}
