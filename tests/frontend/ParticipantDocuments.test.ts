import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ParticipantDocuments from '../../resources/js/components/x-change/ParticipantDocuments.vue';

vi.mock('@inertiajs/vue3', () => ({ router: { reload: vi.fn() } }));

describe('ParticipantDocuments', () => {
    const requirement = { type: 'PROOF_OF_PAYMENT', timing: 'before_payment', requires_acceptance: true };

    it('renders the issuer requirement and prevents upload in the walkthrough', () => {
        const wrapper = mount(ParticipantDocuments, { props: { requirements: [requirement], previewMode: true } });
        expect(wrapper.text()).toContain('PROOF OF PAYMENT');
        expect(wrapper.text()).toContain('Required before payment');
        expect(wrapper.text()).toContain('Approval required');
        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
    });

    it('shows the submitted document review status', () => {
        const wrapper = mount(ParticipantDocuments, { props: { requirements: [{ ...requirement, status: 'rejected' }], uploadUrl: '/test' } });
        expect(wrapper.text()).toContain('Please resubmit');
        expect(wrapper.get('input').attributes('disabled')).toBeUndefined();
    });
});
