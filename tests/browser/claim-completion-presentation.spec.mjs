import { createRequire } from 'node:module';
const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

// Render the installed host assets with synthetic Inertia props. Persisted outcome
// and receipt authorization are covered by Pest, not simulated by this UI check.
for (const width of [375, 1440]) {
    for (const state of ['processing', 'ready', 'ready-without-receipt', 'payment-unverified']) {
        test(`completion ${state} at ${width}px never asks for another payment`, async ({ page }) => {
            const base = process.env.WORKFLOW_BROWSER_URL;
            if (!base || !new URL(base).hostname.endsWith('.test')) throw new Error('Local host required.');
            await page.setViewportSize({ width, height: 900 });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            const ready = state.startsWith('ready');
            const title = ready ? 'Policy result ready' : state === 'processing' ? 'Details submitted' : 'Payment verification unavailable';
            await page.route('**/*', async route => {
                const request = route.request();
                if (new URL(request.url()).origin !== base || request.method() !== 'GET') return route.abort();
                if (new URL(request.url()).pathname !== '/login') return route.continue();
                const response = await route.fetch();
                let html = await response.text();
                const pattern = /(<script[^>]*data-page="app"[^>]*>)([\s\S]*?)(<\/script>)/;
                const match = html.match(pattern);
                if (!match) throw new Error('Expected Inertia JSON page script.');
                const original = JSON.parse(match[2]);
                const fixture = {
                    ...original,
                    component: 'x-change/claim/Success',
                    props: {
                        ...original.props,
                        voucher: { code: 'SYNTHETIC-COMPLETION', amount: 0, currency: 'PHP' },
                        claimOutcome: 'accepted_success',
                        success_presentation: {
                            title, suppress_legacy_rider: true,
                            body: state === 'payment-unverified'
                                ? 'We cannot verify the payment linked to this details request. Do not pay again.'
                                : ready ? 'Your payment has been received. A demonstration result is not actual insurance coverage.'
                                    : 'Your payment has been received. Your policy result is being prepared.',
                        },
                        rider: {
                            success: { enabled: true, type: 'text', content: 'Continue to payment to pay the premium.' },
                            redirect: { enabled: true, url: `${base}/x/pay/SYNTHETIC`, timeout: 1 },
                            stages: { stages: [] },
                        },
                        success_action: state === 'ready' ? {
                            key: 'x-change.claim-success.view-demo-policy', label: 'View demo policy', enabled: true,
                            intent: 'demo_policy_summary',
                            target: { url: `${base}/demo-policy/synthetic?signature=test-only`, redirectable: false },
                        } : null,
                    },
                };
                html = html.replace(pattern, (_, start, ignored, end) => `${start}${JSON.stringify(fixture).replaceAll('<', '\\u003c')}${end}`);
                await route.fulfill({ response, body: html });
            });
            await page.goto(`${base}/login`);
            await expect(page.getByText(title, { exact: true })).toBeVisible();
            await expect(page.getByText('Continue to payment', { exact: false })).toHaveCount(0);
            await expect(page.getByTestId('rider-countdown')).toHaveCount(0);
            await expect(page.getByTestId('rider-runtime')).toHaveCount(0);
            await expect(page.getByTestId('claim-success-primary-action')).toHaveCount(state === 'ready' ? 1 : 0);
            if (state === 'ready') await expect(page.getByRole('link', { name: 'View demo policy' })).toBeVisible();
            await expect.poll(() => page.locator('html').evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
            await page.screenshot({ path: `${process.env.WORKFLOW_BROWSER_ARTIFACTS}/${state}-${width}.png`, fullPage: true });
            expect(errors).toEqual([]);
        });
    }
}
