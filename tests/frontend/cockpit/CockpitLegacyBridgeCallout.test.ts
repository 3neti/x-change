import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitBridgeCallout from '../../../resources/js/components/x-change/CockpitBridgeCallout.vue';

describe('Cockpit legacy bridge callout', () => {
    it('renders an operator link when a legacy page exposes an available cockpit bridge', () => {
        const wrapper = mount(CockpitBridgeCallout, {
            global: {
                stubs: {
                    Link: {
                        props: ['href'],
                        template: '<a :href="href"><slot /></a>',
                    },
                },
            },
            props: {
                title: 'Cockpit Pay Code Explorer is available',
                bridge: {
                    status: 'available',
                    relationship: 'legacy-pay-code-list-to-cockpit-explorer',
                    cockpit_route: '/x/cockpit/pay-codes',
                    legacy_owner: 'PayCodeIndexPageController',
                },
            },
        });

        expect(wrapper.find('[data-testid="x-change-cockpit-bridge-callout"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Cockpit bridge');
        expect(wrapper.text()).toContain('Cockpit Pay Code Explorer is available');
        expect(wrapper.text()).toContain('legacy page remains the functional owner');
        expect(wrapper.text()).toContain('PayCodeIndexPageController');
        expect(wrapper.find('[data-testid="x-change-cockpit-bridge-link"]').attributes('href')).toBe('/x/cockpit/pay-codes');
    });

    it('does not render when a legacy page has no available bridge', () => {
        const wrapper = mount(CockpitBridgeCallout, {
            props: {
                bridge: null,
            },
        });

        expect(wrapper.find('[data-testid="x-change-cockpit-bridge-callout"]').exists()).toBe(false);
    });
});
