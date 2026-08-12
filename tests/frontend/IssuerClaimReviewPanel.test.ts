import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import IssuerClaimReviewPanel from '../../resources/js/components/x-change/IssuerClaimReviewPanel.vue';

describe('IssuerClaimReviewPanel', () => {
    it('renders the headline, description, requirement summary, and open pay code action', () => {
        const wrapper = mount(IssuerClaimReviewPanel, {
            props: {
                headline: 'Your Pay Code was claimed',
                description: 'Review the submitted claim requirements and payout status.',
                requirementItems: [
                    { key: 'mobile', label: 'Mobile number', status: 'completed', tone: 'positive', description: null },
                ],
                actions: [
                    { key: 'open_pay_code', label: 'Open Pay Code', href: '/x/pay-codes/ABC123', method: 'get', variant: 'secondary' },
                ],
            },
        });

        expect(wrapper.text()).toContain('Your Pay Code was claimed');
        expect(wrapper.text()).toContain('Review the submitted claim requirements');
        expect(wrapper.find('[data-testid="claim-requirement-summary"]').exists()).toBe(true);

        const action = wrapper.find('[data-testid="issuer-claim-review-panel-action-open_pay_code"]');
        expect(action.exists()).toBe(true);
        expect(action.attributes('href')).toBe('/x/pay-codes/ABC123');
    });

    it('renders an approve payout action when the claim is pending issuer approval', () => {
        const wrapper = mount(IssuerClaimReviewPanel, {
            props: {
                headline: 'Your Pay Code was claimed',
                actions: [
                    { key: 'approve_payout', label: 'Approve Payout', href: '/x/pay-codes/ABC123/approval', method: 'get', variant: 'primary' },
                ],
            },
        });

        expect(wrapper.find('[data-testid="issuer-claim-review-panel-action-approve_payout"]').text()).toBe('Approve Payout');
    });

    it('renders the payout route and outcome panel when provided', () => {
        const wrapper = mount(IssuerClaimReviewPanel, {
            props: {
                headline: 'Your Pay Code was claimed',
                outcomePanel: {
                    status_key: 'redeemed',
                    status_label: 'Already claimed',
                    code: 'ABC123',
                    formatted_amount: '₱100.00',
                },
                payoutRoute: {
                    bank_code: 'GXCHPHM2XXX',
                    settlement_rail: 'INSTAPAY',
                    account_number_masked: '*******1987',
                },
            },
        });

        expect(wrapper.find('[data-testid="pay-code-outcome-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="payout-route-display"]').exists()).toBe(true);
    });

    it('omits the requirement summary, payout route, and actions region when none are provided', () => {
        const wrapper = mount(IssuerClaimReviewPanel, {
            props: {
                headline: 'Your Pay Code was claimed',
            },
        });

        expect(wrapper.find('[data-testid="claim-requirement-summary"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="payout-route-display"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="issuer-claim-review-panel-actions"]').exists()).toBe(false);
    });
});
