import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitBalanceHud from '../../../resources/js/cockpit/components/CockpitBalanceHud.vue';
import CockpitGlobalHeader from '../../../resources/js/cockpit/components/CockpitGlobalHeader.vue';
import CockpitSidebar from '../../../resources/js/cockpit/components/CockpitSidebar.vue';
import CockpitLayout from '../../../resources/js/cockpit/layouts/CockpitLayout.vue';

describe('Cockpit shell layout baseline', () => {
    it('renders the operator shell with global header sidebar and workspace', () => {
        const wrapper = mount(CockpitLayout, {
            props: {
                institution: 'DBP Pay Code',
                operatingIdentity: 'Treasury Operations',
                connectivity: 'Online',
                activeNavigation: 'pay-codes',
                balances: [
                    {
                        key: 'internal',
                        label: 'Internal Balance',
                        value: '₱125,000,000',
                        tone: 'healthy',
                    },
                    {
                        key: 'live',
                        label: 'Live Balance',
                        value: '₱123,500,000',
                        tone: 'warning',
                    },
                ],
            },
            slots: {
                default: '<div data-testid="workspace-content">Operator workspace</div>',
            },
        });

        expect(wrapper.find('[data-testid="cockpit-layout"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-global-header"]').text()).toContain('DBP Pay Code');
        expect(wrapper.find('[data-testid="cockpit-sidebar"]').text()).toContain('Pay Codes');
        expect(wrapper.find('[aria-current="page"]').text()).toContain('Pay Codes');
        expect(wrapper.find('[data-testid="cockpit-workspace"]').text()).toContain('Operator workspace');
    });

    it('renders planned Cockpit navigation items as disabled coming soon entries', () => {
        const wrapper = mount(CockpitSidebar, {
            props: {
                activeKey: 'dashboard',
            },
        });

        const disabledItems = wrapper.findAll('[data-testid="cockpit-nav-item-disabled"]');
        const disabledText = disabledItems.map((item) => item.text()).join(' ');

        expect(disabledItems).toHaveLength(7);
        expect(disabledText).toContain('Funding');
        expect(disabledText).toContain('Operations');
        expect(disabledText).toContain('Coming soon');

        for (const item of disabledItems) {
            expect(item.attributes('aria-disabled')).toBe('true');
            expect(item.attributes('href')).toBeUndefined();
        }
    });

    it('hydrates the global balance HUD from the shared cockpit header read model', () => {
        const wrapper = mount(CockpitLayout, {
            props: {
                cockpitHeaderReadModel: {
                    schema: 'x-change.cockpit.header-read-model.v1',
                    authorized: true,
                    read_only: true,
                    balances: [
                        {
                            key: 'internal',
                            label: 'Internal Balance',
                            value: '₱9,876.50',
                            tone: 'healthy',
                        },
                        {
                            key: 'outstanding',
                            label: 'Outstanding Pay Codes',
                            value: '₱25.00',
                            tone: 'warning',
                        },
                        {
                            key: 'usable',
                            label: 'Usable Balance',
                            value: '₱9,851.50',
                            tone: 'healthy',
                        },
                        {
                            key: 'live',
                            label: 'Live Balance',
                            value: 'Provider balance not connected',
                            tone: 'neutral',
                        },
                    ],
                },
            },
        });

        const header = wrapper.find('[data-testid="cockpit-global-header"]');

        expect(header.text()).toContain('₱9,876.50');
        expect(header.text()).toContain('₱25.00');
        expect(header.text()).toContain('₱9,851.50');
        expect(header.text()).toContain('Provider balance not connected');
        expect(header.text()).not.toContain('Internal balance not connected');
    });

    it('renders balance metrics as header HUD presentation only', () => {
        const wrapper = mount(CockpitGlobalHeader, {
            props: {},
        });

        expect(wrapper.find('[data-testid="cockpit-balance-hud"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Internal Balance');
        expect(wrapper.text()).toContain('Internal balance not connected');
        expect(wrapper.text()).toContain('Live Balance');
        expect(wrapper.text()).toContain('Provider balance not connected');
        expect(wrapper.text()).not.toContain('Summary not connected');
        expect(wrapper.text()).not.toContain('Provider not connected');
        expect(wrapper.text()).toContain('Operating as: Treasury Operations');
        expect(wrapper.find('[data-testid="cockpit-balance-hud"]').classes()).toContain('xl:min-w-[44rem]');
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
        expect(wrapper.findAll('[data-testid="cockpit-balance-metric"]')).toHaveLength(1);
    });

    it('centers single-line labels and values in width-aware balance columns', () => {
        const wrapper = mount(CockpitBalanceHud, {
            props: {
                balances: [
                    {
                        key: 'internal',
                        label: 'Internal Balance',
                        value: '₱8,241.70',
                    },
                    {
                        key: 'outstanding',
                        label: 'Outstanding Pay Codes',
                        value: '₱0.00',
                    },
                ],
            },
        });

        const labelRows = wrapper.findAll('[data-testid="cockpit-balance-label"]');
        const valueRows = wrapper.findAll('[data-testid="cockpit-balance-value"]');
        const hud = wrapper.find('[data-testid="cockpit-balance-hud"]');

        expect(labelRows).toHaveLength(2);
        expect(valueRows).toHaveLength(2);
        expect(hud.classes()).toContain('xl:grid-cols-[4fr_6fr_4fr_8fr]');

        for (const labelRow of labelRows) {
            expect(labelRow.classes()).toContain('text-center');
            expect(labelRow.classes()).toContain('whitespace-nowrap');
        }

        for (const valueRow of valueRows) {
            expect(valueRow.classes()).toContain('text-center');
            expect(valueRow.classes()).toContain('whitespace-nowrap');
        }
    });
});
