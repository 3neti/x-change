import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import Funding from '../../../resources/js/cockpit/pages/Funding.vue';

const fundingReadModel = {
    schema: 'x-change.cockpit.funding-read-model.v1',
    status: 'available',
    authorized: true,
    read_only: true,
    summary: {
        awaiting_funds: 1,
        settled_funding: '₱24,950.00',
        open_suspense: 1,
        recovery_outstanding: '₱200.00',
    },
    providers: [
        {
            code: 'netbank',
            label: 'NetBank',
            status: 'available',
            authoritative_verification: true,
            destination_mode: 'shared',
            destination_status: 'platform_managed',
            destination_reference: 'Platform-managed',
        },
        {
            code: 'paynamics_constellation',
            label: 'Paynamics',
            status: 'available',
            authoritative_verification: true,
            destination_mode: 'shared',
            destination_status: 'platform_managed',
            destination_reference: 'Platform-managed',
        },
    ],
    intents: [
        {
            reference: '01J-FUNDING-1',
            provider: 'netbank',
            amount: '₱250.00',
            currency: 'PHP',
            status: 'awaiting_funds',
            created_at: '2026-07-23T08:00:00+08:00',
        },
    ],
    suspense_cases: [
        {
            reference: '01J-SUSPENSE-1',
            provider: 'netbank',
            reason: 'amount_mismatch',
            status: 'open',
            pending_approval: false,
            pending_action: null,
            allowed_actions: ['match_verified_observation'],
        },
    ],
    approval_queue: [
        {
            reference: '01J-APPROVAL-1',
            case_reference: '01J-SUSPENSE-2',
            provider: 'netbank',
            reason: 'verified_posting_interrupted',
            action: 'compensate_verified_posting',
            status: 'pending_approval',
            requested_at: '2026-07-23T08:05:00+08:00',
            requested_by_self: false,
            can_approve: true,
            amount_input_allowed: false,
            evidence_input_allowed: false,
        },
    ],
    recovery_holds: [
        {
            reference: '01J-RECOVERY-1',
            status: 'open',
            hold_status: 'active',
            outstanding: '₱200.00',
            currency: 'PHP',
        },
    ],
    treasury_positions: [
        {
            provider: 'netbank',
            currency: 'PHP',
            status: 'active',
            recognized: '₱24,950.00',
            has_treasury_facts: true,
        },
    ],
    controls: {
        funding_intent_required: true,
        manual_balance_adjustment_enabled: false,
    },
    redactions: {
        payloads: 'funding-operations-summary-only',
    },
};

const fundingSimulation = {
    enabled: true,
    mode: 'rollback-only' as const,
    provider_calls: false as const,
    balance_changes: false as const,
    amount: '₱25.00',
    mobile_ready: true,
    qr_code: 'data:image/png;base64,AA==',
};

const fundingSimulationResult = {
    schema: 'x-change.lifecycle.qrph-funding-simulation.v1',
    scenario: 'qrph_funding_existing_mobile_demo',
    label: 'QR Ph Funding Existing Mobile',
    mode: 'qrph_funding_simulation',
    success: true,
    message: 'Rollback-only QR Ph funding lifecycle completed.',
    rollback_completed: true,
    simulation: {
        rollback_only: true,
        provider_calls: 0,
        simulated_provider_ledger: true,
        signed_webhook: true,
        authoritative_verification: true,
        persisted: false,
    },
    balance: {
        before_minor: 1_000_000,
        after_minor: 1_002_500,
        credited_minor: 2_500,
        after_replay_minor: 1_002_500,
    },
    steps: [
        {
            key: 'verified_mobile_resolved',
            label: 'Verified mobile resolves the intended Account',
            outcome: 'ready',
            facts: [
                {
                    label: 'Settlement authority',
                    value: 'Provider evidence only',
                },
            ],
        },
        {
            key: 'identical_replay_noop',
            label: 'Identical callback replay is a no-op',
            outcome: 'protected',
            facts: [{ label: 'Second credit', value: 'No' }],
        },
    ],
};

