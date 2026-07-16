import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitLiquidityHero from '../../../resources/js/cockpit/components/CockpitLiquidityHero.vue';
import CockpitRecentActivityPanel from '../../../resources/js/cockpit/components/CockpitRecentActivityPanel.vue';
import CockpitRedemptionPipeline from '../../../resources/js/cockpit/components/CockpitRedemptionPipeline.vue';
import CockpitRiskExpiryPanel from '../../../resources/js/cockpit/components/CockpitRiskExpiryPanel.vue';
import {
    cockpitDashboardMetrics,
    cockpitRecentActivityItems,
    cockpitRedemptionPipelineStages,
    cockpitRiskSignals,
} from '../../../resources/js/cockpit/dashboardDefaults';

describe('Cockpit dashboard foundation widgets', () => {
    it('renders the liquidity hero with read-only balance metrics', () => {
        const wrapper = mount(CockpitLiquidityHero, {
            props: {
                metrics: cockpitDashboardMetrics,
            },
        });

        expect(wrapper.text()).toContain('Money position comes first');
        expect(wrapper.text()).toContain('Internal Balance');
        expect(wrapper.text()).toContain('Live Balance');
        expect(wrapper.text()).toContain('Reserved Funds');
        expect(wrapper.text()).toContain('Available To Issue');
        expect(wrapper.findAll('[data-testid="cockpit-dashboard-metric-card"]')).toHaveLength(4);
    });

    it('renders the redemption pipeline as lifecycle visibility only', () => {
        const wrapper = mount(CockpitRedemptionPipeline, {
            props: {
                stages: cockpitRedemptionPipelineStages,
            },
        });

        expect(wrapper.text()).toContain('Redemption status');
        expect(wrapper.text()).toContain('Issued');
        expect(wrapper.text()).toContain('Claim Started');
        expect(wrapper.text()).toContain('Reconciled');
        expect(wrapper.text()).toContain('No execution');
        expect(wrapper.findAll('[data-testid="cockpit-pipeline-stage"]')).toHaveLength(7);
    });

    it('renders risk and expiry signals without performing workflow actions', () => {
        const wrapper = mount(CockpitRiskExpiryPanel, {
            props: {
                signals: cockpitRiskSignals,
            },
        });

        expect(wrapper.text()).toContain('Risk signals');
        expect(wrapper.text()).toContain('Expiring Today');
        expect(wrapper.text()).toContain('Funding Runway');
        expect(wrapper.text()).toContain('Stuck Settlements');
        expect(wrapper.findAll('[data-testid="cockpit-risk-signal"]')).toHaveLength(3);
    });

    it('renders recent activity placeholders from supplied facts only', () => {
        const wrapper = mount(CockpitRecentActivityPanel, {
            props: {
                items: cockpitRecentActivityItems,
            },
        });

        expect(wrapper.text()).toContain('Execution evidence');
        expect(wrapper.text()).toContain('Execution activity');
        expect(wrapper.text()).toContain('Journal activity');
        expect(wrapper.text()).toContain('Feedback activity');
        expect(wrapper.text()).toContain('Delivery status is communication state, not lifecycle truth.');
        expect(wrapper.findAll('[data-testid="cockpit-activity-item"]')).toHaveLength(3);
    });

    it('renders durable execution handoff summary projection evidence when supplied', () => {
        const wrapper = mount(CockpitRecentActivityPanel, {
            props: {
                items: [
                    {
                        id: 'execution-exec-playwright-projection',
                        label: 'Execution recorded for PC-PROJECTION',
                        description: 'settlement_envelope succeeded · exec-playwright-projection',
                        timestamp: '2026-07-15T10:00:00+08:00',
                        source: 'execution',
                        projection_badge: 'Durable summary evidence',
                        projection_status: 'durable_summary_evidence_available',
                        projection_detail: 'Action and feedback statuses are projected from x-journal execution.handoff.summary.recorded.',
                        projection_targets: ['journal', 'action', 'feedback', 'handoff_summary_journal'],
                    },
                ],
            },
        });

        expect(wrapper.find('[data-testid="cockpit-activity-projection-status"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Durable summary evidence');
        expect(wrapper.text()).toContain('durable_summary_evidence_available');
        expect(wrapper.text()).toContain('Action and feedback statuses are projected from x-journal execution.handoff.summary.recorded.');
        expect(wrapper.text()).toContain('Targets: journal, action, feedback, handoff_summary_journal');
    });
});
