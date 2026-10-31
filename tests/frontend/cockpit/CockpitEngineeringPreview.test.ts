import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    router: { reload: vi.fn() },
    usePage: () => ({ props: {} }),
}));

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

function panel(showEngineeringPreview = true) {
    return mount(CockpitQuickGenerateSubmitPanel, {
        props: { templates: cockpitQuickGenerateTemplates, showEngineeringPreview },
        global: { stubs: { Teleport: true, CockpitPayCodeCanvas: true } },
    });
}
function json(wrapper: ReturnType<typeof panel>): string {
    return wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text();
}

describe('Engineering Preview exports', () => {
    it('includes only a quote matching the current pricing request', async () => {
        vi.useFakeTimers();
        try {
            const hash = 'a'.repeat(64);
            vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({ success: true, data: { currency: 'PHP', total: 15, commercial_quote_reference: `commercial-quote:${hash}` } }),
            }));
            const wrapper = panel();
            await wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]').setValue('100');
            await vi.advanceTimersByTimeAsync(600);
            await flushPromises();
            expect(JSON.parse(json(wrapper)).pricing).toEqual({ quote_reference: `commercial-quote:${hash}`, quote_hash: hash, currency: 'PHP', fee_bearer: 'issuer' });
            await wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]').setValue('200');
            expect(JSON.parse(json(wrapper))).not.toHaveProperty('pricing');
        } finally {
            vi.useRealTimers();
        }
    });
    it('shows an explicit v2 execution for a new ordinary Pay Code', () => {
        const instructions = JSON.parse(json(panel()));
        expect(instructions.execution).toEqual({ schema: 'voucher.execution.v2', driver: 'default' });
        expect(instructions.claim.profile).toBe('voucher.claim.v1');
        expect(instructions.presentation.stamp).toMatchObject({
            design_id: instructions.rider.stamp.design_id,
            design_version: instructions.rider.stamp.design_version,
            show_logo: instructions.rider.stamp.show_logo,
            show_tagline: instructions.rider.stamp.show_tagline,
            claim_marker: instructions.rider.stamp.claim_marker,
        });
    });
    it('copies the current full JSON and keeps the preview collapsible', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        const wrapper = panel();
        await wrapper.get('[data-testid="cockpit-quick-generate-primary-recipient"]').setValue('"release-123"');
        const preview = wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview"]');
        expect(preview.element.tagName).toBe('DETAILS');
        expect(preview.attributes('open')).toBeUndefined();
        expect(JSON.parse(json(wrapper)).cash.validation.secret).toBe('release-123');
        await wrapper.get('[data-testid="engineering-preview-copy"]').trigger('click');
        await flushPromises();
        expect(writeText).toHaveBeenLastCalledWith(json(wrapper));
        expect(preview.text()).toContain('JSON copied.');
        await wrapper.get('[data-testid="cockpit-quick-generate-primary-recipient"]').setValue('"updated-456"');
        await wrapper.get('[data-testid="engineering-preview-copy"]').trigger('click');
        await flushPromises();
        expect(JSON.parse(writeText.mock.lastCall![0]).cash.validation.secret).toBe('updated-456');
        expect(wrapper.findComponent({ name: 'CockpitPayCodeCanvas' }).props('stampInstructions').cash.validation.secret).toBe('[redacted secret]');
    });

    it('downloads exactly the displayed JSON as a JSON file', async () => {
        const createObjectURL = vi.fn((_blob: Blob) => 'blob:engineering-preview');
        const revokeObjectURL = vi.fn();
        vi.stubGlobal('URL', Object.assign(class extends URL {}, { createObjectURL, revokeObjectURL }));
        let download = '';
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
            download = this.download;
            expect(this.href).toBe('blob:engineering-preview');
        });
        const wrapper = panel();
        await wrapper.get('[data-testid="cockpit-quick-generate-primary-recipient"]').setValue('"full-value"');
        await wrapper.get('[data-testid="engineering-preview-download"]').trigger('click');
        const blob = createObjectURL.mock.calls[0][0] as Blob;
        const contents = await new Promise<string>((resolve) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result as string);
            reader.readAsText(blob);
        });
        expect(contents).toBe(json(wrapper));
        expect(blob.type).toBe('application/json;charset=utf-8');
        expect(download).toBe('pay-code-instructions.json');
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:engineering-preview');
        expect(wrapper.get('[data-testid="engineering-preview-download"]').attributes('type')).toBe('button');
    });

    it('reports a failed clipboard write without changing the JSON', async () => {
        vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('denied')) } });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        const wrapper = panel();
        const before = json(wrapper);
        await wrapper.get('[data-testid="engineering-preview-copy"]').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Could not copy. Use Download JSON instead.');
        expect(json(wrapper)).toBe(before);
    });

    it('hides the preview and its export buttons when disabled', () => {
        const wrapper = panel(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-engineering-preview"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="engineering-preview-copy"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="engineering-preview-download"]').exists()).toBe(false);
    });
});
