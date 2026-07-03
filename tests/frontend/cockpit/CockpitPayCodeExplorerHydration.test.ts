import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PayCodeExplorer from '../../../resources/js/cockpit/pages/PayCodeExplorer.vue';
import PayCodeExplorerRouteAdapter from '../../../resources/js/pages/x-change/cockpit/PayCodeExplorer.vue';

const payCodesReadModel = {
    status: 'ready',
    authorized: true,
    query: 'PC-HYDRATED',
    redactions: {
        payloads: 'sanitized-list-only',
    },
    records: [
        {
            code: 'PC-HYDRATED-001',
            template: 'Money Changer',
            amount: 1500.75,
            currency: 'PHP',
            status: 'issued',
            display_status: 'ready',
            owner: 'Treasury Desk',
            last_activity: '2026-07-03T10:00:00+08:00',
            provider_payload: 'must-not-render',
            raw_payload: 'must-not-render',
            wallet: 'must-not-render',
            claims: 'must-not-render',
            approval: 'must-not-render',
        },
        {
            code: 'PC-HYDRATED-002',
            template: 'Settlement Envelope',
            amount: null,
            currency: 'PHP',
            status: 'not_wired',
            owner: null,
            last_activity: null,
        },
    ],
};

describe('Cockpit Pay Code Explorer hydration', () => {
    it('hydrates explorer rows from sanitized list read model records', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        expect(wrapper.text()).toContain('Pay Code Explorer Read Model');
        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('PC-HYDRATED-002');
        expect(wrapper.text()).toContain('₱1,500.75');
        expect(wrapper.text()).toContain('ready');
        expect(wrapper.text()).toContain('Treasury Desk');
        expect(wrapper.text()).toContain('sanitized-list-only');
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(2);
    });

    it('keeps search and filter controls local and read-only during hydration', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const search = wrapper.find('[data-testid="cockpit-pay-code-search-input"]');

        expect(search.element).toHaveProperty('readOnly', true);
        expect(search.element).toHaveProperty('value', 'PC-HYDRATED');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.text()).toContain('Filtering remains local and read-only until an approved host query API exists.');
    });

    it('does not render unsafe fields from hydrated list records', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('wallet');
    });

    it('renders an explicit empty state for authorized empty list read models', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: {
                    status: 'ready',
                    authorized: true,
                    query: '',
                    redactions: { payloads: 'sanitized-list-only' },
                    records: [],
                },
            },
        });

        expect(wrapper.text()).toContain('No Pay Codes available in the sanitized read model.');
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(0);
    });

    it('forwards route adapter props into the Cockpit Pay Code Explorer page', () => {
        const wrapper = mount(PayCodeExplorerRouteAdapter, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('₱1,500.75');
        expect(wrapper.find('[data-testid="cockpit-pay-code-explorer-shell"]').exists()).toBe(true);
    });
});
