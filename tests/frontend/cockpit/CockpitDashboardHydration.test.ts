import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitDashboard from '../../../resources/js/cockpit/pages/Dashboard.vue';
import DashboardRouteAdapter from '../../../resources/js/pages/x-change/cockpit/Dashboard.vue';

const dashboardReadModel = {
    status: 'available',
    authorized: true,
    metrics: [
        {
            key: 'pay-codes-visible',
            label: 'Pay Codes Visible',
            value: '4',
            helper: 'Sanitized voucher lifecycle list rows',
            tone: 'neutral',
        },
    ],
    pipeline: [
        {
            key: 'issued',
            label: 'Issued',
            value: '1',
            tone: 'neutral',
        },
    ],
    risk_signals: [
        {
            key: 'awaiting-approval',
            label: 'Awaiting Approval',
            value: '1 sanitized summaries',
            severity: 'watch',
        },
    ],
    activity: [
        {
            id: 'PC-AWAITING-001',
            label: 'PC-AWAITING-001',
            description: 'Status: awaiting_approval',
            timestamp: '2026-07-03T13:00:00+08:00',
            source: 'system',
            provider_payload: 'must-not-render',
        },
    ],
    redactions: {
        payloads: 'sanitized-dashboard-summary-only',
    },
    provider_payload: 'must-not-render',
    raw_payload: 'must-not-render',
    wallet: 'must-not-render',
};

const campaignReadModel = {
    schema: 'x-change.cockpit.campaign-adoption.v1',
    status: 'available',
    authorized: true,
    source: 'x-campaign',
    surfaces: [
        {
            key: 'campaign_dashboard',
            status: 'available',
            enabled: true,
            read_only: true,
            reason: 'x-campaign-read-model-available',
        },
        {
            key: 'attachment_operator_workspace',
            status: 'available',
            enabled: true,
            read_only: true,
            reason: 'x-campaign-read-model-available',
        },
    ],
    facts: {
        planning_key: 'campaign-plan-1',
        execution_id: 'execution-1',
        cards: {
            campaign: {
                name: 'Food Aid July',
                recipient_count: 250,
                secret: 'must-not-render',
            },
        },
        panels: {
            audience_import_workspace: {
                status: 'ready',
            },
        },
        actions: {
            review_campaign: {
                enabled: true,
            },
            generate_pay_codes: {
                enabled: false,
            },
        },
        metadata: {
            token: 'must-not-render',
        },
    },
    mutation: {
        enabled: false,
        status: 'blocked',
        reason: 'campaign-mutations-not-authorized',
    },
    redactions: {
        payloads: 'campaign-cockpit-summary-only',
        routes_registered: false,
        controllers_registered: false,
        mutates_campaigns: false,
        issues_pay_codes: false,
        sends_feedback: false,
        writes_journal: false,
        moves_money: false,
    },
    provider_payload: 'must-not-render',
    raw_payload: 'must-not-render',
    wallet: 'must-not-render',
    mutation_route: '/must-not-render',
};

