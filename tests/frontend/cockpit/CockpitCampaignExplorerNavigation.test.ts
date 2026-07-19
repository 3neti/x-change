import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PayCodeExplorer from '../../../resources/js/cockpit/pages/PayCodeExplorer.vue';
import PayCodeExplorerRouteAdapter from '../../../resources/js/pages/x-change/cockpit/PayCodeExplorer.vue';

const payCodesReadModel = {
    status: 'available',
    authorized: true,
    query: null,
    records: [
        {
            code: 'PC-CAMPAIGN-001',
            template: 'OFW Remittance',
            amount: 500,
            currency: 'PHP',
            status: 'active',
            actions: [
                {
                    key: 'detail',
                    label: 'View details',
                    enabled: true,
                    read_only: true,
                    href: '/x/cockpit/pay-codes/PC-CAMPAIGN-001',
                },
                {
                    key: 'distribution',
                    label: 'Distribution',
                    enabled: true,
                    read_only: true,
                    href: '/x/cockpit/pay-codes/PC-CAMPAIGN-001/distribution?tab=share',
                },
            ],
        },
    ],
    redactions: {
        payloads: 'sanitized-list-summary-only',
    },
};

const campaignNavigationContext = {
    schema: 'x-change.cockpit.campaign-navigation.v1',
    status: 'available',
    authorized: true,
    source: 'campaign_cockpit',
    planning_key: 'campaign-plan-1',
    execution_id: 'execution-1',
    campaign_id: 'campaign-1',
    audience_id: 'audience-1',
    recipient_id: 'recipient-1',
    destination: 'pay_code_explorer',
    read_only: true,
    mutation: {
        enabled: false,
        status: 'blocked',
        reason: 'campaign-navigation-read-only',
    },
    redactions: {
        payloads: 'navigation-context-only',
    },
    provider_payload: 'must-not-render',
    raw_payload: 'must-not-render',
    mutation_route: '/must-not-render',
};

describe('Cockpit campaign explorer navigation boundary', () => {
    it('hydrates read-only campaign navigation context on the Pay Code Explorer', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                campaign_navigation_context: campaignNavigationContext,
            },
        });

        expect(wrapper.text()).toContain('Campaign Context');
        expect(wrapper.text()).toContain('Showing Pay Codes from a campaign view');
        expect(wrapper.text()).toContain('Read-only context');
        expect(wrapper.text()).toContain('campaign-plan-1');
        expect(wrapper.text()).toContain('campaign-1');
        expect(wrapper.text()).toContain('recipient-1');
        expect(wrapper.text()).toContain('Pay Code Explorer');
        expect(wrapper.text()).toContain('Campaign changes are disabled');
        expect(wrapper.text()).toContain('Campaign filter details');
        expect(wrapper.text()).toContain('Use this view for inspection and navigation only');
        expect(wrapper.text()).not.toContain('campaign-navigation-read-only');
        expect(wrapper.text()).toContain('navigation-context-only');
        expect(wrapper.find('[data-testid="cockpit-campaign-navigation-context"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-campaign-navigation-primary-context-item"]')).toHaveLength(4);
        expect(wrapper.find('[data-testid="cockpit-campaign-navigation-context-details"]').element.tagName.toLowerCase()).toBe('details');
        expect(wrapper.findAll('[data-testid="cockpit-campaign-navigation-context-item"]')).toHaveLength(8);
        expect(wrapper.find('[data-testid="cockpit-campaign-navigation-mutation-button"]').exists()).toBe(false);
    });

    it('links back to the dashboard with the same campaign context', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                campaign_navigation_context: campaignNavigationContext,
            },
        });

        const link = wrapper.find('[data-testid="cockpit-campaign-navigation-dashboard-link"]');

        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toContain('/x/cockpit?');
        expect(link.attributes('href')).toContain('campaign_planning_key=campaign-plan-1');
        expect(link.attributes('href')).toContain('campaign_execution_id=execution-1');
        expect(link.attributes('href')).toContain('campaign_id=campaign-1');
        expect(link.attributes('href')).toContain('campaign_audience_id=audience-1');
        expect(link.attributes('href')).toContain('campaign_recipient_id=recipient-1');
        expect(link.attributes('href')).toContain('campaign_source=campaign_cockpit');
    });

    it('preserves campaign context through read-only explorer filter forms', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: {
                    ...payCodesReadModel,
                    query: 'PC-CAMPAIGN',
                    status_filter: 'issued',
                    filters: [
                        { key: 'status', label: 'Issued', value: 'issued', active: true, read_only: true },
                    ],
                },
                campaign_navigation_context: campaignNavigationContext,
            },
        });

        const hiddenFields = wrapper.findAll('[data-testid="cockpit-pay-code-search-context-input"]');
        const filterCards = wrapper.findAll('[data-testid="cockpit-pay-code-filter"]');

        expect(hiddenFields.map((field) => [field.attributes('name'), field.attributes('value')])).toEqual([
            ['campaign_planning_key', 'campaign-plan-1'],
            ['campaign_execution_id', 'execution-1'],
            ['campaign_source', 'campaign_cockpit'],
            ['campaign_id', 'campaign-1'],
            ['campaign_audience_id', 'audience-1'],
            ['campaign_recipient_id', 'recipient-1'],
        ]);
        expect(wrapper.find('[data-testid="cockpit-pay-code-clear-filters"]').attributes('href')).toContain('campaign_planning_key=campaign-plan-1');
        expect(wrapper.find('[data-testid="cockpit-pay-code-explorer-primary-clear-link"]').attributes('href')).toContain('campaign_recipient_id=recipient-1');
        expect(filterCards.some((card) => card.text().includes('Campaign Planning Key'))).toBe(true);
        expect(filterCards.some((card) => card.text().includes('Campaign Recipient Id'))).toBe(true);
        expect(wrapper.text()).toContain('Campaign context is preserved as read-only Explorer orientation metadata.');
    });

    it('preserves campaign context through row detail and distribution navigation links', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                campaign_navigation_context: campaignNavigationContext,
            },
        });

        const actionLinks = wrapper.findAll('[data-testid="cockpit-pay-code-row-action-link"]');

        expect(actionLinks).toHaveLength(2);
        expect(actionLinks[0].attributes('href')).toContain('/x/cockpit/pay-codes/PC-CAMPAIGN-001?');
        expect(actionLinks[0].attributes('href')).toContain('campaign_planning_key=campaign-plan-1');
        expect(actionLinks[0].attributes('href')).toContain('campaign_recipient_id=recipient-1');
        expect(actionLinks[1].attributes('href')).toContain('/x/cockpit/pay-codes/PC-CAMPAIGN-001/distribution?');
        expect(actionLinks[1].attributes('href')).toContain('tab=share');
        expect(actionLinks[1].attributes('href')).toContain('campaign_execution_id=execution-1');
        expect(actionLinks[1].attributes('href')).toContain('campaign_source=campaign_cockpit');
    });

    it('does not render unsafe campaign navigation payloads or routes', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                campaign_navigation_context: campaignNavigationContext,
            },
        });

        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('/must-not-render');
        expect(wrapper.text()).not.toContain('/campaigns/');
    });

    it('forwards campaign navigation context through the route adapter', () => {
        const wrapper = mount(PayCodeExplorerRouteAdapter, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                campaign_navigation_context: campaignNavigationContext,
            },
        });

        expect(wrapper.text()).toContain('Campaign Context');
        expect(wrapper.text()).toContain('Showing Pay Codes from a campaign view');
        expect(wrapper.text()).toContain('campaign-plan-1');
        expect(wrapper.text()).not.toContain('must-not-render');
    });
});
