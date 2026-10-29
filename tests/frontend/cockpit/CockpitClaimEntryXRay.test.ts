import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitClaimLiveScreen from '../../../resources/js/cockpit/components/CockpitClaimLiveScreen.vue';

describe('Cockpit claim entry walkthrough', () => {
    it('uses the shared pending receipt presentation for the success preview', () => {
        const wrapper = mount(CockpitClaimLiveScreen, {
            props: { screen: { kind: 'success', amount: '₱25.00', message: 'Thank you.' } },
        });

        expect(wrapper.get('[data-testid="provider-payout-pending-region"]').text()).toContain('Claim received');
        expect(wrapper.get('[data-testid="claim-transfer-receipt"]').text()).toContain('₱25.00');
        expect(wrapper.text()).toContain('Message from the sender');
        expect(wrapper.text()).toContain('Thank you.');
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
