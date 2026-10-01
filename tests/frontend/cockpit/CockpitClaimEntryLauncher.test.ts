import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitClaimEntryLauncher from '../../../resources/js/cockpit/components/CockpitClaimEntryLauncher.vue';
import { openCockpitClaimEntryLauncher } from '../../../resources/js/cockpit/claimEntryLauncher';

const artifact = {
    kind: 'claim_entry' as const,
    destination: 'https://example.test/x/claim',
    image_data_uri: 'data:image/png;base64,cXItY29kZQ==',
    title: 'Enter Pay Code',
    description: 'Scan to open the Pay Code entry page.',
    identifier: null,
    center_mark: 'pay_code' as const,
};

afterEach(() => {
    document.body.style.overflow = '';
    vi.restoreAllMocks();
});

describe('Cockpit Claim entry launcher', () => {
    it('shows only the generic claim entry artifact and safe actions', async () => {
        const historyBack = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => undefined);
        const clipboardWrite = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: clipboardWrite },
        });
        const wrapper = mount(CockpitClaimEntryLauncher, {
            props: { artifact },
            global: { stubs: { Teleport: true } },
        });

        openCockpitClaimEntryLauncher();
        await wrapper.vm.$nextTick();

        expect(
            wrapper.get('[data-testid="cockpit-claim-entry-launcher"]'),
        ).toBeTruthy();
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-entry-qr"]')
                .attributes('data-kind'),
        ).toBe('claim_entry');
        expect(wrapper.text()).toContain('Show this QR to enter a Pay Code');
        expect(wrapper.text()).toContain(
            'It contains no account, campaign, or Pay Code details.',
        );
        expect(wrapper.text()).not.toContain('ABCD');
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-entry-open"]')
                .attributes('href'),
        ).toBe(artifact.destination);
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-entry-download"]')
                .attributes('download'),
        ).toBe('pay-code-claim-entry.png');

        await wrapper
            .get('[data-testid="cockpit-claim-entry-copy"]')
            .trigger('click');
        expect(clipboardWrite).toHaveBeenCalledWith(artifact.destination);
        expect(wrapper.text()).toContain('Copied');

        await wrapper
            .get('[data-testid="cockpit-claim-entry-close"]')
            .trigger('click');
        expect(historyBack).toHaveBeenCalledOnce();

        window.dispatchEvent(new PopStateEvent('popstate'));
        await wrapper.vm.$nextTick();
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-entry-launcher"]')
                .exists(),
        ).toBe(false);
    });

    it('does not open without a server-owned artifact', async () => {
        const wrapper = mount(CockpitClaimEntryLauncher, {
            props: { artifact: null },
            global: { stubs: { Teleport: true } },
        });

        openCockpitClaimEntryLauncher();
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .find('[data-testid="cockpit-claim-entry-launcher"]')
                .exists(),
        ).toBe(false);
    });
});