describe('Cockpit Funding foundation', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('renders provider-verified funding posture and operational facts', () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                funding_instruction: {
                    reference: '01J-FUNDING-1',
                    provider: 'netbank',
                    amount: '₱250.00',
                    currency: 'PHP',
                    status: 'awaiting_funds',
                    expires_at: '2026-07-23T08:30:00+08:00',
                    funding_address: '915001234567890123456',
                    institution: 'NetBank',
                    account_name: 'X-Change Treasury',
                    delivery: 'manual-bank-or-wallet-transfer',
                    balance_changed: false,
                    sensitive: true,
                },
            },
        });

        expect(
            wrapper.get('[data-testid="cockpit-funding-page"]').text(),
        ).toContain('Account Funding');
        expect(wrapper.text()).toContain(
            'There is no manual “add funds” control',
        );
        expect(wrapper.text()).toContain('Webhook evidence ≠ Account credit');
        expect(wrapper.text()).toContain('NetBank');
        expect(wrapper.text()).toContain('Shared · Platform-managed');
        expect(wrapper.text()).toContain('Paynamics');
        expect(wrapper.text()).toContain('₱24,950.00');
        expect(wrapper.text()).toContain('Amount Mismatch');
        expect(wrapper.text()).toContain('Request exact evidence match');
        expect(wrapper.text()).toContain('Reconciliation approval queue');
        expect(wrapper.text()).toContain('Approve and execute');
        expect(wrapper.text()).toContain(
            'amount and evidence inputs are disabled',
        );
        expect(wrapper.text()).toContain('Treasury Inventory');
        expect(wrapper.text()).toContain('Create Funding Intent');
        expect(wrapper.text()).toContain('Transfer exactly ₱250.00');
        expect(wrapper.text()).toContain('915001234567890123456');
        expect(wrapper.text()).toContain(
            'The Account changes only after independent provider verification',
        );
        expect(wrapper.text()).not.toContain('provider transaction');
        expect(wrapper.findAll('table tbody tr')).toHaveLength(1);
    });

    it('renders safe empty states when no funding records exist', () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: {
                    ...fundingReadModel,
                    summary: {
                        awaiting_funds: 0,
                        settled_funding: '₱0.00',
                        open_suspense: 0,
                        recovery_outstanding: '₱0.00',
                    },
                    intents: [],
                    suspense_cases: [],
                    approval_queue: [],
                    recovery_holds: [],
                    treasury_positions: [],
                },
            },
        });

        expect(wrapper.text()).toContain('No Funding Intents yet');
        expect(wrapper.text()).toContain('No open funding exceptions.');
        expect(wrapper.text()).toContain(
            'No reconciliation requests are awaiting approval.',
        );
        expect(wrapper.text()).toContain('No active funding recovery holds.');
        expect(wrapper.text()).toContain(
            'No Treasury Inventory has been recognized',
        );
        expect(wrapper.text()).toContain(
            'Funding instructions will appear here once',
        );
    });

    it('rejects a malformed amount before submitting the intent', async () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
            },
        });

        await wrapper
            .get('[data-testid="cockpit-funding-amount"]')
            .setValue('25.999');
        await wrapper
            .get('[data-testid="cockpit-funding-submit"]')
            .trigger('click');
        await nextTick();

        expect(wrapper.text()).toContain('no more than two decimal places');
    });

    it('runs and steps through the rollback-only QR Ph funding simulation', async () => {
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => fundingSimulationResult,
        });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                funding_simulation: fundingSimulation,
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-qrph-funding-simulation"]')
                .text(),
        ).toContain('No monetary value');
        expect(wrapper.text()).toContain('Simulate a ₱25.00 QR Ph');

        await wrapper
            .get('[data-testid="run-qrph-funding-simulation"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(fetch).toHaveBeenCalledOnce();
        expect(
            wrapper
                .get('[data-testid="qrph-funding-simulation-stepper"]')
                .text(),
        ).toContain('Verified mobile resolves the intended Account');
        expect(wrapper.text()).toContain(
            'Rollback confirmed · one simulated credit',
        );
    });
});
