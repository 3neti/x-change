import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitDashboard from '../../../resources/js/cockpit/pages/Dashboard.vue';

describe('Cockpit dashboard shell baseline', () => {
    it('renders the placeholder dashboard inside the Cockpit shell', () => {
        const wrapper = mount(CockpitDashboard);

        expect(wrapper.find('[data-testid="cockpit-dashboard-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Cockpit Dashboard Shell');
        expect(wrapper.text()).toContain('Liquidity Center');
        expect(wrapper.text()).toContain('Redemption Pipeline');
        expect(wrapper.text()).toContain('Risk and Expiry');
        expect(wrapper.text()).toContain('Recent Activity');
        expect(wrapper.findAll('[data-testid="cockpit-dashboard-placeholder"]')).toHaveLength(4);
    });

    it('states the shell boundary without adding domain side effects', () => {
        const wrapper = mount(CockpitDashboard);

        expect(wrapper.text()).toContain('does not execute vouchers');
        expect(wrapper.text()).toContain('write journal entries');
        expect(wrapper.text()).toContain('send feedback');
        expect(wrapper.text()).toContain('move money');
    });
});

