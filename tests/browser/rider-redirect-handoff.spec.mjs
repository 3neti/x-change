import { createRequire } from 'node:module';
const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

// Synthetic server responses prove adapter navigation; Pest proves the real gate and lifecycle.
for (const width of [390, 1440]) {
    test(`pending rider completion follows a server location in the same tab at ${width}px`, async ({ page, context }) => {
        const base = process.env.WORKFLOW_BROWSER_URL;
        if (!base || !new URL(base).hostname.endsWith('.test')) throw new Error('Local host required.');
        await page.setViewportSize({ width, height: 844 });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        let reloads = 0;
        let posts = 0;
        await page.route('**/*', async route => {
            const request = route.request();
            const url = new URL(request.url());
            if (url.origin !== base) return route.abort();
            if (request.method() !== 'GET') { posts++; return route.abort(); }
            if (url.pathname === '/synthetic-rider-landing') {
                return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Controlled landing</title><h1>Landing ready</h1>' });
            }
            if (url.pathname !== '/login') return route.continue();
            if (request.headers()['x-inertia']) {
                reloads++;
                return route.fulfill({ status: 409, headers: { 'X-Inertia-Location': `${base}/synthetic-rider-landing` }, body: '' });
            }
            const response = await route.fetch();
            let html = await response.text();
            const pattern = /(<script[^>]*data-page="app"[^>]*>)([\s\S]*?)(<\/script>)/;
            const match = html.match(pattern);
            if (!match) throw new Error('Expected Inertia page script.');
            const original = JSON.parse(match[2]);
            const fixture = { ...original, component: 'x-change/claim/Success', props: {
                ...original.props, voucher: { code: 'SYNTHETIC-RIDER', amount: 100, currency: 'PHP' },
                claimOutcome: 'accepted_pending', rider_handoff: { pending: true },
                rider: null, redirectEndpoint: null, redirect: null, claim_experience: null,
                success_action: null, success_presentation: null,
            }};
            html = html.replace(pattern, (_, start, json, end) => `${start}${JSON.stringify(fixture)}${end}`);
            return route.fulfill({ response, body: html });
        });
        await page.goto(`${base}/login`);
        await expect(page.getByText('Checking payment status…')).toBeVisible();
        await expect(page).toHaveURL(`${base}/synthetic-rider-landing`, { timeout: 15000 });
        await expect(page.getByRole('heading', { name: 'Landing ready' })).toBeVisible();
        expect(context.pages()).toHaveLength(1);
        expect(posts).toBe(0);
        expect(reloads).toBe(1);
        expect(errors).toEqual([]);
    });
}
