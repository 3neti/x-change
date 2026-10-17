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
        await new Promise((resolve) => setTimeout(resolve, 500));
        await flushPromises();
        await wrapper.get('[data-testid="cockpit-quick-generate-prompt-go"]').trigger('click');
        await flushPromises();

        expect(fetch).toHaveBeenCalledOnce();
        expect(wrapper.emitted('interpreted')?.[0]?.[0]).toMatchObject({
            cash: { amount: 500 },
            inputs: { fields: ['signature', 'selfie'] },
        });
        expect(wrapper.get('[data-testid="cockpit-quick-generate-prompt-go"]').text()).toContain('₱500.00');
        expect(wrapper.get('[data-testid="cockpit-quick-generate-prompt-go"]').text()).toContain('✍️');
        expect(wrapper.get('[data-testid="cockpit-quick-generate-prompt-go"]').text()).toContain('📸');
        expect(wrapper.get('[data-testid="cockpit-quick-generate-prompt-result"]').text()).toContain('Draft applied');
    });

    it('keeps the action inactive when the interpreter cannot construct instructions', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                status: 'needs_clarification',
                instructions: null,
                clarifications: ['Please provide an amount.'],
            }),
        }));

        const wrapper = mount(CockpitQuickGeneratePromptComposer, {
            props: { endpoint: '/x/cockpit/quick-generate/interpret' },
        });

        await wrapper.get('[data-testid="cockpit-quick-generate-prompt-input"]').setValue('make it useful');
        await new Promise((resolve) => setTimeout(resolve, 500));
        await flushPromises();

        expect(wrapper.get('[data-testid="cockpit-quick-generate-prompt-go"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-testid="cockpit-quick-generate-prompt-result"]').text()).toContain('Please provide an amount.');
    });
});