describe('Cockpit dashboard read model hydration', () => {
    it('hydrates dashboard panels from sanitized dashboard read model props', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
            },
        });

        expect(wrapper.text()).toContain('Pay Codes Visible');
        expect(wrapper.text()).toContain('Sanitized voucher lifecycle list rows');
        expect(wrapper.text()).toContain('Issued');
        expect(wrapper.text()).toContain('Awaiting Approval');
        expect(wrapper.text()).toContain('PC-AWAITING-001');
        expect(wrapper.text()).toContain('Status: awaiting_approval');
        expect(wrapper.text()).toContain('2026-07-03T13:00:00+08:00');
        expect(wrapper.findAll('[data-testid="cockpit-dashboard-metric-card"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-pipeline-stage"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-risk-signal"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-activity-item"]')).toHaveLength(1);
    });

    it('does not render unsafe dashboard payload values', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
            },
        });

        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
    });

    it('keeps dashboard defaults when read model is missing or not authorized', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: {
                    status: 'not_wired',
                    authorized: false,
                    metrics: [],
                    pipeline: [],
                    risk_signals: [],
                    activity: [],
                    redactions: {
                        payloads: 'not-loaded',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Pending wallet read model');
        expect(wrapper.text()).toContain('No provider call in Slice 2');
        expect(wrapper.text()).toContain('Journal facts require authorization and redaction before display.');
    });

    it('forwards route adapter props into the cockpit dashboard page', () => {
        const wrapper = mount(DashboardRouteAdapter, {
            props: {
                dashboard_read_model: dashboardReadModel,
            },
        });

        expect(wrapper.text()).toContain('Pay Codes Visible');
        expect(wrapper.text()).toContain('PC-AWAITING-001');
        expect(wrapper.text()).not.toContain('must-not-render');
    });

    it('hydrates read-only campaign cockpit presentation from campaign read model props', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                campaign_read_model: campaignReadModel,
            },
        });

        expect(wrapper.text()).toContain('Campaign Cockpit Adoption');
        expect(wrapper.text()).toContain('Food Aid July');
        expect(wrapper.text()).toContain('250 recipients');
        expect(wrapper.text()).toContain('campaign-plan-1');
        expect(wrapper.text()).toContain('execution-1');
        expect(wrapper.text()).toContain('campaign_dashboard');
        expect(wrapper.text()).toContain('attachment_operator_workspace');
        expect(wrapper.text()).toContain('audience_import_workspace: ready');
        expect(wrapper.text()).toContain('review_campaign: available');
        expect(wrapper.text()).toContain('generate_pay_codes: blocked');
        expect(wrapper.text()).toContain('campaign-mutations-not-authorized');
        expect(wrapper.find('[data-testid="cockpit-campaign-adoption-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-campaign-surface"]')).toHaveLength(2);
    });

    it('does not render unsafe campaign cockpit payload values or mutation affordances', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                campaign_read_model: campaignReadModel,
            },
        });

        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('/must-not-render');
        expect(wrapper.find('[data-testid="cockpit-campaign-mutation-button"]').exists()).toBe(false);
    });

    it('renders unavailable campaign cockpit presentation as read-only disabled state', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                campaign_read_model: {
                    schema: 'x-change.cockpit.campaign-adoption.v1',
                    status: 'unavailable',
                    authorized: false,
                    source: 'x-campaign',
                    facts: [],
                    mutation: {
                        enabled: false,
                        status: 'blocked',
                        reason: 'campaign-mutations-not-authorized',
                    },
                    redactions: {
                        payloads: 'not-loaded',
                        reason: 'package-not-installed',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Campaign Cockpit Adoption');
        expect(wrapper.text()).toContain('Campaign read model unavailable');
        expect(wrapper.text()).toContain('package-not-installed');
        expect(wrapper.text()).toContain('Read-only boundary');
    });

    it('forwards campaign route adapter props into the cockpit dashboard page', () => {
        const wrapper = mount(DashboardRouteAdapter, {
            props: {
                dashboard_read_model: dashboardReadModel,
                campaign_read_model: campaignReadModel,
            },
        });

        expect(wrapper.text()).toContain('Food Aid July');
        expect(wrapper.text()).toContain('campaign-plan-1');
        expect(wrapper.text()).not.toContain('must-not-render');
    });

    it('renders read-only campaign cockpit navigation links to existing explorer surfaces', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                campaign_read_model: campaignReadModel,
            },
        });

        const explorerLink = wrapper.find('[data-testid="cockpit-campaign-explorer-link"]');

        expect(explorerLink.exists()).toBe(true);
        expect(explorerLink.attributes('href')).toBe('/x/cockpit/pay-codes?campaign_planning_key=campaign-plan-1&campaign_execution_id=execution-1&campaign_source=campaign_cockpit');
        expect(explorerLink.text()).toContain('Open Pay Code Explorer');
        expect(wrapper.text()).toContain('Existing read-only Cockpit route');
        expect(wrapper.find('[data-testid="cockpit-campaign-workspace-link"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-campaign-workspace-link"]').attributes('aria-disabled')).toBe('true');
        expect(wrapper.find('[data-testid="cockpit-campaign-mutation-button"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('/campaigns/');
        expect(wrapper.text()).not.toContain('/must-not-render');
    });

    it('renders journal action and feedback integration summary cards from the read model bundle', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                read_model: {
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [{ id: 'journal-1' }],
                        redactions: {
                            payloads: 'journal-evidence-summary-only',
                            source: 'x-journal',
                        },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [{ key: 'review' }],
                        redactions: {
                            payloads: 'safe-action-host-summary-only',
                            source: 'x-action',
                        },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [{ id: 'delivery-1' }],
                        redactions: {
                            payloads: 'communication-delivery-summary-only',
                            source: 'x-feedback',
                        },
                    },
                    raw_payload: 'must-not-render',
                    provider_payload: 'must-not-render',
                },
            },
        });

        expect(wrapper.text()).toContain('Integration Summary');
        expect(wrapper.text()).toContain('Journal Evidence');
        expect(wrapper.text()).toContain('1 entries');
        expect(wrapper.text()).toContain('Action CTAs');
        expect(wrapper.text()).toContain('1 actions');
        expect(wrapper.text()).toContain('Feedback Deliveries');
        expect(wrapper.text()).toContain('1 deliveries');
        expect(wrapper.text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).toContain('safe-action-host-summary-only');
        expect(wrapper.text()).toContain('communication-delivery-summary-only');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.findAll('[data-testid="cockpit-integration-summary-card"]')).toHaveLength(3);
    });
});
