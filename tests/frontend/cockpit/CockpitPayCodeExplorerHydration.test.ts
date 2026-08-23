import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
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
        {
            key: 'status',
            label: 'Locked',
            value: 'locked',
            active: false,
            read_only: true,
        },
        {
            key: 'status',
            label: 'Cancelled',
            value: 'cancelled',
            active: false,
            read_only: true,
        },
        {
            key: 'status',
            label: 'Closed',
            value: 'closed',
            active: false,
            read_only: true,
        },
    ],
    records: [
        {
            code: 'PC-HYDRATED-001',
            template: 'Money Changer',
            purpose: 'School transport allowance',
            amount: 1500.75,
            currency: 'PHP',
            status: 'issued',
            display_status: 'ready',
            capability: {
                key: 'disbursement',
                label: 'Disbursement',
                voucher_type_label: 'Redeemable',
            },
            instruction_badges: [
                { key: 'mobile_bound', label: 'Mobile-bound' },
                { key: 'settlement_rail', label: 'InstaPay' },
                { key: 'otp', label: 'OTP' },
                { key: 'signature', label: 'Signature' },
            ],
            party: {
                state: 'claimed',
                label: 'Claimed by',
                primary: 'Leslie Chong',
                secondary: '•••• 4567',
                masked: true,
            },
            timing: {
                created_at: '2026-07-03T09:00:00+08:00',
                starts_at: '2026-07-03T09:15:00+08:00',
                expires_at: '2026-07-10T09:00:00+08:00',
                redeemed_at: '2026-07-03T10:00:00+08:00',
                terminal_at: '2026-07-03T10:00:00+08:00',
            },
            terminal_control: {
                can_expire: true,
                can_cancel: true,
                blocked_reason: null,
                status: 'available',
            },
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

        expect(wrapper.text()).toContain(
            'Search, inspect, and apply governed lifecycle controls.',
        );
        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('PC-HYDRATED-002');
        expect(wrapper.text()).toContain('School transport allowance');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-purpose"]')
                .attributes('title'),
        ).toBe('School transport allowance');
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-copy-button"]'),
        ).toHaveLength(4);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-pay-code-terminal-action-toggle"]',
            ),
        ).toHaveLength(4);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-pay-code-quick-terminal-form"]',
            ),
        ).toHaveLength(4);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-pay-code-quick-terminal-form"] textarea[name="reason"]',
                )
                .attributes('required'),
        ).toBeDefined();
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-pay-code-quick-terminal-form"] input[name="confirmed"]',
                )
                .attributes('required'),
        ).toBeDefined();
        expect(wrapper.text()).toContain('Created');
        expect(wrapper.text()).toContain('Claimed');
        expect(wrapper.text()).toContain('₱1,500.75');
        expect(wrapper.text()).toContain('ready');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-row-secondary-facts"]')
                .text(),
        ).toContain('Treasury Desk');
        expect(wrapper.text()).toContain('sanitized-list-only');
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(2);
    });

    it('renders search and status filters as read-only GET navigation during hydration', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const search = wrapper.find(
            '[data-testid="cockpit-pay-code-search-input"]',
        );
        const status = wrapper.find(
            '[data-testid="cockpit-pay-code-status-filter"]',
        );
        const submit = wrapper.find(
            '[data-testid="cockpit-pay-code-filter-submit"]',
        );
        const activeSummary = wrapper.find(
            '[data-testid="cockpit-pay-code-active-filter-summary"]',
        );

        expect(search.element).toHaveProperty('value', 'PC-HYDRATED');
        expect(search.classes()).toContain('h-9');
        expect(search.classes()).toContain('rounded-full');
        expect(status.element).toHaveProperty('value', 'redeemed');
        expect(status.classes()).toContain('h-9');
        expect(status.classes()).toContain('rounded-full');
        expect(submit.text()).toBe('Search');
        expect(submit.classes()).toContain('h-9');
        expect(activeSummary.text()).toContain('Search: PC-HYDRATED');
        expect(activeSummary.text()).toContain('Status: redeemed');
        expect(
            activeSummary.findAll(
                '[data-testid="cockpit-pay-code-active-filter-chip"]',
            ),
        ).toHaveLength(2);
        expect(wrapper.find('form').attributes('method')).toBe('get');
        expect(wrapper.find('form').attributes('action')).toBe(
            '/x/cockpit/pay-codes',
        );
        expect(wrapper.text()).toContain('Search: PC-HYDRATED');
        expect(wrapper.text()).toContain('Status: redeemed');
        expect(wrapper.text()).toContain('Search Pay Codes');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-clear-filters"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-clear-filters"]')
                .text(),
        ).toBe('Clear');
        expect(status.text()).toContain('Locked');
        expect(status.text()).toContain('Cancelled');
        expect(status.text()).toContain('Closed');
    });

    it('applies status changes immediately while text search remains explicit', async () => {
        const routerGet = vi
            .spyOn(router, 'get')
            .mockImplementation(() => undefined);
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });
        const search = wrapper.find(
            '[data-testid="cockpit-pay-code-search-input"]',
        );
        const status = wrapper.find(
            '[data-testid="cockpit-pay-code-status-filter"]',
        );

        await search.setValue('PC-CHANGED');

        expect(routerGet).not.toHaveBeenCalled();

        await status.setValue('locked');

        expect(routerGet).toHaveBeenCalledOnce();
        expect(routerGet).toHaveBeenCalledWith(
            '/x/cockpit/pay-codes',
            {
                search: 'PC-CHANGED',
                status: 'locked',
            },
            expect.objectContaining({
                only: ['pay_codes_read_model'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            }),
        );

        routerGet.mockRestore();
    });

    it('renders pay code explorer functional parity stats from the read model', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-stats-summary"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-stats-summary"]')
                .element.tagName.toLowerCase(),
        ).toBe('details');
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

        const pageDetails = wrapper.find(
            '[data-testid="cockpit-pay-code-page-details-disclosure"]',
        );

        expect(pageDetails.exists()).toBe(true);
        expect(pageDetails.element.tagName.toLowerCase()).toBe('details');
        expect(pageDetails.find('summary').text()).toContain(
            'Explorer details',
        );
        expect(pageDetails.text()).toContain(
            'Filter metadata and read-model boundaries.',
        );
        expect(pageDetails.text()).toContain(
            'The main scan path stays focused on search and results.',
        );
        expect(pageDetails.classes()).toContain('py-3');
        expect(
            pageDetails
                .find('[data-testid="cockpit-pay-code-row-action-guidance"]')
                .exists(),
        ).toBe(true);
        expect(
            pageDetails
                .find('[data-testid="cockpit-pay-code-stats-summary"]')
                .exists(),
        ).toBe(true);
        expect(
            pageDetails
                .find('[data-testid="cockpit-pay-code-technical-details"]')
                .exists(),
        ).toBe(false);
        expect(
            pageDetails
                .find('[data-testid="cockpit-pay-code-filter-builder"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-results-table"]')
                .element.compareDocumentPosition(pageDetails.element) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('renders the operator list summary as a compact scan strip', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const summary = wrapper.find(
            '[data-testid="cockpit-pay-code-explorer-primary-summary"]',
        );
        const currentSearch = wrapper.find(
            '[data-testid="cockpit-pay-code-explorer-current-search-disclosure"]',
        );
        const summaryItems = summary.findAll(
            '[data-testid="cockpit-pay-code-explorer-primary-summary-item"]',
        );

        expect(summary.classes()).toContain('flex');
        expect(summary.text()).not.toContain('Voucher status summary');
        expect(summary.text()).not.toContain(
            'Focus the list by lifecycle state',
        );
        expect(currentSearch.exists()).toBe(true);
        expect(currentSearch.element.tagName.toLowerCase()).toBe('details');
        expect(currentSearch.find('summary').text()).toContain(
            'Current search and read model',
        );
        expect(summaryItems).toHaveLength(4);
        expect(summaryItems[0].classes()).toContain('items-baseline');
        expect(summaryItems[0].find('dd').classes()).toContain('font-semibold');
        expect(summaryItems[0].find('p').classes()).toContain('sr-only');
    });

    it('renders the explorer shell header as a compact page intro', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const header = wrapper.find(
            '[data-testid="cockpit-pay-code-explorer-shell-header"]',
        );
        const facts = wrapper.find(
            '[data-testid="cockpit-pay-code-explorer-shell-facts"]',
        );

        expect(header.exists()).toBe(true);
        expect(header.classes()).toEqual(
            expect.arrayContaining(['hidden', 'md:flex']),
        );
        expect(header.text()).toContain('Pay Code Explorer');
        expect(header.text()).toContain(
            'Search, inspect, and apply governed lifecycle controls.',
        );
        expect(header.text()).not.toContain('does not mutate vouchers');
        expect(header.text()).toContain('Create');
        expect(header.text()).toContain('Read-only');
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-pay-code-explorer-primary-summary"]',
                )
                .classes(),
        ).not.toContain('hidden');
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

        const summary = wrapper.find(
            '[data-testid="cockpit-pay-code-explorer-primary-summary"]',
        );
        const items = summary.findAll(
            '[data-testid="cockpit-pay-code-explorer-primary-summary-item"]',
        );

        expect(summary.exists()).toBe(true);
        expect(summary.text()).toContain('Total');
        expect(summary.text()).toContain('4');
        expect(summary.text()).toContain('Active');
        expect(summary.text()).toContain('Redeemed');
        expect(summary.text()).not.toContain('Expired');
        expect(summary.text()).toContain('Needs attention');
        expect(summary.text()).toContain('1');
        expect(wrapper.text()).toContain('Payload policy');
        expect(wrapper.text()).toContain('sanitized-list-only');
        expect(wrapper.text()).toContain('Current search and read model');
        expect(wrapper.text()).toContain('PC-HYDRATED');
        expect(wrapper.text()).toContain('Status');
        expect(wrapper.text()).toContain('redeemed');
        expect(wrapper.text()).toContain('Campaign Context');
        expect(wrapper.text()).toContain('None');
        expect(wrapper.text()).toContain(
            'Search and filters only change the current list view',
        );
        expect(wrapper.text()).toContain('governed expire and cancel controls');
        expect(items).toHaveLength(4);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-pay-code-explorer-current-search-item"]',
            ),
        ).toHaveLength(4);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-pay-code-explorer-primary-quick-generate-link"]',
                )
                .attributes('href'),
        ).toBe('/x/cockpit/quick-generate');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-clear-filters"]')
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes');
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

        const actionLinks = wrapper.findAll(
            '[data-testid="cockpit-pay-code-row-action-link"]',
        );
        const disabledActions = wrapper.findAll(
            '[data-testid="cockpit-pay-code-row-action-disabled"]',
        );

        expect(actionLinks).toHaveLength(2);
        expect(actionLinks[0].attributes('href')).toBe(
            '/x/cockpit/pay-codes/PC-HYDRATED-001',
        );
        expect(actionLinks[0].text()).toContain('View details');
        expect(actionLinks[0].attributes('aria-label')).toBe('View details');
        expect(actionLinks[0].attributes('title')).toBe('View details');
        expect(actionLinks[0].classes()).toContain('h-8');
        expect(actionLinks[0].classes()).toContain('w-8');
        expect(actionLinks[0].classes()).toContain('justify-center');
        expect(actionLinks[0].find('svg').exists()).toBe(true);
        expect(actionLinks[1].attributes('href')).toBe(
            '/x/cockpit/pay-codes/PC-HYDRATED-001/distribution',
        );
        expect(actionLinks[1].attributes('aria-label')).toBe('Distribution');
        expect(actionLinks[1].text()).toContain('Distribution');
        expect(
            disabledActions.some((action) =>
                action.text().includes('Notify recipient'),
            ),
        ).toBe(true);
        const unavailableSummary = wrapper.find(
            '[data-testid="cockpit-pay-code-row-unavailable-actions"] summary',
        );

        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-pay-code-row-unavailable-actions"]',
                )
                .text(),
        ).toContain('1 unavailable');
        expect(unavailableSummary.attributes('aria-label')).toBe(
            'More row actions',
        );
        expect(unavailableSummary.classes()).toContain('h-8');
        expect(unavailableSummary.classes()).toContain('w-8');
        expect(unavailableSummary.find('svg').exists()).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-results-table"]')
                .text(),
        ).not.toContain('Execute');
        expect(wrapper.text()).not.toContain('provider_payload');
    });

    it('keeps row action controls stable-width and centered for scan quality', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const desktopActionColumn = wrapper.find(
            '[data-testid="cockpit-pay-code-row"] td:last-child > div',
        );
        const desktopAmountCell = wrapper.find(
            '[data-testid="cockpit-pay-code-amount"]',
        );
        const mobileLinks = wrapper.findAll(
            '[data-testid="cockpit-pay-code-mobile-row-action-link"]',
        );
        const mobileDisabledSummary = wrapper.find(
            '[data-testid="cockpit-pay-code-mobile-row-disabled-summary"]',
        );

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

        const desktopUnavailable = wrapper.find(
            '[data-testid="cockpit-pay-code-row-unavailable-actions"]',
        );
        const desktopSummary = desktopUnavailable.find('summary');
        const mobileUnavailable = wrapper.find(
            '[data-testid="cockpit-pay-code-mobile-row-unavailable-actions"]',
        );
        const mobileSummary = wrapper.find(
            '[data-testid="cockpit-pay-code-mobile-row-disabled-summary"]',
        );

        expect(desktopSummary.text()).toContain('1 unavailable actions');
        expect(desktopSummary.text()).not.toBe('1 unavailable');
        expect(desktopSummary.find('svg').exists()).toBe(true);
        expect(
            desktopUnavailable.findAll(
                '[data-testid="cockpit-pay-code-row-action-disabled"]',
            ),
        ).toHaveLength(1);
        expect(mobileUnavailable.exists()).toBe(true);
        expect(mobileSummary.text()).toContain('More');
        expect(mobileSummary.text()).toContain('1 unavailable actions');
        expect(
            mobileUnavailable.findAll(
                '[data-testid="cockpit-pay-code-mobile-row-action-disabled"]',
            ),
        ).toHaveLength(1);
    });

    it('renders mobile-first Pay Code result cards without duplicating desktop row test contracts', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const mobileResults = wrapper.find(
            '[data-testid="cockpit-pay-code-mobile-results"]',
        );
        const mobileRows = wrapper.findAll(
            '[data-testid="cockpit-pay-code-mobile-row"]',
        );
        const mobileLinks = wrapper.findAll(
            '[data-testid="cockpit-pay-code-mobile-row-action-link"]',
        );

        expect(mobileResults.exists()).toBe(true);
        expect(mobileResults.classes()).toContain('md:hidden');
        expect(mobileRows).toHaveLength(2);
        expect(mobileRows[0].text()).toContain('PC-HYDRATED-001');
        expect(mobileRows[0].text()).toContain('Money Changer');
        expect(mobileRows[0].text()).toContain('₱1,500.75');
        expect(
            mobileRows[0]
                .find(
                    '[data-testid="cockpit-pay-code-mobile-row-secondary-facts"]',
                )
                .text(),
        ).toContain('Treasury Desk');
        expect(mobileRows[0].text()).toContain('More');
        expect(mobileRows[0].text()).toContain('1 unavailable actions');
        expect(mobileLinks).toHaveLength(2);
        expect(mobileLinks[0].attributes('href')).toBe(
            '/x/cockpit/pay-codes/PC-HYDRATED-001',
        );
        expect(mobileLinks[1].attributes('href')).toBe(
            '/x/cockpit/pay-codes/PC-HYDRATED-001/distribution',
        );
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(2);
    });

    it('keeps mobile row facts compact without duplicating identity content', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const mobileRow = wrapper.find(
            '[data-testid="cockpit-pay-code-mobile-row"]',
        );
        const instructions = mobileRow.find(
            '[data-testid="cockpit-pay-code-mobile-instructions"]',
        );
        const party = mobileRow.find(
            '[data-testid="cockpit-pay-code-mobile-party"]',
        );
        const amount = mobileRow.find(
            '[data-testid="cockpit-pay-code-mobile-amount"]',
        );

        expect(mobileRow.classes()).toContain('space-y-3');
        expect(mobileRow.classes()).toContain('py-3');
        expect(mobileRow.text().match(/Money Changer/g)).toHaveLength(1);
        expect(amount.text()).toBe('₱1,500.75');
        expect(amount.classes()).toContain('text-right');
        expect(instructions.text()).toContain('Mobile-bound');
        expect(instructions.text()).toContain('InstaPay');
        expect(instructions.text()).toContain('OTP');
        expect(instructions.text()).toContain('Signature');
        expect(instructions.text()).not.toContain('+1');
        expect(party.text()).toContain('Claimed by');
        expect(party.text()).toContain('Leslie Chong');
        expect(party.text()).toContain('•••• 4567');
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
                        {
                            ...payCodesReadModel.records[0],
                            code: 'PC-STATUS-PAID-EXPIRED',
                            status: 'paid',
                            display_status: 'paid',
                            voucher_status: 'expired',
                            operational_status: {
                                key: 'paid',
                                label: 'Paid',
                                tone: 'positive',
                                availability_key: 'closed',
                                availability_label: 'Closed',
                                settlement_outcome: 'succeeded',
                                is_terminal: true,
                                can_claim: false,
                                can_retry_payout: false,
                            },
                        },
                        {
                            ...payCodesReadModel.records[0],
                            code: 'PC-STATUS-CANCELLED',
                            status: 'cancelled',
                            display_status: 'cancelled',
                        },
                    ],
                },
            },
        });

        const statusBadges = wrapper.findAll(
            '[data-testid="cockpit-pay-code-status-badge"]',
        );
        const mobileStatusBadges = wrapper.findAll(
            '[data-testid="cockpit-pay-code-mobile-status-badge"]',
        );

        expect(statusBadges).toHaveLength(5);
        expect(statusBadges[0].text()).toBe('Issued');
        expect(statusBadges[0].classes()).toContain('bg-emerald-50');
        expect(statusBadges[1].text()).toBe('Expired');
        expect(statusBadges[1].classes()).toContain('bg-rose-50');
        expect(statusBadges[2].text()).toBe('Awaiting Approval');
        expect(statusBadges[2].classes()).toContain('bg-amber-50');
        expect(mobileStatusBadges[2].text()).toBe('Awaiting Approval');
        expect(statusBadges[3].text()).toBe('Paid');
        expect(statusBadges[3].classes()).toContain('bg-emerald-50');
        expect(statusBadges[4].text()).toBe('Cancelled');
        expect(statusBadges[4].classes()).toContain('bg-slate-100');
        expect(statusBadges[4].classes()).not.toContain('bg-rose-50');
    });

    it('keeps a rejected payout primary while surfacing destination attention', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: {
                    ...payCodesReadModel,
                    records: [
                        {
                            ...payCodesReadModel.records[0],
                            status: 'payout_rejected',
                            display_status: 'payout_rejected',
                            voucher_status: 'expired',
                            operational_status: {
                                key: 'payout_rejected',
                                label: 'Payout Rejected',
                                tone: 'critical',
                                availability_key: 'closed',
                                availability_label: 'Closed',
                                settlement_outcome: 'rejected',
                                is_terminal: true,
                                can_claim: false,
                                can_retry_payout: true,
                            },
                            attention: {
                                key: 'payout_rejected',
                                label: 'Payout rejected',
                                message: 'AC01 (Incorrect account number)',
                                tone: 'critical',
                            },
                        },
                    ],
                },
            },
        });

        expect(
            wrapper.get('[data-testid="cockpit-pay-code-status-badge"]').text(),
        ).toBe('Payout Rejected');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-mobile-status-badge"]')
                .text(),
        ).toBe('Payout Rejected');

        const desktopAttention = wrapper.get(
            '[data-testid="cockpit-pay-code-row-attention"]',
        );
        expect(desktopAttention.text()).toContain('Payout rejected');
        expect(desktopAttention.attributes('title')).toBe(
            'AC01 (Incorrect account number)',
        );

        const mobileAttention = wrapper.get(
            '[data-testid="cockpit-pay-code-mobile-attention"]',
        );
        expect(mobileAttention.text()).toContain('Payout rejected');
        expect(mobileAttention.text()).toContain(
            'AC01 (Incorrect account number)',
        );
    });

    it('renders scan-friendly amount values without mutating amount facts', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const desktopAmounts = wrapper.findAll(
            '[data-testid="cockpit-pay-code-amount"]',
        );
        const mobileAmounts = wrapper.findAll(
            '[data-testid="cockpit-pay-code-mobile-amount"]',
        );

        expect(desktopAmounts).toHaveLength(2);
        expect(desktopAmounts[0].text()).toBe('₱1,500.75');
        expect(desktopAmounts[0].classes()).toContain('text-right');
        expect(desktopAmounts[0].classes()).toContain('font-mono');
        expect(desktopAmounts[0].classes()).toContain('tabular-nums');
        expect(mobileAmounts[0].text()).toBe('₱1,500.75');
        expect(mobileAmounts[0].classes()).toContain('font-mono');
        expect(mobileAmounts[0].classes()).toContain('tabular-nums');
    });

    it('groups desktop capability instructions and party facts into compact scan columns', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const table = wrapper.find(
            '[data-testid="cockpit-pay-code-results-table"] table',
        );
        const identity = wrapper.find(
            '[data-testid="cockpit-pay-code-row-identity"]',
        );
        const instructions = wrapper.find(
            '[data-testid="cockpit-pay-code-instructions"]',
        );
        const party = wrapper.find('[data-testid="cockpit-pay-code-party"]');
        const headers = table.findAll('th');

        expect(table.classes()).toContain('min-w-[68rem]');
        expect(headers).toHaveLength(6);
        expect(headers.map((header) => header.text())).toEqual([
            'Pay Code',
            'Instructions',
            'Amount',
            'Status',
            'Target / Claimed by',
            'Actions',
        ]);
        expect(identity.text()).toContain('PC-HYDRATED-001');
        expect(identity.text()).toContain('Money Changer');
        expect(identity.text()).toContain('Disbursement · Redeemable');
        expect(identity.find('p').classes()).toContain('font-semibold');
        expect(instructions.text()).toContain('Mobile-bound');
        expect(instructions.text()).toContain('InstaPay');
        expect(instructions.text()).toContain('OTP');
        expect(instructions.text()).toContain('Signature');
        expect(instructions.text()).not.toContain('+1');
        expect(party.text()).toContain('Claimed by');
        expect(party.text()).toContain('Leslie Chong');
        expect(party.text()).toContain('•••• 4567');
        const secondaryFacts = wrapper.find(
            '[data-testid="cockpit-pay-code-row-secondary-facts"]',
        );
        expect(secondaryFacts.text()).toContain('Created');
        expect(secondaryFacts.text()).toContain('Expires');
    });

    it('summarizes pay code result density before the rows', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const density = wrapper.find(
            '[data-testid="cockpit-pay-code-results-density-summary"]',
        );
        const scanGuide = wrapper.find(
            '[data-testid="cockpit-pay-code-results-scan-guide"]',
        );

        expect(density.exists()).toBe(true);
        expect(density.classes()).toContain('sm:flex-row');
        expect(density.text()).toContain('Showing');
        expect(density.text()).toContain('2 Pay Codes');
        expect(density.text()).toContain('1–2 of 2');
        expect(density.text()).not.toContain('Links');
        expect(density.text()).not.toContain('Disabled');
        expect(scanGuide.exists()).toBe(false);
    });

    it('keeps result metric values stable while pagination range text changes', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const density = wrapper.find(
            '[data-testid="cockpit-pay-code-results-density-summary"]',
        );
        const range = density.find('.font-mono');

        expect(density.classes()).toContain('sm:flex-row');
        expect(range.classes()).toContain('font-mono');
        expect(range.classes()).toContain('tabular-nums');
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

        const table = wrapper.find(
            '[data-testid="cockpit-pay-code-results-table"]',
        );
        const density = wrapper.find(
            '[data-testid="cockpit-pay-code-results-density-summary"]',
        );
        const pageSize = wrapper.find(
            '[data-testid="cockpit-pay-code-result-page-size"]',
        );
        const footerPagination = wrapper.find(
            '[data-testid="cockpit-pay-code-result-pagination-footer"]',
        );

        expect(table.find('.border-b').classes()).toContain('p-4');
        expect(density.classes()).toContain('sm:flex-row');
        expect(pageSize.exists()).toBe(true);
        expect(footerPagination.text()).toContain('Page 1 of 2');
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

        const density = wrapper.find(
            '[data-testid="cockpit-pay-code-results-density-summary"]',
        );
        const footerPagination = wrapper.find(
            '[data-testid="cockpit-pay-code-result-pagination-footer"]',
        );
        const pageSize = wrapper.find(
            '[data-testid="cockpit-pay-code-result-page-size"]',
        );
        const footerPrevious = wrapper.find(
            '[data-testid="cockpit-pay-code-result-pagination-footer-previous"]',
        );
        const footerNext = wrapper.find(
            '[data-testid="cockpit-pay-code-result-pagination-footer-next"]',
        );

        expect(density.text()).toContain('Showing');
        expect(density.text()).toContain('1–25 of 30');
        expect(density.text()).toContain('30 Pay Codes');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-result-limit-notice"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-result-pagination"]')
                .exists(),
        ).toBe(false);
        expect(footerPagination.exists()).toBe(true);
        expect(footerPagination.text()).toContain('Showing 1–25 of 30');
        expect(footerPagination.text()).toContain('Page 1 of 2');
        expect(pageSize.exists()).toBe(true);
        expect(pageSize.element.tagName.toLowerCase()).toBe('select');
        expect(pageSize.text()).toContain('10 per page');
        expect(pageSize.text()).toContain('25 per page');
        expect(pageSize.text()).toContain('50 per page');
        expect(footerPrevious.attributes('disabled')).toBeDefined();
        expect(footerNext.attributes('disabled')).toBeUndefined();
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(25);
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-mobile-row"]'),
        ).toHaveLength(25);
        expect(wrapper.text()).toContain('PC-VOLUME-025');
        expect(wrapper.text()).not.toContain('PC-VOLUME-026');
        expect(density.text()).not.toContain('Links');
        expect(density.text()).not.toContain('Disabled');
        expect(wrapper.text()).toContain('Search Pay Codes');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');

        await footerNext.trigger('click');

        expect(density.text()).toContain('26–30 of 30');
        expect(footerPagination.text()).toContain('Showing 26–30 of 30');
        expect(footerPagination.text()).toContain('Page 2 of 2');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-pay-code-result-pagination-footer-previous"]',
                )
                .attributes('disabled'),
        ).toBeUndefined();
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-pay-code-result-pagination-footer-next"]',
                )
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(5);
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-mobile-row"]'),
        ).toHaveLength(5);
        expect(wrapper.text()).not.toContain('PC-VOLUME-025');
        expect(wrapper.text()).toContain('PC-VOLUME-026');
        expect(wrapper.text()).toContain('PC-VOLUME-030');

        await wrapper
            .find(
                '[data-testid="cockpit-pay-code-result-pagination-footer-previous"]',
            )
            .trigger('click');

        expect(density.text()).toContain('1–25 of 30');
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

        const density = wrapper.find(
            '[data-testid="cockpit-pay-code-results-density-summary"]',
        );
        const footerPagination = wrapper.find(
            '[data-testid="cockpit-pay-code-result-pagination-footer"]',
        );

        expect(footerPagination.exists()).toBe(true);
        expect(footerPagination.text()).toContain('Showing 1–25 of 30');

        await wrapper
            .find(
                '[data-testid="cockpit-pay-code-result-pagination-footer-next"]',
            )
            .trigger('click');

        expect(density.text()).toContain('26–30 of 30');
        expect(footerPagination.text()).toContain('Showing 26–30 of 30');
        expect(wrapper.text()).toContain('PC-FOOTER-030');

        await wrapper
            .find(
                '[data-testid="cockpit-pay-code-result-pagination-footer-previous"]',
            )
            .trigger('click');

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

        const density = wrapper.find(
            '[data-testid="cockpit-pay-code-results-density-summary"]',
        );
        const pagination = wrapper.find(
            '[data-testid="cockpit-pay-code-result-pagination-footer"]',
        );
        const pageSize = wrapper.find(
            '[data-testid="cockpit-pay-code-result-page-size"]',
        );

        expect(density.text()).toContain('1–25 of 30');
        expect(pagination.text()).toContain('Page 1 of 2');
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(25);

        await pageSize.setValue('10');

        expect(density.text()).toContain('1–10 of 30');
        expect(pagination.text()).toContain('Page 1 of 3');
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(10);
        expect(wrapper.text()).toContain('PC-SIZE-010');
        expect(wrapper.text()).not.toContain('PC-SIZE-011');

        await wrapper
            .find(
                '[data-testid="cockpit-pay-code-result-pagination-footer-next"]',
            )
            .trigger('click');

        expect(density.text()).toContain('11–20 of 30');
        expect(pagination.text()).toContain('Page 2 of 3');

        await pageSize.setValue('50');

        expect(density.text()).toContain('1–30 of 30');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-pay-code-result-pagination-footer"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(30);
        expect(wrapper.text()).toContain('PC-SIZE-030');
    });

    it('summarizes row action safety before the result rows', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        const guidance = wrapper.find(
            '[data-testid="cockpit-pay-code-row-action-guidance"]',
        );
        const items = guidance.findAll(
            '[data-testid="cockpit-pay-code-row-action-guidance-item"]',
        );

        expect(guidance.exists()).toBe(true);
        expect(guidance.element.tagName.toLowerCase()).toBe('details');
        expect(guidance.text()).toContain('Row action guidance');
        expect(guidance.text()).toContain('Governed controls');
        expect(guidance.text()).toContain('Navigation Links');
        expect(guidance.text()).toContain('2');
        expect(guidance.text()).toContain('Blocked Actions');
        expect(guidance.text()).toContain('1');
        expect(guidance.text()).toContain('Rows');
        expect(guidance.text()).toContain('open inspection workspaces');
        expect(guidance.text()).toContain('established governed terminal form');
        expect(guidance.text()).toContain('no provider is called');
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

        expect(wrapper.text()).toContain(
            'No Pay Codes available in the sanitized read model.',
        );
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(0);
    });

    it('forwards route adapter props into the Cockpit Pay Code Explorer page', () => {
        const wrapper = mount(PayCodeExplorerRouteAdapter, {
            props: {
                pay_codes_read_model: payCodesReadModel,
            },
        });

        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('₱1,500.75');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-explorer-shell"]')
                .exists(),
        ).toBe(true);
    });

    it('renders read-only integration status badges from the read model bundle', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                can: {
                    view_technical_details: true,
                },
                pay_codes_read_model: payCodesReadModel,
                read_model: {
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [{ id: 'journal-1' }],
                        redactions: {
                            payloads: 'journal-evidence-summary-only',
                        },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [{ key: 'review' }],
                        redactions: {
                            payloads: 'safe-action-host-summary-only',
                        },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [{ id: 'delivery-1' }],
                        redactions: {
                            payloads: 'communication-delivery-summary-only',
                        },
                    },
                    raw_payload: 'must-not-render',
                },
            },
        });

        expect(wrapper.text()).toContain('Technical details');
        expect(wrapper.text()).toContain('Connected services');
        expect(wrapper.text()).toContain('Journal: available');
        expect(wrapper.text()).toContain('Actions: available');
        expect(wrapper.text()).toContain('Feedback: available');
        expect(wrapper.text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-pay-code-integration-badge"]',
            ),
        ).toHaveLength(3);
    });

    it('keeps explorer integration badges authorization and redaction safe', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                can: {
                    view_technical_details: true,
                },
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

        expect(wrapper.text()).toContain('Technical details');
        expect(wrapper.text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).toContain('safe-action-host-summary-only');
        expect(wrapper.text()).toContain('communication-delivery-summary-only');
        expect(wrapper.text()).not.toContain('SECRET-DO-NOT-RENDER');
        expect(wrapper.text()).not.toContain('RuntimeException');
        expect(wrapper.text()).not.toContain('Stack trace must stay hidden');
        expect(wrapper.text()).not.toContain('/unsafe-action-route');
        expect(wrapper.text()).not.toContain('+639170000000');
    });

    it('omits technical integration details without explicit authorization', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                read_model: {
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [{ id: 'journal-1' }],
                        redactions: {
                            payloads: 'journal-evidence-summary-only',
                        },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [{ key: 'review' }],
                        redactions: {
                            payloads: 'safe-action-host-summary-only',
                        },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [{ id: 'delivery-1' }],
                        redactions: {
                            payloads: 'communication-delivery-summary-only',
                        },
                    },
                    raw_payload: 'must-not-render',
                },
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-technical-details"]')
                .exists(),
        ).toBe(false);
        expect(wrapper.text()).not.toContain('Connected services');
        expect(wrapper.text()).not.toContain('journal-evidence-summary-only');
        expect(wrapper.text()).not.toContain('safe-action-host-summary-only');
        expect(wrapper.text()).not.toContain(
            'communication-delivery-summary-only',
        );
        expect(wrapper.text()).not.toContain('must-not-render');
    });

    it('hydrates read-only operator activity navigation context on the Pay Code Explorer', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                activity_navigation_context: activityNavigationContext,
            },
        });

        expect(wrapper.text()).toContain('Opened from issuance activity');
        expect(wrapper.text()).toContain('Activity context details');
        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('operator_issuance_activity');
        expect(wrapper.text()).toContain('pay_code_explorer');
        expect(wrapper.text()).not.toContain('activity-navigation-read-only');
        expect(wrapper.text()).toContain('activity-navigation-context-only');
        expect(
            wrapper
                .find('[data-testid="cockpit-activity-navigation-context"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('/must-not-render');
    });
});
