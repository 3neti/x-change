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
    status_filter: 'redeemed',
    stats: {
        total: 4,
        active: 1,
        awaiting_approval: 1,
        redeemed: 2,
        expired: 0,
        pending: 0,
        failed: 0,
        filtered: 2,
    },
    filters: [
        {
            key: 'search',
            label: 'Search',
            value: 'PC-HYDRATED',
            active: true,
            read_only: true,
        },
        {
            key: 'status',
            label: 'All',
            value: 'all',
            active: false,
            read_only: true,
        },
        {
            key: 'status',
            label: 'Redeemed',
            value: 'redeemed',
            active: true,
            read_only: true,
        },
    ],
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
            actions: [
                {
                    key: 'detail',
                    label: 'View details',
                    enabled: true,
                    read_only: true,
                    href: '/x/cockpit/pay-codes/PC-HYDRATED-001',
                    reason: 'Read-only Cockpit voucher detail route.',
                },
                {
                    key: 'distribution',
                    label: 'Distribution',
                    enabled: true,
                    read_only: true,
                    href: '/x/cockpit/pay-codes/PC-HYDRATED-001/distribution',
                    reason: 'Read-only Cockpit distribution workspace route.',
                },
                {
                    key: 'notify',
                    label: 'Notify recipient',
                    enabled: false,
                    read_only: true,
                    reason: 'Feedback delivery remains separately gated.',
                },
            ],
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

const activityNavigationContext = {
    schema: 'x-change.cockpit.activity-navigation.v1',
    status: 'available',
    authorized: true,
    source: 'operator_issuance_activity',
    code: 'PC-HYDRATED-001',
    destination: 'pay_code_explorer',
    read_only: true,
    mutation: {
        enabled: false,
        status: 'blocked',
        reason: 'activity-navigation-read-only',
    },
    redactions: {
        payloads: 'activity-navigation-context-only',
    },
    provider_payload: 'must-not-render',
    raw_payload: 'must-not-render',
    wallet: 'must-not-render',
    mutation_route: '/must-not-render',
};

