import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DemonstrationPolicySummary from '../../resources/js/pages/x-change/claim/DemonstrationPolicySummary.vue';

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<slot />' } }));

describe('demonstration policy summary', () => {
    it('labels the result as a demonstration rather than insurance and formats the period in PHT', () => {
        const wrapper = mount(DemonstrationPolicySummary, {
            props: { summary: {
                reference: 'AUI-DEMO-EXAMPLE', product: 'Personal Accident — demonstration',
                effective_at: '2026-09-24T06:19:24Z', expires_at: '2026-09-25T06:19:24Z',
                recorded_at: '2026-09-24T07:21:00Z',
                notice: 'Demonstration only. This is not an issued insurance policy and does not establish insurance coverage.',
            } },
            global: { stubs: { ClaimStepShell: { template: '<main><slot /></main>' } } },
        });
        expect(wrapper.get('h1').text()).toBe('Demo policy summary');
        expect(wrapper.get('[data-testid="demo-policy-notice"]').text()).toContain('not an issued insurance policy');
        expect(wrapper.text()).toContain('AUI-DEMO-EXAMPLE');
        expect(wrapper.text()).toContain('2:19 PM PHT');
        expect(wrapper.text()).toContain('should not be shared');
        expect(wrapper.findAll('a')).toHaveLength(0);
        expect(wrapper.html()).not.toContain('v-html');
    });
});
