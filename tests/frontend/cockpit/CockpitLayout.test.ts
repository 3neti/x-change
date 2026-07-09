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

    it('renders balance metrics as header HUD presentation only', () => {
        const wrapper = mount(CockpitGlobalHeader, {
            props: {
                balances: [
                    {
                        key: 'reserved',
                        label: 'Reserved Funds',
                        value: '₱4,500,000',
                        tone: 'warning',
                    },
                ],
            },
        });

        expect(wrapper.find('[data-testid="cockpit-balance-hud"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Reserved Funds');
        expect(wrapper.text()).toContain('₱4,500,000');
        expect(wrapper.text()).toContain('Operating as: Treasury Operations');
    });

    it('keeps the balance HUD as supplied read-model text', () => {
        const wrapper = mount(CockpitBalanceHud, {
            props: {
                balances: [
                    {
                        key: 'available',
                        label: 'Available To Issue',
                        value: 'Pending read model',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Available To Issue');
        expect(wrapper.text()).toContain('Pending read model');
        expect(wrapper.findAll('[data-testid="cockpit-balance-metric"]')).toHaveLength(1);
    });
});