describe('Cockpit Pay Code Explorer hydration', () => {
    it('hydrates explorer rows from sanitized list read model records', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        expect(wrapper.text()).toContain('Search, filter, and open read-only Pay Code workspaces.');
        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('PC-HYDRATED-002');
        expect(wrapper.text()).toContain('₱1,500.75');
        expect(wrapper.text()).toContain('ready');
        expect(wrapper.find('[data-testid="cockpit-pay-code-row-secondary-facts"]').text()).toContain('Treasury Desk');
        expect(wrapper.text()).toContain('sanitized-list-only');
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(2);
    });

    it('renders search and status filters as read-only GET navigation during hydration', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const search = wrapper.find('[data-testid="cockpit-pay-code-search-input"]');
        const status = wrapper.find('[data-testid="cockpit-pay-code-status-filter"]');
        const submit = wrapper.find('[data-testid="cockpit-pay-code-filter-submit"]');
        const activeSummary = wrapper.find('[data-testid="cockpit-pay-code-active-filter-summary"]');

        expect(search.element).toHaveProperty('value', 'PC-HYDRATED');
        expect(search.classes()).toContain('h-9');
        expect(search.classes()).toContain('rounded-full');
        expect(status.element).toHaveProperty('value', 'redeemed');
        expect(status.classes()).toContain('h-9');
        expect(status.classes()).toContain('rounded-full');
        expect(submit.text()).toBe('Search');
        expect(submit.classes()).toContain('h-9');
        expect(activeSummary.text()).toContain('Filters: search “PC-HYDRATED” · status redeemed');
        expect(wrapper.find('form').attributes('method')).toBe('get');
        expect(wrapper.find('form').attributes('action')).toBe('/x/cockpit/pay-codes');
        expect(wrapper.text()).toContain('Filters: search “PC-HYDRATED” · status redeemed');
        expect(wrapper.text()).toContain('Search Pay Codes');
        expect(wrapper.find('[data-testid="cockpit-pay-code-clear-filters"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-pay-code-clear-filters"]').text()).toBe('Clear');
    });

    it('renders pay code explorer functional parity stats from the read model', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        expect(wrapper.find('[data-testid="cockpit-pay-code-stats-summary"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-pay-code-stats-summary"]').element.tagName.toLowerCase()).toBe('details');
        expect(wrapper.text()).toContain('List totals');
        expect(wrapper.text()).toContain('Read-only totals');
        expect(wrapper.text()).toContain('Filtered');
        expect(wrapper.text()).toContain('Total');
        expect(wrapper.text()).toContain('Needs attention');
    });

    it('groups secondary utility panels behind one page details disclosure', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const pageDetails = wrapper.find('[data-testid="cockpit-pay-code-page-details-disclosure"]');

        expect(pageDetails.exists()).toBe(true);
        expect(pageDetails.element.tagName.toLowerCase()).toBe('details');
        expect(pageDetails.find('summary').text()).toContain('Page details');
        expect(pageDetails.text()).toContain('Read-only rules, totals, and connected-service context.');
        expect(pageDetails.text()).toContain('The main scan path stays focused on search and results.');
        expect(pageDetails.classes()).toContain('py-3');
        expect(pageDetails.find('[data-testid="cockpit-pay-code-row-action-guidance"]').exists()).toBe(true);
        expect(pageDetails.find('[data-testid="cockpit-pay-code-stats-summary"]').exists()).toBe(true);
        expect(pageDetails.find('[data-testid="cockpit-pay-code-integration-badges"]').exists()).toBe(true);
        expect(pageDetails.find('[data-testid="cockpit-pay-code-integration-readiness"]').exists()).toBe(true);
    });

    it('renders the operator list summary as a compact scan strip', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const summary = wrapper.find('[data-testid="cockpit-pay-code-explorer-primary-summary"]');
        const currentSearch = wrapper.find('[data-testid="cockpit-pay-code-explorer-current-search-disclosure"]');
        const summaryItems = summary.findAll('[data-testid="cockpit-pay-code-explorer-primary-summary-item"]');

        expect(summary.classes()).toContain('p-3');
        expect(summary.text()).toContain('Search Pay Codes');
        expect(summary.text()).not.toContain('Voucher status summary');
        expect(summary.text()).not.toContain('Focus the list by lifecycle state');
        expect(currentSearch.exists()).toBe(true);
        expect(currentSearch.element.tagName.toLowerCase()).toBe('details');
        expect(currentSearch.find('summary').text()).toContain('Current search and read model');
        expect(summaryItems).toHaveLength(5);
        expect(summary.find('[data-testid="cockpit-pay-code-explorer-status-pills"]').exists()).toBe(true);
        expect(summaryItems[0].classes()).toContain('rounded-full');
        expect(summaryItems[0].find('dd').classes()).toContain('text-base');
        expect(summaryItems[0].find('p').exists()).toBe(false);
    });

    it('renders the explorer shell header as a compact page intro', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const header = wrapper.find('[data-testid="cockpit-pay-code-explorer-shell-header"]');
        const facts = wrapper.find('[data-testid="cockpit-pay-code-explorer-shell-facts"]');

        expect(header.exists()).toBe(true);
        expect(header.text()).toContain('Pay Code operations');
        expect(header.text()).toContain('Search, filter, and open read-only Pay Code workspaces.');
        expect(header.text()).not.toContain('does not mutate vouchers');
        expect(header.text()).toContain('Quick Generate');
        expect(header.text()).toContain('Read-only');
        expect(facts.exists()).toBe(true);
        expect(facts.classes()).toContain('xl:w-[32rem]');
        expect(facts.findAll('div')).toHaveLength(3);
        expect(facts.find('div').classes()).toContain('rounded-full');
        expect(facts.text()).toContain('Read model');
        expect(facts.text()).toContain('Records');
        expect(facts.text()).toContain('Payload policy');
    });

    it('renders a primary operator list summary with safe navigation actions', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const summary = wrapper.find('[data-testid="cockpit-pay-code-explorer-primary-summary"]');
        const items = summary.findAll('[data-testid="cockpit-pay-code-explorer-primary-summary-item"]');

        expect(summary.exists()).toBe(true);
        expect(summary.text()).toContain('Search Pay Codes');
        expect(summary.text()).toContain('Total');
        expect(summary.text()).toContain('4');
        expect(summary.text()).toContain('Active');
        expect(summary.text()).toContain('Redeemed');
        expect(summary.text()).toContain('Expired');
        expect(summary.text()).toContain('Attention');
        expect(summary.text()).toContain('1');
        expect(wrapper.text()).toContain('Payload policy');
        expect(wrapper.text()).toContain('sanitized-list-only');
        expect(wrapper.text()).toContain('Current search and read model');
        expect(summary.text()).toContain('Search');
        expect(wrapper.text()).toContain('PC-HYDRATED');
        expect(wrapper.text()).toContain('Status');
        expect(wrapper.text()).toContain('redeemed');
        expect(wrapper.text()).toContain('Campaign Context');
        expect(wrapper.text()).toContain('None');
        expect(wrapper.text()).toContain('Search and filters only change the current list view');
        expect(wrapper.text()).toContain('does not mutate vouchers');
        expect(items).toHaveLength(5);
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-explorer-current-search-item"]')).toHaveLength(4);
        expect(wrapper.find('[data-testid="cockpit-pay-code-explorer-primary-quick-generate-link"]').attributes('href')).toBe('/x/cockpit/quick-generate');
        expect(wrapper.find('[data-testid="cockpit-pay-code-explorer-primary-clear-link"]').attributes('href')).toBe('/x/cockpit/pay-codes');
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

    it('renders read-only detail and distribution row action links from hydrated records', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const actionLinks = wrapper.findAll('[data-testid="cockpit-pay-code-row-action-link"]');
        const disabledActions = wrapper.findAll('[data-testid="cockpit-pay-code-row-action-disabled"]');

        expect(actionLinks).toHaveLength(2);
        expect(actionLinks[0].attributes('href')).toBe('/x/cockpit/pay-codes/PC-HYDRATED-001');
        expect(actionLinks[0].text()).toContain('View details');
        expect(actionLinks[0].attributes('aria-label')).toBe('View details');
        expect(actionLinks[0].attributes('title')).toBe('View details');
        expect(actionLinks[0].classes()).toContain('h-8');
        expect(actionLinks[0].classes()).toContain('w-8');
        expect(actionLinks[0].classes()).toContain('justify-center');
        expect(actionLinks[0].find('svg').exists()).toBe(true);
        expect(actionLinks[1].attributes('href')).toBe('/x/cockpit/pay-codes/PC-HYDRATED-001/distribution');
        expect(actionLinks[1].attributes('aria-label')).toBe('Distribution');
        expect(actionLinks[1].text()).toContain('Distribution');
        expect(disabledActions.some((action) => action.text().includes('Notify recipient'))).toBe(true);
        const unavailableSummary = wrapper.find('[data-testid="cockpit-pay-code-row-unavailable-actions"] summary');

        expect(wrapper.find('[data-testid="cockpit-pay-code-row-unavailable-actions"]').text()).toContain('1 unavailable');
        expect(unavailableSummary.attributes('aria-label')).toBe('More row actions');
        expect(unavailableSummary.classes()).toContain('h-8');
        expect(unavailableSummary.classes()).toContain('w-8');
        expect(unavailableSummary.find('svg').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-pay-code-results-table"]').text()).not.toContain('Execute');
        expect(wrapper.text()).not.toContain('provider_payload');
    });

    it('keeps row action controls stable-width and centered for scan quality', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const desktopActionColumn = wrapper.find('[data-testid="cockpit-pay-code-row"] td:last-child > div');
        const desktopAmountCell = wrapper.find('[data-testid="cockpit-pay-code-amount"]');
        const mobileLinks = wrapper.findAll('[data-testid="cockpit-pay-code-mobile-row-action-link"]');
        const mobileDisabledSummary = wrapper.find('[data-testid="cockpit-pay-code-mobile-row-disabled-summary"]');

        expect(desktopAmountCell.classes()).toContain('py-2.5');
        expect(desktopActionColumn.classes()).toContain('justify-end');
        expect(desktopActionColumn.classes()).toContain('gap-1.5');
        expect(desktopActionColumn.findAll('svg').length).toBeGreaterThan(0);
        expect(mobileLinks[0].classes()).toContain('min-h-9');
        expect(mobileLinks[0].classes()).toContain('justify-center');
        expect(mobileLinks[0].classes()).toContain('text-center');
        expect(mobileDisabledSummary.text()).toContain('More');
        expect(mobileDisabledSummary.classes()).toContain('min-h-9');
        expect(mobileDisabledSummary.classes()).toContain('justify-center');
        expect(mobileDisabledSummary.classes()).toContain('text-center');
    });

    it('keeps unavailable row action counts behind a quiet disclosure', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const desktopUnavailable = wrapper.find('[data-testid="cockpit-pay-code-row-unavailable-actions"]');
        const desktopSummary = desktopUnavailable.find('summary');
        const mobileUnavailable = wrapper.find('[data-testid="cockpit-pay-code-mobile-row-unavailable-actions"]');
        const mobileSummary = wrapper.find('[data-testid="cockpit-pay-code-mobile-row-disabled-summary"]');

        expect(desktopSummary.text()).toContain('1 unavailable actions');
        expect(desktopSummary.text()).not.toBe('1 unavailable');
        expect(desktopSummary.find('svg').exists()).toBe(true);
        expect(desktopUnavailable.findAll('[data-testid="cockpit-pay-code-row-action-disabled"]')).toHaveLength(1);
        expect(mobileUnavailable.exists()).toBe(true);
        expect(mobileSummary.text()).toContain('More');
        expect(mobileSummary.text()).toContain('1 unavailable actions');
        expect(mobileUnavailable.findAll('[data-testid="cockpit-pay-code-mobile-row-action-disabled"]')).toHaveLength(1);
    });

    it('renders mobile-first Pay Code result cards without duplicating desktop row test contracts', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const mobileResults = wrapper.find('[data-testid="cockpit-pay-code-mobile-results"]');
        const mobileRows = wrapper.findAll('[data-testid="cockpit-pay-code-mobile-row"]');
        const mobileLinks = wrapper.findAll('[data-testid="cockpit-pay-code-mobile-row-action-link"]');

        expect(mobileResults.exists()).toBe(true);
        expect(mobileResults.classes()).toContain('md:hidden');
        expect(mobileRows).toHaveLength(2);
        expect(mobileRows[0].text()).toContain('PC-HYDRATED-001');
        expect(mobileRows[0].text()).toContain('Money Changer');
        expect(mobileRows[0].text()).toContain('₱1,500.75');
        expect(mobileRows[0].find('[data-testid="cockpit-pay-code-mobile-row-secondary-facts"]').text()).toContain('Treasury Desk');
        expect(mobileRows[0].text()).toContain('More');
        expect(mobileRows[0].text()).toContain('1 unavailable actions');
        expect(mobileLinks).toHaveLength(2);
        expect(mobileLinks[0].attributes('href')).toBe('/x/cockpit/pay-codes/PC-HYDRATED-001');
        expect(mobileLinks[1].attributes('href')).toBe('/x/cockpit/pay-codes/PC-HYDRATED-001/distribution');
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(2);
    });

    it('renders scan-friendly status badges without mutating status facts', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: {
                    ...payCodesReadModel,
                    records: [
                        {
                            ...payCodesReadModel.records[0],
                            status: 'issued',
                            display_status: 'issued',
                        },
                        {
                            ...payCodesReadModel.records[0],
                            code: 'PC-STATUS-EXPIRED',
                            status: 'expired',
                            display_status: 'expired',
                        },
                        {
                            ...payCodesReadModel.records[0],
                            code: 'PC-STATUS-AWAITING',
                            status: 'awaiting_approval',
                            display_status: 'awaiting_approval',
                        },
                    ],
                },
            },
        });

        const statusBadges = wrapper.findAll('[data-testid="cockpit-pay-code-status-badge"]');
        const mobileStatusBadges = wrapper.findAll('[data-testid="cockpit-pay-code-mobile-status-badge"]');

        expect(statusBadges).toHaveLength(3);
        expect(statusBadges[0].text()).toBe('Issued');
        expect(statusBadges[0].classes()).toContain('bg-emerald-50');
        expect(statusBadges[1].text()).toBe('Expired');
        expect(statusBadges[1].classes()).toContain('bg-rose-50');
        expect(statusBadges[2].text()).toBe('Awaiting Approval');
        expect(statusBadges[2].classes()).toContain('bg-amber-50');
        expect(mobileStatusBadges[2].text()).toBe('Awaiting Approval');
    });

    it('renders scan-friendly amount values without mutating amount facts', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const desktopAmounts = wrapper.findAll('[data-testid="cockpit-pay-code-amount"]');
        const mobileAmounts = wrapper.findAll('[data-testid="cockpit-pay-code-mobile-amount"]');

        expect(desktopAmounts).toHaveLength(2);
        expect(desktopAmounts[0].text()).toBe('₱1,500.75');
        expect(desktopAmounts[0].classes()).toContain('text-right');
        expect(desktopAmounts[0].classes()).toContain('font-mono');
        expect(desktopAmounts[0].classes()).toContain('tabular-nums');
        expect(mobileAmounts[0].text()).toBe('₱1,500.75');
        expect(mobileAmounts[0].classes()).toContain('font-mono');
        expect(mobileAmounts[0].classes()).toContain('tabular-nums');
    });

    it('summarizes pay code result density before the rows', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const density = wrapper.find('[data-testid="cockpit-pay-code-results-density-summary"]');
        const scanGuide = wrapper.find('[data-testid="cockpit-pay-code-results-scan-guide"]');

        expect(density.exists()).toBe(true);
        expect(density.classes()).toContain('sm:w-[30rem]');
        expect(density.text()).toContain('Showing');
        expect(density.text()).toContain('2 of 2');
        expect(density.text()).toContain('Total Rows');
        expect(density.text()).toContain('2');
        expect(density.text()).toContain('Links');
        expect(density.text()).toContain('2');
        expect(density.text()).toContain('Disabled');
        expect(density.text()).toContain('4');
        expect(scanGuide.exists()).toBe(true);
        expect(scanGuide.element.tagName.toLowerCase()).toBe('details');
        expect(scanGuide.text()).toContain('How to scan these rows');
    });

    it('keeps result metric values stable while pagination range text changes', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const density = wrapper.find('[data-testid="cockpit-pay-code-results-density-summary"]');
        const metricValues = density.findAll('dd');

        expect(metricValues).toHaveLength(4);
        expect(density.classes()).toContain('sm:w-[30rem]');
        expect(density.classes()).toContain('rounded-full');

        metricValues.forEach((metricValue) => {
            expect(metricValue.classes()).toContain('whitespace-nowrap');
            expect(metricValue.classes()).toContain('font-mono');
            expect(metricValue.classes()).toContain('tabular-nums');
        });
    });

    it('renders the results header as a compact pagination toolbar', () => {
        const records = Array.from({ length: 30 }, (_, index) => ({
            ...payCodesReadModel.records[0],
            code: `PC-TOOLBAR-${String(index + 1).padStart(3, '0')}`,
        }));

        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: {
                    ...payCodesReadModel,
                    stats: {
                        ...payCodesReadModel.stats,
                        total: 30,
                        filtered: 30,
                    },
                    records,
                },
            },
        });

        const table = wrapper.find('[data-testid="cockpit-pay-code-results-table"]');
        const density = wrapper.find('[data-testid="cockpit-pay-code-results-density-summary"]');
        const notice = wrapper.find('[data-testid="cockpit-pay-code-result-limit-notice"]');
        const pagination = wrapper.find('[data-testid="cockpit-pay-code-result-pagination"]');

        expect(table.find('.border-b').classes()).toContain('p-4');
        expect(density.classes()).toContain('rounded-full');
        expect(density.classes()).toContain('p-1.5');
        expect(density.find('div').classes()).toContain('rounded-full');
        expect(density.find('div').classes()).toContain('py-1.5');
        expect(notice.classes()).toContain('mt-3');
        expect(notice.classes()).toContain('text-slate-600');
        expect(pagination.classes()).toContain('mt-3');
        expect(pagination.classes()).toContain('p-2.5');
        expect(pagination.text()).toContain('Page 1 of 2');
    });

    it('paginates high-volume result rendering while preserving total counts and navigation safety', async () => {
        const records = Array.from({ length: 30 }, (_, index) => ({
            ...payCodesReadModel.records[0],
            code: `PC-VOLUME-${String(index + 1).padStart(3, '0')}`,
            actions: payCodesReadModel.records[0].actions?.map((action) => ({
                ...action,
                href:
                    action.href && action.enabled
                        ? action.href.replace(
                              'PC-HYDRATED-001',
                              `PC-VOLUME-${String(index + 1).padStart(3, '0')}`,
                          )
                        : action.href,
            })),
        }));

        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: {
                    ...payCodesReadModel,
                    stats: {
                        ...payCodesReadModel.stats,
                        total: 30,
                        filtered: 30,
                    },
                    records,
                },
            },
        });

        const density = wrapper.find('[data-testid="cockpit-pay-code-results-density-summary"]');
        const notice = wrapper.find('[data-testid="cockpit-pay-code-result-limit-notice"]');
        const pagination = wrapper.find('[data-testid="cockpit-pay-code-result-pagination"]');
        const footerPagination = wrapper.find('[data-testid="cockpit-pay-code-result-pagination-footer"]');
        const pageSize = wrapper.find('[data-testid="cockpit-pay-code-result-page-size"]');
        const previous = wrapper.find('[data-testid="cockpit-pay-code-result-pagination-previous"]');
        const next = wrapper.find('[data-testid="cockpit-pay-code-result-pagination-next"]');
        const footerPrevious = wrapper.find('[data-testid="cockpit-pay-code-result-pagination-footer-previous"]');
        const footerNext = wrapper.find('[data-testid="cockpit-pay-code-result-pagination-footer-next"]');

        expect(density.text()).toContain('Showing');
        expect(density.text()).toContain('1–25 of 30');
        expect(density.text()).toContain('Total Rows');
        expect(density.text()).toContain('30');
        expect(notice.exists()).toBe(true);
        expect(notice.text()).toContain('Showing 1–25 of 30 Pay Codes.');
        expect(notice.text()).toContain('pagination changes only the browser view');
        expect(pagination.exists()).toBe(true);
        expect(pagination.text()).toContain('Page 1 of 2');
        expect(footerPagination.exists()).toBe(true);
        expect(footerPagination.text()).toContain('Showing 1–25 of 30');
        expect(pageSize.exists()).toBe(true);
        expect(pageSize.element.tagName.toLowerCase()).toBe('select');
        expect(pageSize.text()).toContain('10 per page');
        expect(pageSize.text()).toContain('25 per page');
        expect(pageSize.text()).toContain('50 per page');
        expect(previous.attributes('disabled')).toBeDefined();
        expect(next.attributes('disabled')).toBeUndefined();
        expect(footerPrevious.attributes('disabled')).toBeDefined();
        expect(footerNext.attributes('disabled')).toBeUndefined();
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(25);
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-mobile-row"]')).toHaveLength(25);
        expect(wrapper.text()).toContain('PC-VOLUME-025');
        expect(wrapper.text()).not.toContain('PC-VOLUME-026');
        expect(wrapper.text()).toContain('Links');
        expect(wrapper.text()).toContain('60');
        expect(wrapper.text()).toContain('Disabled');
        expect(wrapper.text()).toContain('30');
        expect(wrapper.text()).toContain('Search Pay Codes');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');

        await next.trigger('click');

        expect(density.text()).toContain('26–30 of 30');
        expect(notice.text()).toContain('Showing 26–30 of 30 Pay Codes.');
        expect(pagination.text()).toContain('Page 2 of 2');
        expect(footerPagination.text()).toContain('Showing 26–30 of 30');
        expect(wrapper.find('[data-testid="cockpit-pay-code-result-pagination-previous"]').attributes('disabled')).toBeUndefined();
        expect(wrapper.find('[data-testid="cockpit-pay-code-result-pagination-next"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-testid="cockpit-pay-code-result-pagination-footer-previous"]').attributes('disabled')).toBeUndefined();
        expect(wrapper.find('[data-testid="cockpit-pay-code-result-pagination-footer-next"]').attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(5);
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-mobile-row"]')).toHaveLength(5);
        expect(wrapper.text()).not.toContain('PC-VOLUME-025');
        expect(wrapper.text()).toContain('PC-VOLUME-026');
        expect(wrapper.text()).toContain('PC-VOLUME-030');

        await wrapper.find('[data-testid="cockpit-pay-code-result-pagination-previous"]').trigger('click');

        expect(density.text()).toContain('1–25 of 30');
        expect(pagination.text()).toContain('Page 1 of 2');
        expect(footerPagination.text()).toContain('Showing 1–25 of 30');
    });

    it('lets operators page from the footer after scanning result rows', async () => {
        const records = Array.from({ length: 30 }, (_, index) => ({
            ...payCodesReadModel.records[0],
            code: `PC-FOOTER-${String(index + 1).padStart(3, '0')}`,
        }));

        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: {
                    ...payCodesReadModel,
                    stats: {
                        ...payCodesReadModel.stats,
                        total: 30,
                        filtered: 30,
                    },
                    records,
                },
            },
        });

        const density = wrapper.find('[data-testid="cockpit-pay-code-results-density-summary"]');
        const footerPagination = wrapper.find('[data-testid="cockpit-pay-code-result-pagination-footer"]');

        expect(footerPagination.exists()).toBe(true);
        expect(footerPagination.text()).toContain('Showing 1–25 of 30');

        await wrapper.find('[data-testid="cockpit-pay-code-result-pagination-footer-next"]').trigger('click');

        expect(density.text()).toContain('26–30 of 30');
        expect(footerPagination.text()).toContain('Showing 26–30 of 30');
        expect(wrapper.text()).toContain('PC-FOOTER-030');

        await wrapper.find('[data-testid="cockpit-pay-code-result-pagination-footer-previous"]').trigger('click');

        expect(density.text()).toContain('1–25 of 30');
        expect(footerPagination.text()).toContain('Showing 1–25 of 30');
        expect(wrapper.text()).toContain('PC-FOOTER-001');
    });

    it('lets operators choose result density without changing the read-only result set', async () => {
        const records = Array.from({ length: 30 }, (_, index) => ({
            ...payCodesReadModel.records[0],
            code: `PC-SIZE-${String(index + 1).padStart(3, '0')}`,
        }));

        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: {
                    ...payCodesReadModel,
                    stats: {
                        ...payCodesReadModel.stats,
                        total: 30,
                        filtered: 30,
                    },
                    records,
                },
            },
        });

        const density = wrapper.find('[data-testid="cockpit-pay-code-results-density-summary"]');
        const pagination = wrapper.find('[data-testid="cockpit-pay-code-result-pagination"]');
        const pageSize = wrapper.find('[data-testid="cockpit-pay-code-result-page-size"]');

        expect(density.text()).toContain('1–25 of 30');
        expect(pagination.text()).toContain('Page 1 of 2');
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(25);

        await pageSize.setValue('10');

        expect(density.text()).toContain('1–10 of 30');
        expect(pagination.text()).toContain('Page 1 of 3');
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(10);
        expect(wrapper.text()).toContain('PC-SIZE-010');
        expect(wrapper.text()).not.toContain('PC-SIZE-011');

        await wrapper.find('[data-testid="cockpit-pay-code-result-pagination-next"]').trigger('click');

        expect(density.text()).toContain('11–20 of 30');
        expect(pagination.text()).toContain('Page 2 of 3');

        await pageSize.setValue('50');

        expect(density.text()).toContain('1–30 of 30');
        expect(wrapper.find('[data-testid="cockpit-pay-code-result-pagination"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-row"]')).toHaveLength(30);
        expect(wrapper.text()).toContain('PC-SIZE-030');
    });

    it('summarizes row action safety before the result rows', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const guidance = wrapper.find('[data-testid="cockpit-pay-code-row-action-guidance"]');
        const items = guidance.findAll('[data-testid="cockpit-pay-code-row-action-guidance-item"]');

        expect(guidance.exists()).toBe(true);
        expect(guidance.element.tagName.toLowerCase()).toBe('details');
        expect(guidance.text()).toContain('Row action guidance');
        expect(guidance.text()).toContain('Links only');
        expect(guidance.text()).toContain('Navigation Links');
        expect(guidance.text()).toContain('2');
        expect(guidance.text()).toContain('Blocked Actions');
        expect(guidance.text()).toContain('1');
        expect(guidance.text()).toContain('Rows');
        expect(guidance.text()).toContain('open inspection workspaces or remain disabled');
        expect(guidance.text()).toContain('does not execute actions');
        expect(guidance.text()).toContain('does not execute actions, deliver feedback, mutate vouchers, or call providers');
        expect(items).toHaveLength(3);
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

    it('renders read-only integration status badges from the read model bundle', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                read_model: {
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [{ id: 'journal-1' }],
                        redactions: { payloads: 'journal-evidence-summary-only' },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [{ key: 'review' }],
                        redactions: { payloads: 'safe-action-host-summary-only' },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [{ id: 'delivery-1' }],
                        redactions: { payloads: 'communication-delivery-summary-only' },
                    },
                    raw_payload: 'must-not-render',
                },
            },
        });

        expect(wrapper.text()).toContain('Connected service badges');
        expect(wrapper.text()).toContain('Connected services');
        expect(wrapper.text()).toContain('Journal: available');
        expect(wrapper.text()).toContain('Actions: available');
        expect(wrapper.text()).toContain('Feedback: available');
        expect(wrapper.text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.findAll('[data-testid="cockpit-pay-code-integration-badge"]')).toHaveLength(3);
    });

    it('keeps explorer integration badges authorization and redaction safe', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                read_model: {
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [
                            {
                                id: 'journal-1',
                                raw_payload: 'SECRET-DO-NOT-RENDER',
                            },
                        ],
                        redactions: {
                            payloads: 'journal-evidence-summary-only',
                            reason: 'read-model-ready',
                            exception: 'RuntimeException',
                            exception_message: 'Stack trace must stay hidden',
                        },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [
                            {
                                key: 'review',
                                target_url: '/unsafe-action-route',
                                raw_diagnostics: 'SECRET-DO-NOT-RENDER',
                            },
                        ],
                        redactions: {
                            payloads: 'safe-action-host-summary-only',
                            reason: 'presentation-only',
                        },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [
                            {
                                id: 'delivery-1',
                                recipient: '+639170000000',
                                provider_payload: 'SECRET-DO-NOT-RENDER',
                            },
                        ],
                        redactions: {
                            payloads: 'communication-delivery-summary-only',
                            reason: 'read-model-ready',
                        },
                    },
                    raw_payload: 'SECRET-DO-NOT-RENDER',
                },
            },
        });

        expect(wrapper.text()).toContain('Connected service badges');
        expect(wrapper.text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).toContain('safe-action-host-summary-only');
        expect(wrapper.text()).toContain('communication-delivery-summary-only');
        expect(wrapper.text()).not.toContain('SECRET-DO-NOT-RENDER');
        expect(wrapper.text()).not.toContain('RuntimeException');
        expect(wrapper.text()).not.toContain('Stack trace must stay hidden');
        expect(wrapper.text()).not.toContain('/unsafe-action-route');
        expect(wrapper.text()).not.toContain('+639170000000');
    });

    it('renders operator-readable integration readiness cards', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                read_model: {
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [{ id: 'journal-1' }],
                        redactions: { payloads: 'journal-evidence-summary-only' },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [{ key: 'review' }],
                        redactions: { payloads: 'safe-action-host-summary-only' },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [{ id: 'delivery-1' }],
                        redactions: { payloads: 'communication-delivery-summary-only' },
                    },
                    raw_payload: 'must-not-render',
                },
            },
        });

        const readiness = wrapper.find('[data-testid="cockpit-pay-code-integration-readiness"]');
        const cards = readiness.findAll('[data-testid="cockpit-pay-code-integration-readiness-card"]');

        expect(readiness.exists()).toBe(true);
        expect(readiness.element.tagName.toLowerCase()).toBe('details');
        expect(readiness.text()).toContain('Connected service details');
        expect(readiness.text()).toContain('Connected service readiness');
        expect(readiness.text()).toContain('Journal');
        expect(readiness.text()).toContain('journal-evidence-summary-only');
        expect(readiness.text()).toContain('Journal evidence remains read-only audit context');
        expect(readiness.text()).toContain('Actions');
        expect(readiness.text()).toContain('safe-action-host-summary-only');
        expect(readiness.text()).toContain('presentation-only');
        expect(readiness.text()).toContain('Feedback');
        expect(readiness.text()).toContain('communication-delivery-summary-only');
        expect(readiness.text()).toContain('communication status, not lifecycle truth');
        expect(readiness.text()).toContain('do not write journal entries');
        expect(cards).toHaveLength(3);
        expect(readiness.text()).not.toContain('must-not-render');
    });

    it('hydrates read-only operator activity navigation context on the Pay Code Explorer', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                activity_navigation_context: activityNavigationContext,
            },
        });

        expect(wrapper.text()).toContain('Activity navigation context');
        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('operator_issuance_activity');
        expect(wrapper.text()).toContain('pay_code_explorer');
        expect(wrapper.text()).toContain('activity-navigation-read-only');
        expect(wrapper.text()).toContain('activity-navigation-context-only');
        expect(wrapper.find('[data-testid="cockpit-activity-navigation-context"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('/must-not-render');
    });
});
