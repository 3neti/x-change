import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import Presentation from '../../vendor/3neti/form-flow/stubs/resources/js/pages/form-flow/core/components/FormFlowPresentation.vue';
import { useFormFlowPresentation, type FormFlowPresentation } from '../../vendor/3neti/form-flow/stubs/resources/js/pages/form-flow/core/components/formFlowPresentation';

const policy: FormFlowPresentation = {
    schema: 'form-flow.presentation.v1', profile: 'guided-claim-v1',
    brand: { name: 'Pay Code', logo: '/logo.svg', placement: 'top_left', logo_height: 32, logo_max_width: 112 },
    layout: { density: 'compact', capture_surface: 'fill_available', scrolling: 'when_needed' },
    actions: { placement: 'viewport_bottom', primary_position: 'right', secondary_position: 'left', reserve_content_space: true, primary_label: null, secondary_label: null },
    progress: { show: true, current: 2, total: 4 }, diagnostics: { show_versions: false },
};

describe('inherited Form Flow presentation', () => {
    it('preserves legacy slot content without adding a screen or header', () => {
        const wrapper = mount(Presentation, { slots: { default: '<button>Continue</button>' } });
        expect(wrapper.find('button').text()).toBe('Continue');
        expect(wrapper.find('header').exists()).toBe(false);
    });
    it('shares policy with nested handler containers and reacts to its replacement', async () => {
        const Child = defineComponent({ setup() { return { policy: useFormFlowPresentation() }; }, template: '<p>{{ policy?.layout.density }}</p>' });
        const wrapper = mount(Presentation, { props: { presentation: policy }, slots: { default: Child } });
        expect(wrapper.text()).toContain('Step 2 of 4');
        expect(wrapper.text()).toContain('compact');
        expect(wrapper.get('img').attributes('style')).toContain('height: 32px');
        expect(wrapper.get('[data-testid="form-flow-presentation"]').classes()).toContain('pb-28');
        await wrapper.setProps({ presentation: { ...policy, layout: { ...policy.layout, density: 'immersive' } } });
        expect(wrapper.text()).toContain('immersive');
    });
});
