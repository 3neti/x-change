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
    quick_generate_link: {
        schema: 'x-change.cockpit.campaign-quick-generate-link.v1',
        status: 'available',
        enabled: true,
        label: 'Open Quick Generate',
        href: 'http://localhost/x/cockpit/quick-generate?campaign_planning_key=campaign-plan-1&campaign_execution_id=execution-1&campaign_source=campaign_cockpit&campaign_template_key=ofw-remittance&campaign_amount=500.00&campaign_currency=PHP&campaign_recipient_reference=09173011987&campaign_purpose=Campaign%20payout',
        route: 'x-change.cockpit.quick-generate',
        read_only: true,
        mutates_campaign: false,
        planning_key: 'campaign-plan-1',
        execution_id: 'execution-1',
        source: 'campaign_cockpit',
        draft: {
            template_key: 'ofw-remittance',
            amount: '500.00',
            currency: 'PHP',
            recipient_reference: '09173011987',
            purpose: 'Campaign payout',
            raw_payload: 'must-not-render',
        },
        redactions: {
            payloads: 'campaign-source-link-query-only',
        },
    },
    recipient_quick_generate_links: [
        {
            schema: 'x-change.cockpit.campaign-quick-generate-link.v1',
            status: 'available',
            enabled: true,
            label: 'Generate for Ana',
            href: 'http://localhost/x/cockpit/quick-generate?campaign_planning_key=campaign-plan-1&campaign_execution_id=execution-1&campaign_recipient_id=recipient-a&campaign_source=x_campaign_adapter&campaign_template_key=ofw-remittance&campaign_amount=500.00&campaign_currency=PHP&campaign_recipient_reference=BEN-A&campaign_purpose=Ana%20payout',
            route: 'x-change.cockpit.quick-generate',
            read_only: true,
            mutates_campaign: false,
            planning_key: 'campaign-plan-1',
            execution_id: 'execution-1',
            recipient_id: 'recipient-a',
            source: 'x_campaign_adapter',
            draft: {
                template_key: 'ofw-remittance',
                amount: '500.00',
                currency: 'PHP',
                recipient_reference: 'BEN-A',
                purpose: 'Ana payout',
                mobile: 'must-not-render',
            },
            redactions: {
                payloads: 'campaign-recipient-source-link-query-only',
            },
        },
        {
            schema: 'x-change.cockpit.campaign-quick-generate-link.v1',
            status: 'available',
            enabled: true,
            label: 'Generate for Ben',
            href: 'http://localhost/x/cockpit/quick-generate?campaign_planning_key=campaign-plan-1&campaign_execution_id=execution-1&campaign_recipient_id=recipient-b&campaign_source=x_campaign_adapter&campaign_template_key=money-changer&campaign_amount=625.25&campaign_currency=PHP&campaign_recipient_reference=BEN-B&campaign_purpose=Ben%20payout',
            route: 'x-change.cockpit.quick-generate',
            read_only: true,
            mutates_campaign: false,
            planning_key: 'campaign-plan-1',
            execution_id: 'execution-1',
            recipient_id: 'recipient-b',
            source: 'x_campaign_adapter',
            draft: {
                template_key: 'money-changer',
                amount: '625.25',
                currency: 'PHP',
                recipient_reference: 'BEN-B',
                purpose: 'Ben payout',
                email: 'must-not-render',
            },
            redactions: {
                payloads: 'campaign-recipient-source-link-query-only',
            },
        },
    ],
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
                action: 'composed',
                feedback: 'planned',
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
                action_handoff: {
                    status: 'composed',
                    action_hint_id: 'cockpit.pay-code.open',
                    action_run_id: 'action-run-1',
                    action_required: false,
                    executes_action: false,
                    source: 'test-action-handoff',
                    reason: 'x-action composed presentation-only operator action hints for this Cockpit activity.',
                    metadata: {
                        event_or_state: 'cockpit.operator_issuance_activity.recorded',
                        actions: [
                            {
                                key: 'cockpit.pay-code.open',
                                label: 'Open Pay Code',
                                run_id: 'action-run-1',
                                target: {
                                    url: '/x/cockpit/pay-codes/PC-1234',
                                    redirectable: true,
                                },
                                provider_payload: 'must-not-render',
                            },
                        ],
                        composition: {
                            presentation_only: true,
                            executes_action: false,
                        },
                        provider_payload: 'must-not-render',
                    },
                },
                feedback_handoff: {
                    status: 'planned',
                    feedback_intent_id: 'cockpit.operator_issuance_activity.recorded',
                    delivery_plan_id: 'plan-feedback-1',
                    delivery_receipt_id: null,
                    feedback_required: false,
                    sends_feedback: false,
                    source: 'test-feedback-handoff',
                    reason: 'x-feedback prepared an operator activity delivery plan without dispatching provider delivery.',
                    metadata: {
                        intent_key: 'cockpit.operator_issuance_activity.recorded',
                        event_type: 'cockpit.operator_issuance_activity.recorded',
                        delivery_boundary: 'prepare_only',
                        planned_deliveries: 1,
                        channels: ['in_app'],
                        plan_items: [
                            {
                                intent_key: 'cockpit.operator_issuance_activity.recorded',
                                recipient_type: 'operator',
                                recipient_id: 'operator-1',
                                channel: 'in_app',
                                status: 'planned',
                                priority: 100,
                                provider_payload: 'must-not-render',
                            },
                        ],
                        composition: {
                            presentation_only: true,
                            sends_feedback: false,
                            owns_lifecycle_truth: false,
                        },
                        provider_payload: 'must-not-render',
                    },
                },
                campaign_attribution: {
                    schema: 'x-change.cockpit.quick-generate-campaign-attribution.v1',
                    status: 'available',
                    read_only: true,
                    mutates_campaign: false,
                    planning_key: 'plan-wave-43c',
                    execution_id: 'exec-wave-43c',
                    campaign_id: 'campaign-wave-43c',
                    audience_id: 'audience-wave-43c',
                    recipient_id: 'recipient-wave-43c',
                    source: 'x_campaign_adapter',
                    generated_code: 'PC-1234',
                    template_key: 'ofw-remittance',
                    amount: '500.00',
                    currency: 'PHP',
                    recipient_reference: '09173011987',
                    purpose: 'Campaign payout',
                    provider_payload: 'must-not-render',
                    raw_payload: 'must-not-render',
                    wallet: 'must-not-render',
                    recipient_secret: 'must-not-render',
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
    search_filters: {
        schema: 'x-change.cockpit.operator-issuance-activity-search-filter.v1',
        status: 'available',
        read_only: true,
        search: 'money changer',
        statuses: ['issued'],
        handoff_statuses: ['recorded'],
        available_statuses: ['issued', 'failed'],
        available_handoff_statuses: ['recorded', 'not_wired', 'planned'],
        safety: {
            read_only: true,
            writes_journal: false,
            executes_actions: false,
            sends_feedback: false,
            moves_money: false,
            owns_lifecycle_truth: false,
        },
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

    it('renders a read-only campaign quick generate source link from the campaign read model', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                campaign_read_model: campaignReadModel,
            },
        });

        const quickGenerateLink = wrapper.find('[data-testid="cockpit-campaign-quick-generate-link"]');

        expect(quickGenerateLink.exists()).toBe(true);
        expect(quickGenerateLink.attributes('href')).toContain('/x/cockpit/quick-generate');
        expect(quickGenerateLink.attributes('href')).toContain('campaign_planning_key=campaign-plan-1');
        expect(quickGenerateLink.attributes('href')).toContain('campaign_template_key=ofw-remittance');
        expect(quickGenerateLink.attributes('href')).toContain('campaign_recipient_reference=09173011987');
        expect(quickGenerateLink.text()).toContain('Open Quick Generate');
        expect(quickGenerateLink.text()).toContain('Prefills the existing Quick Generate handoff');
        expect(quickGenerateLink.text()).toContain('read-only campaign context');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.find('[data-testid="cockpit-campaign-mutation-button"]').exists()).toBe(false);
    });

    it('renders read-only campaign recipient quick generate source links from the campaign read model', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                campaign_read_model: campaignReadModel,
            },
        });

        const recipientLinks = wrapper.find('[data-testid="cockpit-campaign-recipient-source-links"]');
        const firstRecipientLink = wrapper.find('[data-testid="cockpit-campaign-recipient-source-link-0"]');
        const secondRecipientLink = wrapper.find('[data-testid="cockpit-campaign-recipient-source-link-1"]');

        expect(recipientLinks.exists()).toBe(true);
        expect(recipientLinks.text()).toContain('Recipient Quick Generate entry points');
        expect(recipientLinks.text()).toContain('Campaign state is not mutated');
        expect(firstRecipientLink.exists()).toBe(true);
        expect(firstRecipientLink.attributes('href')).toContain('/x/cockpit/quick-generate');
        expect(firstRecipientLink.attributes('href')).toContain('campaign_recipient_id=recipient-a');
        expect(firstRecipientLink.text()).toContain('Generate for Ana');
        expect(firstRecipientLink.text()).toContain('BEN-A');
        expect(secondRecipientLink.exists()).toBe(true);
        expect(secondRecipientLink.attributes('href')).toContain('campaign_template_key=money-changer');
        expect(secondRecipientLink.attributes('href')).toContain('campaign_recipient_reference=BEN-B');
        expect(secondRecipientLink.text()).toContain('Generate for Ben');
        expect(secondRecipientLink.text()).toContain('BEN-B');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.find('[data-testid="cockpit-campaign-mutation-button"]').exists()).toBe(false);
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
        expect(wrapper.text()).toContain('action: composed');
        expect(wrapper.text()).toContain('feedback: planned');
        expect(wrapper.text()).toContain('Journal entry: journal-entry-1');
        expect(wrapper.text()).toContain('Writes journal: yes');
        expect(wrapper.text()).toContain('Source: test-journal-handoff');
        expect(wrapper.text()).toContain('Reason: Journal handoff was recorded.');
        expect(wrapper.text()).toContain('Reference: XJ-1');
        expect(wrapper.text()).toContain('Event: cockpit.operator_issuance_activity.recorded');
        expect(wrapper.text()).toContain('Diagnostic: Journal recorded');
        expect(wrapper.text()).toContain('Action: none');
        expect(wrapper.text()).toContain('Read-only: yes');
        expect(wrapper.text()).toContain('Action hint: cockpit.pay-code.open');
        expect(wrapper.text()).toContain('Action run: action-run-1');
        expect(wrapper.text()).toContain('Executes action: no');
        expect(wrapper.text()).toContain('Suggested action: Open Pay Code');
        expect(wrapper.text()).toContain('Feedback intent: cockpit.operator_issuance_activity.recorded');
        expect(wrapper.text()).toContain('Delivery plan: plan-feedback-1');
        expect(wrapper.text()).toContain('Sends feedback: no');
        expect(wrapper.text()).toContain('Channel: in_app');
        expect(wrapper.text()).toContain('Planned deliveries: 1');
        expect(wrapper.text()).toContain('Source: test-feedback-handoff');
        expect(wrapper.text()).toContain('Campaign attribution: available');
        expect(wrapper.text()).toContain('Campaign: campaign-wave-43c');
        expect(wrapper.text()).toContain('Audience: audience-wave-43c');
        expect(wrapper.text()).toContain('Recipient: recipient-wave-43c');
        expect(wrapper.text()).toContain('Recipient reference: 09173011987');
        expect(wrapper.text()).toContain('Template: ofw-remittance');
        expect(wrapper.text()).toContain('Amount: PHP 500.00');
        expect(wrapper.text()).toContain('Generated Pay Code: PC-1234');
        expect(wrapper.text()).toContain('Planning key: plan-wave-43c');
        expect(wrapper.text()).toContain('Execution: exec-wave-43c');
        expect(wrapper.text()).toContain('Source: x_campaign_adapter');
        expect(wrapper.text()).toContain('Purpose: Campaign payout');
        expect(wrapper.text()).toContain('Campaign mutation: no');
        expect(wrapper.text()).toContain('Read-only: yes');
        expect(wrapper.text()).toContain('presentation-only');
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-card"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-campaign-attribution"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-journal-summary"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-journal-diagnostic"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-action-summary"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-operator-issuance-activity-feedback-summary"]')).toHaveLength(1);
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-link"]').attributes('href')).toBe('/x/cockpit/pay-codes/PC-1234');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-explorer-link"]').attributes('href')).toBe('/x/cockpit/pay-codes?activity_code=PC-1234&activity_source=operator_issuance_activity&campaign_planning_key=plan-wave-43c&campaign_execution_id=exec-wave-43c&campaign_id=campaign-wave-43c&campaign_audience_id=audience-wave-43c&campaign_recipient_id=recipient-wave-43c&campaign_source=x_campaign_adapter');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-explorer-link"]').text()).toContain('Open in Explorer');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-explorer-link"]').text()).toContain('campaign context');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-campaign-dashboard-link"]').attributes('href')).toBe('/x/cockpit?campaign_planning_key=plan-wave-43c&campaign_execution_id=exec-wave-43c&campaign_id=campaign-wave-43c&campaign_audience_id=audience-wave-43c&campaign_recipient_id=recipient-wave-43c&campaign_source=x_campaign_adapter');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-campaign-dashboard-link"]').text()).toContain('Return to Campaign Dashboard');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-campaign-dashboard-link"]').text()).toContain('read-only');
    });

    it('does not propagate mutating campaign activity attribution into campaign navigation links', () => {
        const unsafeActivityReadModel = structuredClone(operatorIssuanceActivityReadModel);
        unsafeActivityReadModel.presentations[0].metadata.campaign_attribution.mutates_campaign = true;
        unsafeActivityReadModel.presentations[0].metadata.campaign_attribution.read_only = false;

        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                operator_issuance_activity_read_model: unsafeActivityReadModel,
            },
        });

        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-explorer-link"]').attributes('href')).toBe('/x/cockpit/pay-codes?activity_code=PC-1234&activity_source=operator_issuance_activity');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-explorer-link"]').text()).not.toContain('campaign context');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-campaign-dashboard-link"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Campaign mutation: yes');
        expect(wrapper.text()).toContain('Read-only: no');
        expect(wrapper.text()).not.toContain('must-not-render');
    });

    it('renders read-only operator issuance activity search and filter controls', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                operator_issuance_activity_read_model: operatorIssuanceActivityReadModel,
            },
        });

        const form = wrapper.find('[data-testid="cockpit-operator-issuance-activity-filter-form"]');
        const search = wrapper.find('[data-testid="cockpit-operator-issuance-activity-search-input"]');
        const status = wrapper.find('[data-testid="cockpit-operator-issuance-activity-status-filter"]');
        const handoff = wrapper.find('[data-testid="cockpit-operator-issuance-activity-handoff-filter"]');

        expect(form.attributes('method')).toBe('get');
        expect(form.attributes('action')).toBe('/x/cockpit');
        expect(search.attributes('name')).toBe('activity_search');
        expect((search.element as HTMLInputElement).value).toBe('money changer');
        expect(search.attributes('disabled')).toBeUndefined();
        expect(status.attributes('name')).toBe('activity_status');
        expect((status.element as HTMLSelectElement).value).toBe('issued');
        expect(status.text()).toContain('failed');
        expect(handoff.attributes('name')).toBe('activity_handoff_status');
        expect((handoff.element as HTMLSelectElement).value).toBe('recorded');
        expect(handoff.text()).toContain('planned');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-active-filters"]').text()).toContain('3 active filters');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-active-filters"]').text()).toContain('Read-only filter query; no activity mutation is executed.');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-result-summary"]').text()).toContain('Showing 1 matching activity for the current read-only filters.');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-filter-summary"]').text()).toContain('Filters: search “money changer” · status issued · handoff recorded');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-filter-clear"]').attributes('href')).toBe('/x/cockpit');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-clear-search"]').attributes('href')).toBe('/x/cockpit?activity_status=issued&activity_handoff_status=recorded');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-clear-status"]').attributes('href')).toBe('/x/cockpit?activity_search=money+changer&activity_handoff_status=recorded');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-clear-handoff"]').attributes('href')).toBe('/x/cockpit?activity_search=money+changer&activity_status=issued');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-mutation"]').exists()).toBe(false);
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
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-search-input"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-filter-submit"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-result-summary"]').text()).toContain('Activity filters become available when durable activity storage is wired.');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-filter-summary"]').text()).toContain('Filter summary unavailable until durable activity storage is wired.');
    });

    it('renders a filtered no-match empty state without implying missing runtime wiring', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                operator_issuance_activity_read_model: {
                    ...operatorIssuanceActivityReadModel,
                    items: [],
                    presentations: [],
                    search_filters: {
                        ...operatorIssuanceActivityReadModel.search_filters,
                        search: 'missing pay code',
                        statuses: ['issued'],
                        handoff_statuses: ['recorded'],
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('No activity matches current filters');
        expect(wrapper.text()).toContain('Clear filters or adjust the search/status criteria to inspect durable operator issuance activity.');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-result-summary"]').text()).toContain('Showing 0 matching activities for the current read-only filters.');
        expect(wrapper.text()).not.toContain('Activity recording is not wired yet.');
        expect(wrapper.find('[data-testid="cockpit-operator-issuance-activity-mutation"]').exists()).toBe(false);
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
