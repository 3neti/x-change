import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitPayCodeFilterBuilder from '../../../resources/js/cockpit/components/CockpitPayCodeFilterBuilder.vue';
import CockpitPayCodeResultsTable from '../../../resources/js/cockpit/components/CockpitPayCodeResultsTable.vue';
import CockpitPayCodeSearchBar from '../../../resources/js/cockpit/components/CockpitPayCodeSearchBar.vue';
import PayCodeExplorer from '../../../resources/js/cockpit/pages/PayCodeExplorer.vue';
import {
    cockpitPayCodeExplorerFilters,
    cockpitPayCodeExplorerRecords,
    cockpitPayCodeRowActions,
} from '../../../resources/js/cockpit/payCodeExplorerDefaults';

describe('Cockpit Pay Code Explorer foundation', () => {
    it('renders read-only search filtering controls without mutation behavior', async () => {
        const wrapper = mount(CockpitPayCodeSearchBar, {
            props: {
                query: 'PC-READY',
            },
        });

        const input = wrapper.find(
            '[data-testid="cockpit-pay-code-search-input"]',
        );

        expect(input.element).toHaveProperty('readOnly', false);
        expect(input.element).toHaveProperty('value', 'PC-READY');
        expect(wrapper.find('form').exists()).toBe(true);
        expect(wrapper.text()).toContain(
            'Filters use read-only GET navigation.',
        );

        expect(wrapper.find('[type="submit"]').exists()).toBe(true);
    });

    it('renders filter builder placeholders without applying host queries', () => {
        const wrapper = mount(CockpitPayCodeFilterBuilder, {
            props: {
                filters: cockpitPayCodeExplorerFilters,
            },
        });

        expect(wrapper.text()).toContain('Query controls placeholder');
        expect(wrapper.text()).toContain('Status');
        expect(wrapper.text()).toContain('Template');
        expect(wrapper.text()).toContain('Risk');
        expect(wrapper.text()).toContain(
            'Filtering is presentation-only until a host query API is wired.',
        );
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-filter"]'),
        ).toHaveLength(3);
    });

    it('renders result rows and disabled row actions without hidden mutations', async () => {
        const wrapper = mount(CockpitPayCodeResultsTable, {
            props: {
                records: cockpitPayCodeExplorerRecords,
                actions: cockpitPayCodeRowActions,
            },
        });

        expect(wrapper.text()).toContain('Pay Code read-model placeholder');
        expect(wrapper.text()).toContain('PC-READY-001');
        expect(wrapper.text()).toContain('PC-PENDING-002');
        expect(wrapper.text()).toContain('PC-SETTLE-003');
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(3);
        expect(wrapper.text()).toContain('View details');
        expect(wrapper.text()).toContain('Open timeline');
        expect(wrapper.text()).toContain('Notify recipient');

        for (const action of wrapper.findAll(
            '[data-testid="cockpit-pay-code-row-action-disabled"]',
        )) {
            expect(action.attributes('disabled')).toBeDefined();
            expect(action.attributes('title')).toBeTruthy();
        }
    });

    it('renders the full explorer page with active navigation and side-effect boundaries', () => {
        const wrapper = mount(PayCodeExplorer);

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-explorer-shell"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Pay Code Explorer Foundation');
        expect(wrapper.text()).toContain('Search');
        expect(wrapper.text()).toContain('Filter Builder');
        expect(wrapper.text()).toContain('Results');
        expect(wrapper.find('[aria-current="page"]').text()).toContain(
            'Pay Codes',
        );
        expect(wrapper.text()).toContain('does not mutate vouchers');
        expect(wrapper.text()).toContain('execute drivers');
        expect(wrapper.text()).toContain('approve claims');
        expect(wrapper.text()).toContain('send feedback');
        expect(wrapper.text()).toContain('write journal entries');
        expect(wrapper.text()).toContain('move money');
    });
});
