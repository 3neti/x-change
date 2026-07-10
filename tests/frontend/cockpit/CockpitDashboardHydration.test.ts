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

const operatorIssuanceActivityReadModel = {
    schema: 'x-change.cockpit.operator-issuance-activity.v1',
    status: 'available',
    authorized: true,
    source: 'x-change.cockpit.operator-issuance-activity.presenter',
    items: [
        {
            id: 'activity-1',
            code: 'PC-1234',
            amount: '100.00',
            currency: 'PHP',
            status: 'issued',
            issued_at: '2026-07-10T09:00:00+08:00',
            route: 'cockpit.quick-generate',
            provider_payload: 'must-not-render',
        },
    ],
    presentations: [
        {
            schema: 'x-change.cockpit.operator-issuance-activity-presentation.v1',
            id: 'activity-1',
            code: 'PC-1234',
            title: 'Pay Code PC-1234 issued',
            subtitle: 'PHP 100.00 issued through Quick Generate',
            status: 'issued',
            detail_href: '/x/cockpit/pay-codes/PC-1234',
            correlation_id: 'corr-1',
            handoffs: {
                journal: 'recorded',
                action: 'not_wired',
                feedback: 'not_wired',
            },
            safety: {
                presentation_only: true,
                writes_journal: false,
                executes_actions: false,
                sends_feedback: false,
                moves_money: false,
                owns_lifecycle_truth: false,
            },
            metadata: {
                journal_handoff: {
                    status: 'recorded',
                    journal_entry_id: 'journal-entry-1',
                    writes_journal: true,
                    source: 'test-journal-handoff',
                    reason: 'Journal handoff was recorded.',
                    diagnostic: {
                        classification: 'recorded',
                        tone: 'success',
                        label: 'Journal recorded',
                        description: 'The durable activity was handed to the journal and a journal entry identifier is available for read-only inspection.',
                        operator_action: 'none',
                        read_only: true,
                        retry_enabled: false,
                        mutation_enabled: false,
                        raw_payloads_exposed: false,
                    },
                    metadata: {
                        reference_number: 'XJ-1',
                        event_type: 'cockpit.operator_issuance_activity.recorded',
                        provider_payload: 'must-not-render',
                        token: 'must-not-render',
                    },
                },
                provider_payload: 'must-not-render',
                raw_payload: 'must-not-render',
                wallet: 'must-not-render',
                recipient_secret: 'must-not-render',
            },
        },
    ],
    redactions: {
        payloads: 'activity-summary-only',
        lifecycle_truth: false,
        writes_journal: false,
        executes_actions: false,
        sends_feedback: false,
        moves_money: false,
    },
    provider_payload: 'must-not-render',
    raw_payload: 'must-not-render',
    wallet: 'must-not-render',
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

    it('renders integration unavailable reasons without exception messages', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                read_model: {
                    journal: {
                        status: 'unavailable',
                        authorized: false,
                        entries: [],
                        redactions: {
                            payloads: 'not-loaded',
                            reason: 'read-model-unavailable',
                            exception: 'RuntimeException',
                            exception_message: 'must-not-render',
                            exception_message_exposed: false,
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Journal Evidence');
        expect(wrapper.text()).toContain('unavailable');
        expect(wrapper.text()).toContain('read-model-unavailable');
        expect(wrapper.text()).not.toContain('RuntimeException');
        expect(wrapper.text()).not.toContain('must-not-render');
    });

    it('keeps operator integration summaries authorization and redaction safe', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                read_model: {
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [
                            {
                                id: 'journal-1',
                                event_type: 'voucher.redeemed',
                                raw_payload: 'SECRET-DO-NOT-RENDER',
                                exception_message: 'Stack trace must stay hidden',
                            },
                        ],
                        redactions: {
                            payloads: 'journal-evidence-summary-only',
                            reason: 'read-model-ready',
                            exception: 'RuntimeException',
                            exception_message: 'Stack trace must stay hidden',
                            internal_route: '/unsafe-journal-route',
                        },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [
                            {
                                key: 'approve',
                                target_url: '/unsafe-action-route',
                                run_id: 'non-durable-run-id',
                                raw_diagnostics: 'SECRET-DO-NOT-RENDER',
                            },
                        ],
                        redactions: {
                            payloads: 'safe-action-host-summary-only',
                            reason: 'presentation-only',
                            executes_action: false,
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
                            credential: 'SECRET-DO-NOT-RENDER',
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Integration Summary');
        expect(wrapper.text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).toContain('safe-action-host-summary-only');
        expect(wrapper.text()).toContain('communication-delivery-summary-only');
        expect(wrapper.text()).not.toContain('SECRET-DO-NOT-RENDER');
        expect(wrapper.text()).not.toContain('RuntimeException');
        expect(wrapper.text()).not.toContain('Stack trace must stay hidden');
        expect(wrapper.text()).not.toContain('/unsafe-journal-route');
        expect(wrapper.text()).not.toContain('/unsafe-action-route');
        expect(wrapper.text()).not.toContain('non-durable-run-id');
        expect(wrapper.text()).not.toContain('+639170000000');
    });

    it('renders operator issuance activity presentations as read-only dashboard evidence', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                operator_issuance_activity_read_model: operatorIssuanceActivityReadModel,
            },
        });

        expect(wrapper.text()).toContain('Operator Issuance Activity');
        expect(wrapper.text()).toContain('Pay Code PC-1234 issued');
        expect(wrapper.text()).toContain('PHP 100.00 issued through Quick Generate');
        expect(wrapper.text()).toContain('corr-1');
        expect(wrapper.text()).toContain('journal: recorded');
        expect(wrapper.text()).toContain('action: not_wired');
        expect(wrapper.text()).toContain('feedback: not_wired');
        expect(wrapper.text()).toContain('Journal entry: journal-entry-1');
        expect(wrapper.text()).toContain('Writes journal: yes');
        expect(wrapper.text()).toContain('Source: test-journal-handoff');
        expect(wrapper.text()).toContain('Reason: Journal handoff was recorded.');
        expect(wrapper.text()).toContain('Reference: XJ-1');
        expect(wrapper.text()).toContain('Event: cockpit.operator_issuance_activity.recorded');
        expect(wrapper.text()).toContain('Diagnostic: Journal recorded');
        expect(wrapper.text()).toContain('Action: none');
        expect(wrapper.text()).toContain('Read-only: yes');
        expect(wrapper.text()).toContain('presentation-only');
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-card"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-journal-summary"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-journal-diagnostic"]')).toHaveLength(1);
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-link"]').attributes('href')).toBe('/x/cockpit/pay-codes/PC-1234');
    });

    it('does not render unsafe operator issuance activity payloads or side-effect affordances', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                operator_issuance_activity_read_model: operatorIssuanceActivityReadModel,
            },
        });

        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('recipient_secret');
        expect(wrapper.text()).not.toContain('token');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-mutation"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-journal-retry"]').exists()).toBe(false);
    });

    it('renders unavailable operator issuance activity as a read-only empty state', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                operator_issuance_activity_read_model: {
                    schema: 'x-change.cockpit.operator-issuance-activity.v1',
                    status: 'not_wired',
                    authorized: false,
                    source: 'null-operator-issuance-activity-read-model',
                    items: [],
                    presentations: [],
                    empty_state: {
                        title: 'No operator issuance activity available',
                        description: 'Activity recording is not wired yet.',
                    },
                    redactions: {
                        payloads: 'activity-summary-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Operator Issuance Activity');
        expect(wrapper.text()).toContain('No operator issuance activity available');
        expect(wrapper.text()).toContain('Activity recording is not wired yet.');
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-card"]')).toHaveLength(0);
    });

    it('forwards operator issuance activity props through the dashboard route adapter', () => {
        const wrapper = mount(DashboardRouteAdapter, {
            props: {
                dashboard_read_model: dashboardReadModel,
                operator_issuance_activity_read_model: operatorIssuanceActivityReadModel,
            },
        });

        expect(wrapper.text()).toContain('Pay Code PC-1234 issued');
        expect(wrapper.text()).toContain('PHP 100.00 issued through Quick Generate');
        expect(wrapper.text()).not.toContain('must-not-render');
    });
});
