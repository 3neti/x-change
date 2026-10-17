import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitQuickGenerateSurfaceSwitch from '../../../resources/js/cockpit/components/CockpitQuickGenerateSurfaceSwitch.vue';

describe('Cockpit quick generate surface switch', () => {
    it('limits the public workspace switch to Composer and LLM', () => {
        const wrapper = mount(CockpitQuickGenerateSurfaceSwitch, {
            props: {
                modelValue: 'composer',
                surfaces: ['composer', 'llm'],
            },
        });

        expect(wrapper.findAll('button')).toHaveLength(2);
        expect(wrapper.get('[data-testid="cockpit-quick-generate-surface-composer"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="cockpit-quick-generate-surface-llm"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-surface-pos"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-surface-qr"]').exists()).toBe(false);
    });

    it('keeps all workspaces when no restriction is supplied', () => {
        const wrapper = mount(CockpitQuickGenerateSurfaceSwitch, {
            props: { modelValue: 'composer' },
        });

        expect(wrapper.findAll('button')).toHaveLength(4);
    });
});
