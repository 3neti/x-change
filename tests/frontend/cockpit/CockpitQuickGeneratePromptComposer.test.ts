import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitQuickGeneratePromptComposer from '../../../resources/js/cockpit/components/CockpitQuickGeneratePromptComposer.vue';

describe('CockpitQuickGeneratePromptComposer', () => {
    it('interprets a prompt and emits the normalized draft without issuing', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                status: 'ready',
                instructions: {
                    cash: { amount: 500, currency: 'PHP' },
                    inputs: { fields: ['signature', 'selfie'] },
                },
                recognized: ['amount', 'signature', 'selfie'],
                unresolved: [],
                clarifications: [],
            }),
        }));

        const wrapper = mount(CockpitQuickGeneratePromptComposer, {
            props: { endpoint: '/x/cockpit/quick-generate/interpret' },
        });

        await wrapper.get('[data-testid="cockpit-quick-generate-prompt-input"]').setValue('500 pesos. Require signature and selfie.');
        await wrapper.get('[data-testid="cockpit-quick-generate-prompt-go"]').trigger('click');
        await flushPromises();

        expect(fetch).toHaveBeenCalledOnce();
        expect(wrapper.emitted('interpreted')?.[0]?.[0]).toMatchObject({
            cash: { amount: 500 },
            inputs: { fields: ['signature', 'selfie'] },
        });
        expect(wrapper.get('[data-testid="cockpit-quick-generate-prompt-result"]').text()).toContain('Draft applied');
    });
});
