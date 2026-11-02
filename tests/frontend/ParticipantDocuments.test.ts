import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ParticipantDocuments from '../../resources/js/components/x-change/ParticipantDocuments.vue';

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { reload } }));

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

    it('uploads a selected file and refreshes the payment status', async () => {
        const request = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ review_status: 'pending' }) });
        vi.stubGlobal('fetch', request);
        const wrapper = mount(ParticipantDocuments, { props: { requirements: [requirement], uploadUrl: '/test' } });
        const input = wrapper.get('input');
        Object.defineProperty(input.element, 'files', { configurable: true, value: [new File(['document'], 'proof.pdf', { type: 'application/pdf' })] });
        await input.trigger('change');
        await flushPromises();
        expect(request).toHaveBeenCalledWith('/test', expect.objectContaining({ method: 'POST', body: expect.any(FormData) }));
        expect(wrapper.text()).toContain('Uploaded for review');
        expect(reload).toHaveBeenCalledWith({ only: ['payment'] });
        vi.unstubAllGlobals();
    });
});
