import { createRequire } from 'node:module';
const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

test('authorized local operator creates AUI and BST drafts without publication', async ({ page }) => {
    const base = process.env.WORKFLOW_BROWSER_URL;
    const owner = process.env.WORKFLOW_BROWSER_OWNER;
    if (!base || !new URL(base).hostname.endsWith('.test') || !/^\d+$/.test(owner ?? '')) {
        throw new Error('Explicit local host and synthetic operator are required.');
    }
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`${base}/_dusk/login/${owner}`);
    await page.goto(`${base}/x/cockpit/campaigns`);
    await page.getByRole('button', { name: 'Endpoints', exact: true }).click();
    for (const [service, workflow, entry] of [
        ['aui-demo', 'aui.personal-accident.provisional-cover@1.0.0', 'payment_qr'],
        ['philhealth-demo', 'philhealth.bst.demo@1.0.0', 'public_endpoint'],
    ]) {
        await page.getByTestId('campaign-workflow-draft-open').click();
        await page.getByTestId('workflow-draft-name').fill(`Browser ${service} draft`);
        await page.getByTestId('workflow-draft-template').selectOption({ label: 'Workflow browser settlement fixture' });
        await page.getByTestId('workflow-draft-service').selectOption(service);
        await page.getByTestId('workflow-draft-workflow').selectOption(workflow);
        if (service === 'aui-demo') await page.getByTestId('workflow-draft-plan').selectOption('PA5000_DAY@1');
        await page.getByTestId('workflow-draft-entry').selectOption(entry);
        await expect(page.getByTestId('workflow-draft-save')).toBeEnabled();
        await page.screenshot({ path: `${process.env.WORKFLOW_BROWSER_ARTIFACTS}/${service}-draft.png`, fullPage: true });
        await page.setViewportSize({ width: 375, height: 812 });
        await expect(page.getByTestId('workflow-draft-save')).toBeVisible();
        expect(await page.locator('html').evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
        await page.screenshot({ path: `${process.env.WORKFLOW_BROWSER_ARTIFACTS}/${service}-mobile-draft.png`, fullPage: true });
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.getByTestId('workflow-draft-save').click();
        await expect(page.getByTestId('campaign-workflow-draft-dialog')).not.toBeVisible();
        await expect(page.getByTestId('campaign-workflow-drafts')).toContainText(`Browser ${service} draft`);
    }
    expect(errors).toEqual([]);
});

test('publishes only the prepared AUI demo without provisioning a provider QR', async ({ page }) => {
    test.skip(process.env.WORKFLOW_BROWSER_PUBLISH !== '1', 'Explicit local publication test opt-in required.');
    const base = process.env.WORKFLOW_BROWSER_URL;
    if (!base || !new URL(base).hostname.endsWith('.test')) throw new Error('Local host required.');
    await page.goto(`${base}/_dusk/login/${process.env.WORKFLOW_BROWSER_OWNER}`);
    await page.goto(`${base}/x/cockpit/campaigns`);
    await page.getByRole('button', { name: 'Endpoints', exact: true }).click();
    const drafts = page.getByTestId('campaign-workflow-drafts');
    const bst = drafts.locator('li').filter({ hasText: 'Browser philhealth-demo draft' }).first();
    await expect(bst.getByTestId('workflow-draft-publish-open')).toHaveCount(0);
    const aui = drafts.locator('li').filter({ hasText: 'Browser aui-demo draft' }).first();
    await aui.getByTestId('workflow-draft-publish-open').click();
    await page.getByTestId('workflow-draft-publish-confirm').click();
    await expect(page.getByTestId('workflow-draft-publish-confirm')).not.toBeVisible();
    await expect(page.getByTestId('campaign-endpoint-canvas')).toContainText('Browser aui-demo draft');
    await page.screenshot({ path: `${process.env.WORKFLOW_BROWSER_ARTIFACTS}/published-demo.png`, fullPage: true });
});
