import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PayCodeOutcomePanel from '../../resources/js/components/x-change/PayCodeOutcomePanel.vue';

describe('PayCodeOutcomePanel', () => {
    it('renders the backend-provided status label and code without a tilted stamp', () => {
        const wrapper = mount(PayCodeOutcomePanel, {
            props: {
                statusKey: 'redeemed',
                statusLabel: 'Already claimed',
                code: 'ABC12345',
            },
        });

        expect(wrapper.text()).toContain('Already claimed');
        expect(wrapper.text()).toContain('Complete');
        expect(wrapper.text()).not.toContain('redeemed');
        expect(wrapper.text()).toContain('ABC12345');
        expect(wrapper.find('[data-testid="pay-code-outcome-panel"]').exists()).toBe(true);
        expect(wrapper.text().match(/Already claimed/g)).toHaveLength(1);
    });

    it('renders the formatted amount and redeemed date for issuer viewers only when provided', () => {
        const wrapper = mount(PayCodeOutcomePanel, {
            props: {
                statusKey: 'redeemed',
                statusLabel: 'Already claimed',
                code: 'ABC12345',
                formattedAmount: '₱100.00',
                redeemedAt: '2024-01-15T10:30:00Z',
                payoutStatus: 'completed',
            },
        });

        expect(wrapper.text()).toContain('₱100.00');
        expect(wrapper.find('[data-testid="pay-code-outcome-redeemed-at"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="pay-code-outcome-payout-status"]').text()).toContain('completed');
    });

    it('omits amount/redeemed-at/payout-status rows entirely for a guest-facing outcome without them', () => {
        const wrapper = mount(PayCodeOutcomePanel, {
            props: {
                statusKey: 'expired',
                statusLabel: 'Expired',
            },
        });

        expect(wrapper.find('[data-testid="pay-code-outcome-redeemed-at"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pay-code-outcome-payout-status"]').exists()).toBe(false);
        expect(wrapper.text().match(/Expired/g)).toHaveLength(1);
    });
});
