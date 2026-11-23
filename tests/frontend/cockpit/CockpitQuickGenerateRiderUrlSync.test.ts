import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: {} }),
    Link: { props: ['href'], template: '<a :href="href?.url ?? href"><slot /></a>' },
    router: { patch: vi.fn(), post: vi.fn(), reload: vi.fn() },
}));

enableAutoUnmount(afterEach);
afterEach(() => vi.unstubAllGlobals());

const originalUrl = 'https://open.spotify.com/track/4MBaQPdgBWLQVYmwaCxwbT?si=a0eb30bc81f2486c';

describe('Purpose Rider editor URL synchronization', () => {
    it.each([
        ['replace', 'https://example.test/updated?source=rider&step=2'],
        ['clear', ''],
        ['paste with surrounding whitespace', `  ${originalUrl}  `],
    ])('keeps the preview and submitted URL synchronized when users %s a template URL', async (_action, editedUrl) => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ status: 'issued', result: { code: 'TEST' } }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            attachTo: document.body,
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
                savedTemplates: [{
                    reference: '01TEMPLATE',
                    name: 'Double A',
                    base_template_key: 'blank-pay-code',
                    include_amount: true,
                    include_purpose: true,
                    instructions: {
                        cash: { amount: 5370, currency: 'PHP', validation: {} },
                        rider: { message: 'Parking', url: originalUrl },
                    },
                }],
            },
            global: { stubs: { Teleport: true, CockpitPayCodeCanvas: true } },
        });
        const preview = () => JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text());
        const openLink = async () => {
            await wrapper.get('[data-testid="open-rider-editor"]').trigger('click');
            await wrapper.get('[data-testid="rider-editor"]').findAll('[role="tab"]').find(tab => tab.text() === 'Link')!.trigger('mousedown', { button: 0 });
        };
        const closeRider = async () => {
            const dialog = wrapper.findAll('[role="dialog"]').find(item => item.find('[data-testid="rider-editor"]').exists())!;
            await dialog.findAll('button').find(button => button.text() === 'Done')!.trigger('click');
        };

        await wrapper.get('[data-testid="open-templates-editor"]').trigger('click');
        await wrapper.get('[data-testid="cockpit-quick-generate-choose-template"]').trigger('click');
        await wrapper.get('[data-testid="cockpit-quick-generate-saved-template-option"]').trigger('click');
        expect(preview().rider.url).toBe(originalUrl);

        await openLink();
        await wrapper.get('[data-testid="rider-editor-url"]').setValue(editedUrl);
        await closeRider();
        await wrapper.get('[data-testid="cockpit-quick-generate-primary-purpose"]').setValue('Updated parking');
        const expectedUrl = editedUrl.trim() || null;
        expect(preview().rider.url).toBe(expectedUrl);
        expect(preview().rider.message).toBe('Updated parking');

        await openLink();
        expect(wrapper.get<HTMLInputElement>('[data-testid="rider-editor-url"]').element.value.trim()).toBe(editedUrl.trim());
        await wrapper.get('[data-testid="rider-editor"]').findAll('[role="tab"]').find(tab => tab.text() === 'Message')!.trigger('mousedown', { button: 0 });
        await closeRider();

        expect(wrapper.get<HTMLButtonElement>('[data-testid="cockpit-quick-generate-submit-button"]').element.disabled).toBe(false);
        await wrapper.get('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');
        await flushPromises();

        const issuanceCalls = fetchMock.mock.calls.filter(([url]) => url === '/x/cockpit/quick-generate');
        expect(issuanceCalls).toHaveLength(1);
        const payload = JSON.parse(issuanceCalls[0][1].body);
        expect(payload.rider.url).toBe(expectedUrl);
        expect(payload.rider.message).toBe('Updated parking');
        expect(payload.cash.amount).toBe(5370);
    });
});
