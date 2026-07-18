import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PayCodeExplorer from '../../../resources/js/cockpit/pages/PayCodeExplorer.vue';
import PayCodeExplorerRouteAdapter from '../../../resources/js/pages/x-change/cockpit/PayCodeExplorer.vue';

const payCodesReadModel = {
    status: 'available',
    authorized: true,
    query: null,
    records: [],
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

        expect(wrapper.text()).toContain('Campaign Explorer Context');
        expect(wrapper.text()).toContain('Campaign-aware Pay Code view');
        expect(wrapper.text()).toContain('Read-only filter');
        expect(wrapper.text()).toContain('campaign-plan-1');
        expect(wrapper.text()).toContain('execution-1');
        expect(wrapper.text()).toContain('campaign-1');
        expect(wrapper.text()).toContain('audience-1');
        expect(wrapper.text()).toContain('recipient-1');
        expect(wrapper.text()).toContain('Pay Code Explorer');
        expect(wrapper.text()).toContain('campaign-navigation-read-only');
        expect(wrapper.text()).toContain('navigation-context-only');
        expect(wrapper.find('[data-testid="cockpit-campaign-navigation-context"]').exists()).toBe(true);
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

        expect(wrapper.text()).toContain('Campaign Explorer Context');
        expect(wrapper.text()).toContain('campaign-plan-1');
        expect(wrapper.text()).not.toContain('must-not-render');
    });
});
