import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitClaimLiveScreen from '../../../resources/js/cockpit/components/CockpitClaimLiveScreen.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...await importOriginal<typeof import('@inertiajs/vue3')>(),
    Head: { template: '<div><slot /></div>' },
    usePoll: () => ({ start: vi.fn(), stop: vi.fn() }),
}));

describe('Cockpit claim entry walkthrough', () => {
    it('renders the real payment page as a non-interactive preview', async () => {
        const wrapper = mount(CockpitClaimLiveScreen, {
            props: { screen: {
                kind: 'payment_preview', component: 'x-change/claim/Payment', props: {
                    previewMode: true,
                    payment: { pay_code: 'AA317', currency: 'PHP', target_amount_minor: 10000,
                        collected_amount_minor: 0, amount_due_minor: 10000, is_fully_paid: false,
                        rider_message: null, provider: 'netbank', provider_available: true,
                        can_create_attempt: false, poll_interval_ms: 5000, attempt: null, receipt: null },
                },
            } },
        });
        await flushPromises();
        expect(wrapper.get('[data-testid="cockpit-claim-actual-screen"]').attributes()).toHaveProperty('inert');
        await vi.waitFor(() => expect(wrapper.text()).toContain('Pay AA317'));
        expect(wrapper.text()).toContain('₱100.00');
        expect(wrapper.text()).toContain('Choose how to pay');
        wrapper.unmount();
    });

    it('uses the shared pending receipt presentation for the success preview', () => {
        const wrapper = mount(CockpitClaimLiveScreen, {
            props: { screen: { kind: 'success', amount: '₱25.00', message: 'Thank you.' } },
        });

        expect(wrapper.get('[data-testid="provider-payout-pending-region"]').text()).toContain('Claim received');
        expect(wrapper.get('[data-testid="claim-transfer-receipt"]').text()).toContain('₱25.00');
        expect(wrapper.text()).toContain('Message from the sender');
        expect(wrapper.text()).toContain('Thank you.');
    });

    it('shows an unattributed thank you when the preview has no sender message', () => {
        const wrapper = mount(CockpitClaimLiveScreen, {
            props: { screen: { kind: 'success', amount: '₱25.00', message: null } },
        });

        expect(wrapper.text()).toContain('Thank you.');
        expect(wrapper.text()).not.toContain('Message from the sender');
    });

    it('uses the live x-ray card with the sample code and a single visible action', async () => {
        const wrapper = mount(CockpitClaimLiveScreen, {
            props: {
                presentation: 'viewport',
                screen: {
                    kind: 'claim_entry',
                    code: 'AA317',
                    title: 'Disbursement Details',
                    xray: {
                        visible: true,
                        status: 'claimable',
                        disclosures: [
                            { key: 'amount', value: '₱25.00' },
                            { key: 'reference', value: 'AA317' },
                        ],
                        requirements: [
                            { key: 'mobile', label: 'Mobile number' },
                        ],
                    },
                },
            },
        });

        expect(wrapper.get('[data-testid="cockpit-claim-entry-xray"]').text()).toContain('₱25.00');
        expect(wrapper.get('[data-testid="cockpit-claim-entry-xray"]').text()).not.toContain('AA317');
        expect((wrapper.get('[data-testid="claim-code-input"]').element as HTMLInputElement).value).toBe('AA317');
        expect(wrapper.get('[data-testid="claim-code-left-pipes"]').text()).toBe('||');
        expect(wrapper.get('[data-testid="claim-code-right-pipes"]').text()).toBe('||');
        expect(wrapper.get('[data-testid="xray-requirements-toggle"]').text()).toContain('Mobile number');
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Continue')).toHaveLength(1);

        await wrapper.get('[data-testid="xray-requirements-toggle"]').trigger('click');

        expect(wrapper.get('[data-testid="xray-required-inputs"]').isVisible()).toBe(true);
    });
});
