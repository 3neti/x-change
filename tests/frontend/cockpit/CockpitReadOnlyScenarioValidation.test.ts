import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitDashboard from '../../../resources/js/cockpit/pages/Dashboard.vue';
import PayCodeExplorer from '../../../resources/js/cockpit/pages/PayCodeExplorer.vue';
import VoucherDetail from '../../../resources/js/cockpit/pages/VoucherDetail.vue';

const unsafeValues = [
    'SECRET-SCENARIO-PAYLOAD',
    'provider_payload',
    'raw_payload',
    'wallet_private_key',
    'approval_otp',
    '+639170000000',
    '/unsafe-action-route',
    'RuntimeException',
    'Stack trace must stay hidden',
];

const integrationBundle = {
    journal: {
        status: 'available',
        authorized: true,
        entries: [
            {
                id: 'journal-basic-cash',
                event_type: 'voucher.generated',
                summary:
                    'basic_cash generated a Pay Code for local validation.',
                occurred_at: '2026-07-09T10:00:00+08:00',
                raw_payload: 'SECRET-SCENARIO-PAYLOAD',
            },
            {
                id: 'journal-open-slices',
                event_type: 'voucher.slice.redeemed',
                summary:
                    'divisible_open_three_slices_enforced_interval recorded a slice checkpoint.',
                occurred_at: '2026-07-09T10:05:00+08:00',
                provider_payload: 'SECRET-SCENARIO-PAYLOAD',
            },
        ],
        redactions: {
            payloads: 'journal-evidence-summary-only',
            reason: 'scenario-validation-ready',
            exception: 'RuntimeException',
            exception_message: 'Stack trace must stay hidden',
        },
    },
    actions: {
        status: 'available',
        authorized: true,
        actions: [
            {
                key: 'review-basic-cash',
                label: 'Review basic_cash',
                status: 'available',
                target_url: '/unsafe-action-route',
                raw_diagnostics: 'SECRET-SCENARIO-PAYLOAD',
            },
            {
                key: 'review-open-slices',
                label: 'Review open slice interval',
                status: 'available',
                target_url: '/unsafe-action-route',
                raw_diagnostics: 'SECRET-SCENARIO-PAYLOAD',
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
                id: 'feedback-basic-cash',
                channel: 'sms',
                status: 'planned',
                recipient: '+639170000000',
                provider_payload: 'SECRET-SCENARIO-PAYLOAD',
            },
        ],
        redactions: {
            payloads: 'communication-delivery-summary-only',
            reason: 'read-only-delivery-summary',
            credential: 'SECRET-SCENARIO-PAYLOAD',
        },
    },
};

const dashboardReadModel = {
    status: 'available',
    authorized: true,
    metrics: [
        {
            key: 'scenario-count',
            label: 'Local Scenarios',
            value: '2',
            helper: 'basic_cash and divisible_open_three_slices_enforced_interval',
            tone: 'healthy',
        },
    ],
    pipeline: [
        {
            key: 'basic-cash',
            label: 'basic_cash',
            value: 'generated',
            tone: 'healthy',
        },
        {
            key: 'open-slices',
            label: 'divisible_open_three_slices_enforced_interval',
            value: 'slice checkpoints',
            tone: 'neutral',
        },
    ],
    risk_signals: [
        {
            key: 'read-only-validation',
            label: 'Read-only validation',
            value: 'No provider calls or money movement',
            severity: 'watch',
        },
    ],
    activity: [
        {
            id: 'scenario-basic-cash',
            label: 'basic_cash',
            description:
                'Scenario output available as sanitized Cockpit summary.',
            timestamp: '2026-07-09T10:00:00+08:00',
            source: 'system',
            raw_payload: 'SECRET-SCENARIO-PAYLOAD',
        },
        {
            id: 'scenario-open-slices',
            label: 'divisible_open_three_slices_enforced_interval',
            description:
                'Open slice interval checkpoints available as sanitized Cockpit summary.',
            timestamp: '2026-07-09T10:05:00+08:00',
            source: 'journal',
            provider_payload: 'SECRET-SCENARIO-PAYLOAD',
        },
    ],
    redactions: {
        payloads: 'sanitized-dashboard-summary-only',
    },
};

const payCodesReadModel = {
    status: 'ready',
    authorized: true,
    query: 'scenario-validation',
    redactions: {
        payloads: 'sanitized-list-only',
    },
    records: [
        {
            code: 'PC-BASIC-CASH-001',
            template: 'basic_cash',
            amount: 25,
            currency: 'PHP',
            status: 'issued',
            display_status: 'ready',
            owner: 'Scenario Runner',
            last_activity: '2026-07-09T10:00:00+08:00',
            raw_payload: 'SECRET-SCENARIO-PAYLOAD',
        },
        {
            code: 'PC-OPEN-SLICES-001',
            template: 'divisible_open_three_slices_enforced_interval',
            amount: 75,
            currency: 'PHP',
            status: 'partially_redeemed',
            display_status: 'slice checkpoint',
            owner: 'Scenario Runner',
            last_activity: '2026-07-09T10:05:00+08:00',
            provider_payload: 'SECRET-SCENARIO-PAYLOAD',
        },
    ],
};

