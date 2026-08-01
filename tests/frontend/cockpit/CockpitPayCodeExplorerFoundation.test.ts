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
        expect(wrapper.text()).toContain('Search Pay Codes');
        expect(input.classes()).toContain('h-9');

        expect(wrapper.find('[type="submit"]').exists()).toBe(true);
    });

    it('renders filter summary cards without applying mutations', () => {
        const wrapper = mount(CockpitPayCodeFilterBuilder, {
            props: {
                filters: cockpitPayCodeExplorerFilters,
            },
        });

        expect(wrapper.element.tagName.toLowerCase()).toBe('section');
        expect(wrapper.text()).toContain('Filter metadata');
        expect(wrapper.text()).toContain('Read-only query criteria');
        expect(wrapper.text()).toContain(
            'Filtering uses normal GET navigation',
        );
        expect(wrapper.classes()).toContain('py-3');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-filter-density-summary"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-filter-density-summary"]')
                .classes(),
        ).toContain('rounded-full');
        expect(wrapper.text()).toContain('Active');
        expect(wrapper.text()).toContain('Context');
        expect(wrapper.text()).toContain('Total');
        expect(wrapper.text()).toContain('Status');
        expect(wrapper.text()).toContain('Template');
        expect(wrapper.text()).toContain('Risk');
        expect(wrapper.text()).toContain(
            'Current list includes every lifecycle state.',
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

        expect(wrapper.text()).toContain('Pay Code results');
        expect(wrapper.text()).toContain('3 Pay Codes');
        expect(wrapper.text()).not.toContain('How to scan these rows');
        expect(wrapper.text()).not.toContain('Pay Code read-model placeholder');
        expect(wrapper.text()).toContain('PC-READY-001');
        expect(wrapper.text()).toContain('PC-PENDING-002');
        expect(wrapper.text()).toContain('PC-SETTLE-003');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-results-scan-guide"]')
                .exists(),
        ).toBe(false);
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
        expect(wrapper.text()).toContain('Pay Code Explorer');
        expect(wrapper.text()).toContain('Search');
        expect(wrapper.text()).toContain('Explorer details');
        expect(wrapper.text()).toContain('Filter metadata');
        expect(wrapper.text()).toContain('Results');
        expect(wrapper.find('[data-testid="cockpit-layout"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('does not mutate vouchers');
        expect(wrapper.text()).toContain('send feedback');
        expect(wrapper.text()).toContain('write journal entries');
        expect(wrapper.text()).toContain('move money');
    });
});
