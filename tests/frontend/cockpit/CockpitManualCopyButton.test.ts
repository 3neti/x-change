import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitManualCopyButton from '../../../resources/js/cockpit/components/CockpitManualCopyButton.vue';

describe('Cockpit manual copy button', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('copies the supplied URL through the browser clipboard without backend interaction', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText,
            },
        });
        vi.stubGlobal('fetch', vi.fn());

        const wrapper = mount(CockpitManualCopyButton, {
            props: {
                value: 'https://example.test/x/claim/PC-COPY-001/experience',
            },
        });

        await wrapper.find('[data-testid="cockpit-manual-copy-button"]').trigger('click');
        await Promise.resolve();

        expect(writeText).toHaveBeenCalledWith('https://example.test/x/claim/PC-COPY-001/experience');
        expect(globalThis.fetch).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="cockpit-manual-copy-button"]').text()).toContain('Copied');
        expect(wrapper.find('[data-testid="cockpit-manual-copy-status"]').text()).toContain('No delivery was sent');
    });

    it('does not use backend transport APIs while copying manually', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        const sendBeacon = vi.fn();
        const XMLHttpRequest = vi.fn();

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText,
            },
            sendBeacon,
        });
        vi.stubGlobal('fetch', vi.fn());
        vi.stubGlobal('XMLHttpRequest', XMLHttpRequest);

        const wrapper = mount(CockpitManualCopyButton, {
            props: {
                value: 'https://example.test/x/claim/PC-COPY-HARDENED/experience',
            },
        });

        await wrapper.find('[data-testid="cockpit-manual-copy-button"]').trigger('click');
        await Promise.resolve();

        expect(writeText).toHaveBeenCalledWith('https://example.test/x/claim/PC-COPY-HARDENED/experience');
        expect(globalThis.fetch).not.toHaveBeenCalled();
        expect(sendBeacon).not.toHaveBeenCalled();
        expect(XMLHttpRequest).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="cockpit-manual-copy-status"]').text()).toContain('Copied locally. No delivery was sent.');
    });

    it('shows unavailable state when clipboard access is missing', async () => {
        vi.stubGlobal('navigator', {});
        vi.stubGlobal('fetch', vi.fn());

        const wrapper = mount(CockpitManualCopyButton, {
            props: {
                value: 'https://example.test/x/claim/PC-COPY-002/experience',
            },
        });

        await wrapper.find('[data-testid="cockpit-manual-copy-button"]').trigger('click');
        await Promise.resolve();

        expect(globalThis.fetch).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="cockpit-manual-copy-status"]').text()).toContain('Copy unavailable');
    });

    it('shows failed state when clipboard write rejects without backend interaction', async () => {
        const writeText = vi.fn().mockRejectedValue(new Error('clipboard blocked'));

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText,
            },
        });
        vi.stubGlobal('fetch', vi.fn());

        const wrapper = mount(CockpitManualCopyButton, {
            props: {
                value: 'https://example.test/x/claim/PC-COPY-FAILED/experience',
            },
        });

        await wrapper.find('[data-testid="cockpit-manual-copy-button"]').trigger('click');
        await Promise.resolve();
        await Promise.resolve();

        expect(writeText).toHaveBeenCalledWith('https://example.test/x/claim/PC-COPY-FAILED/experience');
        expect(globalThis.fetch).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="cockpit-manual-copy-button"]').text()).toContain('Copy failed');
        expect(wrapper.find('[data-testid="cockpit-manual-copy-status"]').text()).toContain('No backend call was made');
    });

    it('disables copy when no value is available', () => {
        const wrapper = mount(CockpitManualCopyButton, {
            props: {
                value: null,
            },
        });

        expect(wrapper.find('[data-testid="cockpit-manual-copy-button"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-testid="cockpit-manual-copy-button"]').text()).toContain('Copy unavailable');
    });
});
