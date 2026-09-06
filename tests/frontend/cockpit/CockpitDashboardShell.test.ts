import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitDashboard from '../../../resources/js/cockpit/pages/Dashboard.vue';

describe('Cockpit dashboard shell baseline', () => {
    it('renders the operating controls and horizon inside the Cockpit shell', () => {
        const wrapper = mount(CockpitDashboard);

        expect(
            wrapper.find('[data-testid="cockpit-dashboard-shell"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="cockpit-page-heading"]').text(),
        ).toContain('Cockpit');
        expect(
            wrapper.find('[data-testid="cockpit-page-heading"]').classes(),
        ).toEqual(expect.arrayContaining(['hidden', 'md:flex']));
        expect(
            wrapper.find('[data-testid="cockpit-controls-panel"]').text(),
        ).toContain('Choose a workspace.');
        expect(
            wrapper.find('[data-testid="cockpit-operational-horizon"]').text(),
        ).toContain('Current work at a glance.');
        expect(
            wrapper.find('[data-testid="cockpit-attention-panel"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-recently-claimed-spotlight"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="cockpit-recent-log"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="cockpit-getting-started"]').exists(),
        ).toBe(true);
        expect(
            wrapper.findAll('[data-testid="cockpit-control-link"]'),
        ).toHaveLength(5);
        expect(
            wrapper.findAll('[data-testid="cockpit-horizon-item"]'),
        ).toHaveLength(4);
        expect(
            wrapper.find('[data-testid="cockpit-liquidity-hero"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-redemption-pipeline"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="cockpit-risk-expiry-panel"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-recent-activity-panel"]')
                .exists(),
        ).toBe(true);
    });

    it('keeps the primary Cockpit free of technical boundary chatter and mutations', () => {
        const wrapper = mount(CockpitDashboard);

        const heading = wrapper.find('[data-testid="cockpit-page-heading"]');
        const controls = wrapper.find('[data-testid="cockpit-controls-panel"]');

        expect(heading.text()).not.toContain('does not');
        expect(controls.text()).not.toContain('read model');
        expect(controls.text()).not.toContain('provider');
        expect(
            wrapper.find('[data-testid="cockpit-dashboard-mutation"]').exists(),
        ).toBe(false);
    });

    it('renders read-model placeholder data without calling host integrations', () => {
        const wrapper = mount(CockpitDashboard);

        expect(
            wrapper.findAll('[data-testid="cockpit-dashboard-metric-card"]'),
        ).toHaveLength(4);
        expect(
            wrapper.findAll('[data-testid="cockpit-pipeline-stage"]'),
        ).toHaveLength(7);
        expect(
            wrapper.findAll('[data-testid="cockpit-risk-signal"]'),
        ).toHaveLength(3);
        expect(
            wrapper.findAll('[data-testid="cockpit-activity-item"]'),
        ).toHaveLength(3);
        expect(wrapper.text()).toContain('Summary not connected');
        expect(wrapper.text()).toContain('no provider call from dashboard');
        expect(wrapper.text()).toContain(
            'Audit facts require authorization and redaction before display.',
        );
        expect(wrapper.text()).toContain('No claims yet today');
    });
});
