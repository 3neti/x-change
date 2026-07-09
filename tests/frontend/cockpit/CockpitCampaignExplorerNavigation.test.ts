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

        expect(wrapper.text()).toContain('Campaign navigation context');
        expect(wrapper.text()).toContain('campaign-plan-1');
        expect(wrapper.text()).toContain('execution-1');
        expect(wrapper.text()).toContain('pay_code_explorer');
        expect(wrapper.text()).toContain('campaign-navigation-read-only');
        expect(wrapper.text()).toContain('navigation-context-only');
        expect(wrapper.find('[data-testid="cockpit-campaign-navigation-context"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-campaign-navigation-mutation-button"]').exists()).toBe(false);
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

        expect(wrapper.text()).toContain('Campaign navigation context');
        expect(wrapper.text()).toContain('campaign-plan-1');
        expect(wrapper.text()).not.toContain('must-not-render');
    });
});
