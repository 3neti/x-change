import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DemonstrationPolicySummary from '../../resources/js/pages/x-change/claim/DemonstrationPolicySummary.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
}));

vi.mock('@/components/x-change/ClaimStepShell.vue', () => ({
    default: { template: '<main><slot /></main>' },
}));

describe('Medicard demonstration summary', () => {
    it('renders product-aware copy and the full demonstration disclaimer', () => {
        const wrapper = mount(DemonstrationPolicySummary, {
            props: {
                summary: {
                    reference: 'MEDICARD-DEMO-ABC',
                    product: 'MediCard Demo Benefit Pass',
                    effective_at: '2026-10-09T08:00:00+08:00',
                    expires_at: '2026-10-10T08:00:00+08:00',
                    recorded_at: '2026-10-09T08:05:00+08:00',
                    eyebrow: 'Demonstration only',
                    title: 'Demo benefit summary',
                    description: 'Your healthcare-access demonstration response was recorded.',
                    notice: 'DEMONSTRATION ONLY. This does not create MediCard membership, healthcare coverage, a policy, a letter of authorization, or a right to treatment or reimbursement.',
                    action_label: 'View demo benefit',
                    action_description: 'View your private summary.',
                },
                applicant: { name: 'Demo Participant' },
            },
        });

        expect(wrapper.get('h1').text()).toBe('Demo benefit summary');
        expect(wrapper.text()).toContain('MediCard Demo Benefit Pass');
        expect(wrapper.get('[data-testid="demo-policy-notice"]').text()).toContain(
            'does not create MediCard membership',
        );
        expect(wrapper.text()).toContain('right to treatment or reimbursement');
    });
});
