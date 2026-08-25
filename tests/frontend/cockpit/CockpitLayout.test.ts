import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitBalanceHud from '../../../resources/js/cockpit/components/CockpitBalanceHud.vue';
import CockpitGlobalHeader from '../../../resources/js/cockpit/components/CockpitGlobalHeader.vue';
import CockpitLayout from '../../../resources/js/cockpit/layouts/CockpitLayout.vue';
import { cockpitNavigationItems } from '../../../resources/js/cockpit/navigation';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    router: { reload: vi.fn() },
}));
vi.mock('@laravel/echo-vue', () => ({
    useEcho: vi.fn(),
}));

afterEach(() => {
    vi.clearAllMocks();
    vi.useRealTimers();
});

describe('Cockpit shell layout baseline', () => {
    it('refreshes the shared balance after a voucher collection settles', async () => {
        vi.useFakeTimers();
        mount(CockpitLayout, {
            props: {
                cockpitHeaderReadModel: {
                    schema: 'x-change.cockpit.header-read-model.v2',
                    authorized: true,
                    read_only: true,
                    operating_identity: 'Account holder',
                    balances: [],
                    funding_realtime: {
                        enabled: true,
                        channel: 'private-balance',
                        event: '.FundingProjectionChanged',
                    },
                },
            },
        });

        const listener = vi.mocked(useEcho).mock.calls[0]?.[2] as
            | ((event: Record<string, string>) => void)
            | undefined;
        expect(listener).toBeTypeOf('function');

        listener?.({
            schema: 'x-change.funding-projection-changed.v1',
            event_id: 'event-1',
            reason: 'voucher_collection_settled',
            occurred_at: '2026-08-25T09:00:00Z',
        });
        await vi.advanceTimersByTimeAsync(151);

        expect(router.reload).toHaveBeenCalledWith({
            only: ['cockpit_header_read_model'],
        });
    });

    it('renders the operator workspace without a nested application sidebar', () => {
        const wrapper = mount(CockpitLayout, {
            props: {
                institution: 'DBP Pay Code',
                operatingIdentity: 'Treasury Operations',
                connectivity: 'Online',
                activeNavigation: 'pay-codes',
                balances: [
                    {
                        key: 'internal',
                        label: 'Client Funds',
                        value: '₱125,000,000',
                        tone: 'healthy',
                        amount_minor: 12_500_000_000,
                    },
                    {
                        key: 'live',
                        label: 'NetBank Liquidity',
                        value: '₱123,500,000',
                        tone: 'warning',
                        amount_minor: 12_350_000_000,
                    },
                ],
            },
            slots: {
                default:
                    '<div data-testid="workspace-content">Operator workspace</div>',
            },
        });

        expect(wrapper.find('[data-testid="cockpit-layout"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('[data-testid="cockpit-global-header"]').text(),
        ).toContain('DBP Pay Code');
        expect(wrapper.find('[data-testid="cockpit-sidebar"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.find('[data-testid="cockpit-workspace"]').text(),
        ).toContain('Operator workspace');
        expect(
            wrapper.find('[data-testid="cockpit-theme-picker"]').exists(),
        ).toBe(true);
        expect(
            wrapper.get('[data-testid="cockpit-global-header"]').element
                .parentElement?.classList,
        ).toContain('md:block');
        expect(
            wrapper.get('[data-testid="cockpit-mobile-tab-bar"]').classes(),
        ).toContain('md:hidden');
        expect(
            wrapper
                .get('[data-testid="cockpit-mobile-tab-pay-codes"]')
                .attributes('aria-current'),
        ).toBe('page');
        expect(
            wrapper.get('[data-testid="cockpit-workspace"]').classes(),
        ).toEqual(expect.arrayContaining(['pb-24', 'md:pb-4']));
    });

    it('uses concise task language without changing the established endpoints', () => {
        expect(
            cockpitNavigationItems.every((item) => item.enabled !== false),
        ).toBe(true);
        expect(cockpitNavigationItems.map((item) => item.label)).toEqual([
            'Funding',
            'Issuance',
            'Campaigns',
            'Pay Codes',
            'Overview',
            'Account',
            'System Readiness',
            'Guides',
        ]);
        expect(cockpitNavigationItems.map((item) => item.href)).toEqual([
            '/x/cockpit/funding',
            '/x/cockpit/quick-generate',
            '/x/cockpit/campaigns',
            '/x/cockpit/pay-codes',
            '/x/cockpit/overview',
            '/x/cockpit/accounts',
            '/x/cockpit/diagnostics/runtime-profile',
            '/x/cockpit/documentation',
        ]);
        expect(cockpitNavigationItems.map((item) => item.description)).toEqual([
            'Add and confirm funds',
            'Design and issue a Pay Code',
            'Issue to many recipients',
            'Find and manage Pay Codes',
            'Funds, capacity, and activity',
            'Position and connected services',
            'Deployment and runtime checks',
            'Workflows and terminology',
        ]);
    });

    it('hydrates the global balance HUD from the shared cockpit header read model', async () => {
        const wrapper = mount(CockpitLayout, {
            props: {
                cockpitHeaderReadModel: {
                    schema: 'x-change.cockpit.header-read-model.v2',
                    authorized: true,
                    read_only: true,
                    operating_identity: 'Account holder',
                    balances: [
                        {
                            key: 'internal',
                            label: 'Client Funds',
                            value: '₱9,876.50',
                            tone: 'healthy',
                            amount_minor: 987_650,
                        },
                        {
                            key: 'outstanding',
                            label: 'Outstanding Pay Codes',
                            value: '₱25.00',
                            tone: 'warning',
                            amount_minor: 2_500,
                        },
                        {
                            key: 'issuance',
                            label: 'Issuance Capacity',
                            value: '₱9,851.50',
                            tone: 'healthy',
                            amount_minor: 985_150,
                        },
                    ],
                },
            },
        });

        const header = wrapper.find('[data-testid="cockpit-global-header"]');
        const visibilityToggle = wrapper.find(
            '[data-testid="cockpit-balance-visibility-toggle"]',
        );

        expect(header.text()).not.toContain('₱9,876.50');
        expect(header.text()).not.toContain('₱25.00');
        expect(header.text()).not.toContain('₱9,851.50');
        expect(header.text()).toContain('••••••');
        expect(visibilityToggle.attributes('aria-label')).toBe(
            'Show balance values',
        );
        expect(visibilityToggle.attributes('aria-pressed')).toBe('false');
        expect(header.text()).toContain('Issuance Capacity');
        expect(header.text()).toContain('Operating as: Account holder');
        expect(header.text()).not.toContain('Provider Liquidity');
        expect(header.text()).not.toContain('Client Funds not connected');

        for (const metric of wrapper.findAll(
            '[data-testid="cockpit-balance-metric"]',
        )) {
            expect(metric.attributes('aria-label')).toContain('Value hidden');
            expect(metric.attributes('aria-label')).not.toContain('₱');
        }

        await visibilityToggle.trigger('click');

        expect(header.text()).toContain('₱9,876.50');
        expect(header.text()).toContain('₱25.00');
        expect(header.text()).toContain('₱9,851.50');
        expect(visibilityToggle.attributes('aria-label')).toBe(
            'Hide balance values',
        );
        expect(visibilityToggle.attributes('aria-pressed')).toBe('true');
    });

    it('explains a smart entry redirect without exposing financial values', () => {
        const wrapper = mount(CockpitLayout, {
            props: {
                cockpitEntryNotice: {
                    schema: 'x-change.cockpit.entry-notice.v1',
                    destination: 'funding',
                    title: 'Start with Funding',
                    message:
                        'Add funds to increase your Issuance Capacity before creating a Pay Code.',
                    read_only: true,
                },
            },
        });

        const notice = wrapper.get('[data-testid="cockpit-entry-notice"]');

        expect(notice.attributes('role')).toBe('status');
        expect(notice.text()).toContain('Start with Funding');
        expect(notice.text()).toContain('Issuance Capacity');
        expect(notice.text()).not.toContain('₱');
        expect(notice.classes()).not.toContain('hidden');
    });

    it('renders balance metrics as header HUD presentation only', () => {
        const wrapper = mount(CockpitGlobalHeader, {
            props: {},
        });

        expect(
            wrapper.find('[data-testid="cockpit-balance-hud"]').exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Client Funds');
        expect(wrapper.text()).toContain('Client Funds not connected');
        expect(wrapper.text()).toContain('Issuance Capacity');
        expect(wrapper.text()).toContain('Not available');
        expect(wrapper.text()).not.toContain('Provider Liquidity');
        expect(wrapper.text()).not.toContain('Summary not connected');
        expect(wrapper.text()).not.toContain('Provider not connected');
        expect(wrapper.text()).toContain('Operating as: Account holder');
        expect(wrapper.text()).not.toContain('Settlement Operating System');
        expect(
            wrapper.get('[data-testid="cockpit-global-header-primary"]').text(),
        ).toContain('Operating as: Account holder');
        expect(
            wrapper.find('[data-testid="cockpit-balance-hud"]').classes(),
        ).not.toContain('xl:min-w-[44rem]');
    });

    it('keeps the balance HUD as supplied summary text', () => {
        const wrapper = mount(CockpitBalanceHud, {
            props: {
                balances: [
                    {
                        key: 'available',
                        label: 'Available To Issue',
                        value: 'Summary not connected',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Available To Issue');
        expect(wrapper.text()).toContain('Summary not connected');
        expect(
            wrapper.findAll('[data-testid="cockpit-balance-metric"]'),
        ).toHaveLength(1);
    });

    it('keeps the three primary balance values in one muted inline strip', () => {
        const wrapper = mount(CockpitBalanceHud, {
            props: {
                valuesVisible: true,
                balances: [
                    {
                        key: 'internal',
                        label: 'Client Funds',
                        value: '₱8,241.70',
                        amount_minor: 824_170,
                    },
                    {
                        key: 'outstanding',
                        label: 'Outstanding Pay Codes',
                        value: '₱0.00',
                        amount_minor: 0,
                    },
                    {
                        key: 'issuance',
                        label: 'Issuance Capacity',
                        value: '₱8,241.70',
                        amount_minor: 824_170,
                    },
                ],
            },
        });

        const labelRows = wrapper.findAll(
            '[data-testid="cockpit-balance-label"]',
        );
        const valueRows = wrapper.findAll(
            '[data-testid="cockpit-balance-value"]',
        );
        const hud = wrapper.find('[data-testid="cockpit-balance-hud"]');
        const strip = wrapper.find('[data-testid="cockpit-balance-strip"]');

        expect(labelRows).toHaveLength(3);
        expect(valueRows).toHaveLength(3);
        expect(hud.classes()).toContain('max-w-full');
        expect(hud.classes()).toContain('min-w-0');
        expect(hud.classes()).toContain('overflow-x-auto');
        expect(hud.classes()).not.toContain('border');
        expect(hud.classes()).not.toContain('shadow-sm');
        expect(strip.classes()).toContain('flex');
        expect(strip.classes()).toContain('min-w-max');
        expect(strip.classes()).toContain('whitespace-nowrap');
        expect(
            wrapper.findAll('[data-testid="cockpit-balance-separator"]'),
        ).toHaveLength(2);
        expect(wrapper.text()).toContain('₱8,241.70');

        for (const labelRow of labelRows) {
            expect(labelRow.classes()).toContain('font-normal');
            expect(labelRow.classes()).toContain('text-muted-foreground');
        }

        for (const valueRow of valueRows) {
            expect(valueRow.classes()).toContain('font-medium');
            expect(valueRow.classes()).not.toContain('font-bold');
        }
    });

    it('renders authorized provider liquidity dynamically and masks it with the other values', async () => {
        const wrapper = mount(CockpitGlobalHeader, {
            props: {
                balances: [
                    {
                        key: 'internal',
                        label: 'Client Funds',
                        value: '₱8,241.70',
                        amount_minor: 824_170,
                    },
                    {
                        key: 'outstanding',
                        label: 'Outstanding Pay Codes',
                        value: '₱500.00',
                        amount_minor: 50_000,
                    },
                    {
                        key: 'issuance',
                        label: 'Issuance Capacity',
                        value: '₱7,741.70',
                        amount_minor: 774_170,
                    },
                    {
                        key: 'live',
                        label: 'NetBank Liquidity',
                        value: 'Stale · ₱9,000.00',
                        amount_minor: 900_000,
                        tone: 'warning',
                    },
                ],
            },
        });

        const providerMetric = wrapper
            .findAll('[data-testid="cockpit-balance-metric"]')
            .find((metric) => metric.text().includes('NetBank Liquidity'));

        expect(providerMetric).toBeDefined();
        expect(providerMetric?.classes()).toContain('inline-flex');
        expect(providerMetric?.classes()).not.toContain('col-span-3');
        expect(providerMetric?.classes()).not.toContain('xl:col-span-1');
        expect(providerMetric?.text()).not.toContain('₱9,000.00');
        expect(providerMetric?.text()).toContain('Stale · ••••••');
        expect(providerMetric?.attributes('aria-label')).not.toContain(
            '₱9,000.00',
        );
        expect(providerMetric?.attributes('aria-label')).toContain(
            'Stale. Value hidden',
        );

        await wrapper
            .find('[data-testid="cockpit-balance-visibility-toggle"]')
            .trigger('click');

        expect(providerMetric?.text()).toContain('Stale · ₱9,000.00');
        expect(
            wrapper.find('[data-testid="cockpit-balance-hud"]').classes(),
        ).not.toContain('xl:grid-cols-4');
    });

    it('contains the single-line balance strip at narrow viewport widths', () => {
        const wrapper = mount(CockpitGlobalHeader, {
            props: {
                institution: 'DBP Pay Code',
                operatingIdentity: 'Treasury Operations',
                balances: [
                    {
                        key: 'internal',
                        label: 'Client Funds',
                        value: '₱8,241.70',
                        amount_minor: 824_170,
                    },
                    {
                        key: 'outstanding',
                        label: 'Outstanding Pay Codes',
                        value: '₱500.00',
                        amount_minor: 50_000,
                    },
                    {
                        key: 'issuance',
                        label: 'Issuance Capacity',
                        value: '₱7,741.70',
                        amount_minor: 774_170,
                    },
                ],
            },
        });
        const header = wrapper.get('[data-testid="cockpit-global-header"]');
        const primary = wrapper.get(
            '[data-testid="cockpit-global-header-primary"]',
        );
        const hud = wrapper.get('[data-testid="cockpit-balance-hud"]');

        expect(header.classes()).toContain('px-4');
        expect(primary.classes()).toContain('flex-wrap');
        expect(primary.classes()).toContain('min-w-0');
        expect(hud.classes()).toContain('max-w-full');
        expect(hud.classes()).toContain('overflow-x-auto');
        expect(
            hud.get('[data-testid="cockpit-balance-strip"]').classes(),
        ).toContain('whitespace-nowrap');
    });
});