const voucherReadModel = {
    code: 'PC-BASIC-CASH-001',
    voucher: {
        code: 'PC-BASIC-CASH-001',
        status: 'issued',
        summary: {
            code: 'PC-BASIC-CASH-001',
            status: 'issued',
            display_status: 'ready',
            amount: 25,
            currency: 'PHP',
            claimed: false,
            fully_claimed: false,
            created_at: '2026-07-09T10:00:00+08:00',
            starts_at: '2026-07-09T10:00:00+08:00',
            expires_at: '2026-07-16T10:00:00+08:00',
            redeemed_at: null,
            wallet_private_key: 'SECRET-SCENARIO-PAYLOAD',
            approval_otp: 'SECRET-SCENARIO-PAYLOAD',
            raw_payload: 'SECRET-SCENARIO-PAYLOAD',
        },
        redactions: {
            payloads: 'sanitized-summary-only',
        },
        authorized: true,
    },
    execution: {
        status: 'available',
        authorized: true,
        redactions: { payloads: 'execution-summary-only' },
    },
    ...integrationBundle,
};

function expectNoUnsafeText(text: string): void {
    for (const value of unsafeValues) {
        expect(text).not.toContain(value);
    }
}

describe('Cockpit read-only UI/UX scenario validation checkpoint', () => {
    it('renders local scenario summaries on the dashboard without unsafe payloads', async () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
                read_model: integrationBundle,
            },
        });

        expect(wrapper.text()).toContain('Local Scenarios');
        expect(wrapper.text()).toContain('basic_cash');
        expect(wrapper.text()).toContain(
            'divisible_open_three_slices_enforced_interval',
        );
        expect(wrapper.text()).toContain('Connected Services');
        expect(wrapper.text()).not.toContain('Journal Evidence Summary Only');
        expect(wrapper.text()).not.toContain('Safe Action Host Summary Only');
        expect(wrapper.text()).not.toContain('Communication Delivery Summary Only');
        for (const toggle of wrapper.findAll('[data-testid="cockpit-integration-summary-details-toggle"]')) {
            await toggle.trigger('click');
        }
        expect(wrapper.text()).toContain('Journal Evidence Summary Only');
        expect(wrapper.text()).toContain('Safe Action Host Summary Only');
        expect(wrapper.text()).toContain('Communication Delivery Summary Only');
        expect(
            wrapper.findAll('[data-testid="cockpit-integration-summary-card"]'),
        ).toHaveLength(3);
        expectNoUnsafeText(wrapper.text());
    });

    it('renders scenario Pay Codes in the explorer as a read-only local validation list', () => {
        const wrapper = mount(PayCodeExplorer, {
            props: {
                pay_codes_read_model: payCodesReadModel,
                read_model: integrationBundle,
            },
        });

        const search = wrapper.find(
            '[data-testid="cockpit-pay-code-search-input"]',
        );

        expect(wrapper.text()).toContain('PC-BASIC-CASH-001');
        expect(wrapper.text()).toContain('PC-OPEN-SLICES-001');
        expect(wrapper.text()).toContain('basic_cash');
        expect(wrapper.text()).toContain(
            'divisible_open_three_slices_enforced_interval',
        );
        expect(search.element).toHaveProperty('readOnly', false);
        expect(
            wrapper.findAll('[data-testid="cockpit-pay-code-row"]'),
        ).toHaveLength(2);
        expect(wrapper.find('form').exists()).toBe(true);
        expectNoUnsafeText(wrapper.text());
    });

    it('renders a scenario voucher detail as read-only evidence and communication summary', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-BASIC-CASH-001' },
                read_model: voucherReadModel,
            },
        });

        expect(wrapper.text()).toContain('PC-BASIC-CASH-001');
        expect(wrapper.text()).toContain('₱25.00');
        expect(wrapper.text()).toContain('Journal: voucher.generated');
        expect(wrapper.text()).toContain('Review basic_cash');
        expect(wrapper.text()).toContain('SMS');
        expect(wrapper.text()).toContain(
            'Feedback delivery remains read-only from Cockpit.',
        );
        expect(wrapper.text()).toContain(
            'Action execution remains disabled from Cockpit.',
        );
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-voucher-integration-summary-card"]',
            ),
        ).toHaveLength(3);
        expectNoUnsafeText(wrapper.text());
    });
});
