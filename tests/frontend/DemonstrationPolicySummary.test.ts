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
        expect(wrapper.get('[data-testid="demo-policy-applicant-name"]').text()).toBe('Not provided');
    });

    it('renders submitted details as escaped text and does not shift birth dates or create contact links', () => {
        const wrapper = mount(DemonstrationPolicySummary, {
            props: {
                summary: { reference: 'AUI-DEMO-EXAMPLE', product: 'Demo', effective_at: null, expires_at: null, recorded_at: '', notice: 'Demonstration only' },
                applicant: { name: '<script>alert(1)</script>', address: '123 Demo Street\nExample City', birth_date: '1990-01-02', mobile: '09170000001', email: 'demo@example.test' },
            },
            global: { stubs: { ClaimStepShell: { template: '<main><slot /></main>' } } },
        });
        expect(wrapper.text()).toContain('Applicant details submitted');
        expect(wrapper.text()).toContain('not independently verified');
        expect(wrapper.get('[data-testid="demo-policy-applicant-name"]').text()).toBe('<script>alert(1)</script>');
        expect(wrapper.get('[data-testid="demo-policy-applicant-address"]').text()).toContain('123 Demo Street');
        expect(wrapper.get('[data-testid="demo-policy-applicant-birth_date"]').text()).toBe('1990-01-02');
        expect(wrapper.get('[data-testid="demo-policy-applicant-mobile"]').text()).toBe('09170000001');
        expect(wrapper.get('[data-testid="demo-policy-applicant-email"]').text()).toBe('demo@example.test');
        expect(wrapper.findAll('script, a')).toHaveLength(0);
    });
});
