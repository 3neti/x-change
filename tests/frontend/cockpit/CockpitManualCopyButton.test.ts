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
