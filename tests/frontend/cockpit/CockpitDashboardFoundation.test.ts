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

        expect(wrapper.text()).toContain('Funding readiness');
        expect(wrapper.text()).toContain('Outstanding Pay Codes and Usable Balance are bridge estimates');
        expect(wrapper.text()).toContain('Cockpit does not reserve, release, capture, refund, or move money here.');
        expect(wrapper.text()).toContain('Bridge estimates');
        expect(wrapper.text()).toContain('Treasury facts deferred');
        expect(wrapper.text()).toContain('Accounting');
        expect(wrapper.text()).toContain('Liability');
        expect(wrapper.text()).toContain('Estimate');
        expect(wrapper.text()).toContain('External');
        expect(wrapper.text()).toContain('Internal Balance');
        expect(wrapper.text()).toContain('Provider Liquidity');
        expect(wrapper.text()).toContain('Outstanding Pay Codes');
        expect(wrapper.text()).toContain('Usable Balance');
        expect(wrapper.text()).toContain('Bridge estimate; no fund release');
        expect(wrapper.text()).toContain('Bridge estimate; Internal Balance minus Outstanding Pay Codes');
        expect(wrapper.text()).not.toContain('Balance summary not connected');
        expect(wrapper.text()).not.toContain('Read model pending');
        expect(wrapper.find('[data-testid="cockpit-funding-density-summary"]').text()).toContain('Funding Facts');
        expect(wrapper.find('[data-testid="cockpit-funding-density-summary"]').text()).toContain('Money Movement');
        expect(wrapper.find('[data-testid="cockpit-funding-density-summary"]').text()).toContain('Disabled');
        expect(wrapper.find('[data-testid="cockpit-funding-semantics"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-dashboard-metric-card"]')).toHaveLength(4);
    });

    it('renders the redemption pipeline as lifecycle visibility only', () => {
        const wrapper = mount(CockpitRedemptionPipeline, {
            props: {
                stages: cockpitRedemptionPipelineStages,
            },
        });

        expect(wrapper.text()).toContain('Claim lifecycle summary');
        expect(wrapper.text()).toContain('This panel does not approve, redeem, execute, or reconcile Pay Codes.');
        expect(wrapper.text()).toContain('Issued');
        expect(wrapper.text()).toContain('Claim Started');
        expect(wrapper.text()).toContain('Reconciled');
        expect(wrapper.text()).toContain('Read-only');
        expect(wrapper.find('[data-testid="cockpit-pipeline-density-summary"]').text()).toContain('Claim Facts');
        expect(wrapper.find('[data-testid="cockpit-pipeline-density-summary"]').text()).toContain('Active Counts');
        expect(wrapper.find('[data-testid="cockpit-pipeline-density-summary"]').text()).toContain('Not run here');
        expect(wrapper.findAll('[data-testid="cockpit-pipeline-stage"]')).toHaveLength(7);
    });

    it('renders risk and expiry signals without performing workflow actions', () => {
        const wrapper = mount(CockpitRiskExpiryPanel, {
            props: {
                signals: cockpitRiskSignals,
            },
        });

        expect(wrapper.text()).toContain('Items that may need attention');
        expect(wrapper.text()).toContain('Review actions remain outside this dashboard.');
        expect(wrapper.text()).toContain('Expiring Today');
        expect(wrapper.text()).toContain('Funding Runway');
        expect(wrapper.text()).toContain('Stuck Settlements');
        expect(wrapper.find('[data-testid="cockpit-risk-density-summary"]').text()).toContain('Signals');
        expect(wrapper.find('[data-testid="cockpit-risk-density-summary"]').text()).toContain('Highest Severity');
        expect(wrapper.find('[data-testid="cockpit-risk-density-summary"]').text()).toContain('Critical');
        expect(wrapper.findAll('[data-testid="cockpit-risk-signal"]')).toHaveLength(3);
    });

    it('renders recent activity placeholders from supplied facts only', () => {
        const wrapper = mount(CockpitRecentActivityPanel, {
            props: {
                items: cockpitRecentActivityItems,
            },
        });

        expect(wrapper.text()).toContain('System Activity');
        expect(wrapper.text()).toContain('Recent operating evidence');
        expect(wrapper.text()).toContain('does not execute follow-up work');
        expect(wrapper.text()).toContain('Settlement activity');
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
        expect(wrapper.text()).toContain('Summary evidence available');
        expect(wrapper.text()).not.toContain('durable_summary_evidence_available');
        expect(wrapper.text()).toContain('Action and feedback statuses are projected from x-journal execution.handoff.summary.recorded.');
        expect(wrapper.text()).toContain('Read from: journal, action, feedback, handoff summary');
    });
});
