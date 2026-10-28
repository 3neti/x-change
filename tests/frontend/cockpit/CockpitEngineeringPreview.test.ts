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
