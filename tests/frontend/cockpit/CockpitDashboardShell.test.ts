import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitDashboard from '../../../resources/js/cockpit/pages/Dashboard.vue';

describe('Cockpit dashboard shell baseline', () => {
    it('renders the dashboard foundation inside the Cockpit shell', () => {
        const wrapper = mount(CockpitDashboard);

        expect(wrapper.find('[data-testid="cockpit-dashboard-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Settlement OS Operating Overview');
        expect(wrapper.text()).toContain('Liquidity Center');
        expect(wrapper.text()).toContain('Redemption Pipeline');
        expect(wrapper.text()).toContain('Risk and Expiry');
        expect(wrapper.text()).toContain('Settlement Activity');
        expect(wrapper.find('[data-testid="cockpit-liquidity-hero"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-redemption-pipeline"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-risk-expiry-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-recent-activity-panel"]').exists()).toBe(true);
    });

    it('states the shell boundary without adding domain side effects', () => {
        const wrapper = mount(CockpitDashboard);

        expect(wrapper.text()).toContain('does not execute vouchers');
        expect(wrapper.text()).toContain('write journal entries');
        expect(wrapper.text()).toContain('send feedback');
        expect(wrapper.text()).toContain('call providers');
        expect(wrapper.text()).toContain('move money');
    });

    it('renders read-model placeholder data without calling host integrations', () => {
        const wrapper = mount(CockpitDashboard);

        expect(wrapper.findAll('[data-testid="cockpit-dashboard-metric-card"]')).toHaveLength(4);
        expect(wrapper.findAll('[data-testid="cockpit-pipeline-stage"]')).toHaveLength(7);
        expect(wrapper.findAll('[data-testid="cockpit-risk-signal"]')).toHaveLength(3);
        expect(wrapper.findAll('[data-testid="cockpit-activity-item"]')).toHaveLength(3);
        expect(wrapper.text()).toContain('Pending wallet read model');
        expect(wrapper.text()).toContain('No provider call in Slice 2');
        expect(wrapper.text()).toContain('Journal facts require authorization and redaction before display.');
    });
});
