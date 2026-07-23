import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import Funding from '../../../resources/js/cockpit/pages/Funding.vue';

const { usePollMock } = vi.hoisted(() => ({
    usePollMock: vi.fn(() => ({
        start: vi.fn(),
        stop: vi.fn(),
    })),
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();

    return {
        ...actual,
        usePoll: usePollMock,
    };
});

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
            can_check_provider: true,
            can_reopen_instructions: true,
            verification_status: 'awaiting_funds',
            last_checked_at: null,
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

const reusableFundingAvailability = {
    enabled: true,
    available: true,
    status: 'available',
    provider: 'netbank' as const,
    temporary: true as const,
    provider_calls: true as const,
    funding_intent_created: false as const,
    automatic_credit_enabled: false as const,
};

describe('Cockpit Funding foundation', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        usePollMock.mockClear();
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
                    qr_code: 'data:image/png;base64,AA==',
                    qr_mode: 'dynamic',
                    transaction_type: 'p2m',
                    embedded_amount: true,
                    provider_generated: true,
                    balance_changed: false,
                    sensitive: true,
                },
                reusable_funding_address: reusableFundingAvailability,
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
        expect(wrapper.text()).toContain('Scan to pay exactly ₱250.00');
        expect(
            wrapper
                .get('[data-testid="cockpit-funding-qr"] img')
                .attributes('src'),
        ).toBe('data:image/png;base64,AA==');
        expect(wrapper.text()).toContain('915001234567890123456');
        expect(wrapper.text()).toContain('Check NetBank');
        expect(wrapper.text()).toContain('Reusable Funding Address');
        expect(wrapper.text()).toContain('Generate reusable QR');
        expect(wrapper.text()).toContain(
            'it does not create a Funding Intent or credit the Account',
        );
        expect(wrapper.text()).toContain('Reopen QR');
        expect(wrapper.text()).toContain(
            'The Account changes only after independent provider verification',
        );
        expect(wrapper.text()).not.toContain('provider transaction');
        expect(wrapper.findAll('table tbody tr')).toHaveLength(1);
        expect(wrapper.get('table').classes()).toContain('min-w-[56rem]');
        expect(usePollMock).toHaveBeenCalledWith(
            5000,
            {
                only: ['funding_read_model', 'funding_notice'],
            },
            {
                autoStart: true,
                mode: 'rest',
            },
        );
    });

    it('generates a temporary reusable QR and checks sanitized NetBank history', async () => {
        const fetch = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.netbank-reusable-funding-address.v1',
                    address: {
                        provider: 'netbank',
                        funding_address: '915001234567890123456',
                        masked_funding_address: '•••• 123456',
                        currency: 'PHP',
                        institution: 'NetBank',
                        merchant_name: 'X Change',
                        qr_code: 'data:image/png;base64,REUSABLE',
                        qr_mode: 'static',
                        transaction_type: 'p2m',
                        embedded_amount: false,
                        provider_generated: true,
                        temporary: true,
                        funding_intent_created: false,
                        automatic_credit_enabled: false,
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.netbank-reusable-funding-history.v1',
                    observations: [
                        {
                            reference: 'NB-ABC123',
                            gross_amount_minor: 2500,
                            fee_amount_minor: 0,
                            net_amount_minor: 2500,
                            gross_amount: '₱25.00',
                            net_amount: '₱25.00',
                            currency: 'PHP',
                            provider_status: 'settled',
                            occurred_at: '2026-07-23T01:05:00+00:00',
                            settled_at: '2026-07-23T01:06:00+00:00',
                        },
                    ],
                    checked_at: '2026-07-23T01:07:00+00:00',
                    balance_changed: false,
                    funding_intent_created: false,
                }),
            });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                reusable_funding_address: reusableFundingAvailability,
            },
        });

        await wrapper
            .get('[data-testid="generate-reusable-funding-address"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(fetch).toHaveBeenNthCalledWith(
            1,
            '/x/cockpit/funding/reusable-addresses/netbank',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify({
                    confirm_temporary_reusable_address: true,
                }),
            }),
        );
        expect(
            wrapper
                .get('[data-testid="reusable-funding-address-qr"]')
                .attributes('src'),
        ).toBe('data:image/png;base64,REUSABLE');
        expect(
            wrapper
                .get('[data-testid="reusable-funding-address-value"]')
                .text(),
        ).toContain('915001234567890123456');
        expect(wrapper.text()).toContain('Automatic credit');
        expect(wrapper.text()).toContain('Disabled');

        await wrapper
            .get('[data-testid="check-reusable-funding-history"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(fetch).toHaveBeenNthCalledWith(
            2,
            '/x/cockpit/funding/reusable-addresses/netbank/history-checks',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
            }),
        );
        expect(wrapper.text()).toContain('NB-ABC123');
        expect(wrapper.text()).toContain('₱25.00');
        expect(wrapper.text()).toContain('Settled');

        await wrapper
            .get('[data-testid="hide-reusable-funding-address"]')
            .trigger('click');

        expect(
            wrapper
                .find('[data-testid="reusable-funding-address-qr"]')
                .exists(),
        ).toBe(false);
    });

    it('reopens an owner-scoped QR without placing it in the general read model', async () => {
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({
                instruction: {
                    reference: '01J-FUNDING-1',
                    provider: 'netbank',
                    amount: '₱250.00',
                    currency: 'PHP',
                    status: 'awaiting_funds',
                    expires_at: '2026-07-23T08:30:00+08:00',
                    qr_code: 'data:image/png;base64,REOPENED',
                    embedded_amount: true,
                    provider_generated: true,
                    balance_changed: false,
                    sensitive: true,
                },
            }),
        });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
            },
        });

        await wrapper
            .get('[data-testid="reopen-funding-instructions-01J-FUNDING-1"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(fetch).toHaveBeenCalledWith(
            '/x/cockpit/funding/intents/01J-FUNDING-1/instructions',
            expect.objectContaining({
                method: 'GET',
                credentials: 'same-origin',
            }),
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-funding-qr"] img')
                .attributes('src'),
        ).toBe('data:image/png;base64,REOPENED');
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

    it('shows installed providers while keeping disabled funding intake locked', () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: {
                    ...fundingReadModel,
                    providers: fundingReadModel.providers.map((provider) => ({
                        ...provider,
                        status: 'disabled',
                    })),
                },
            },
        });

        const provider = wrapper.get(
            '[data-testid="cockpit-funding-provider"]',
        );
        const providerText = provider.text().replace(/\s+/g, ' ');

        expect(providerText).toContain('No funding provider enabled');
        expect(providerText).toContain('NetBank · Shared (disabled)');
        expect(providerText).toContain('Paynamics · Shared (disabled)');
        expect(wrapper.text()).toContain('2 installed');
        expect(wrapper.text()).toContain('Funding Intake stays locked');
        expect(provider.attributes('disabled')).toBeUndefined();
        expect(
            wrapper
                .get('[data-testid="cockpit-funding-submit"]')
                .attributes('disabled'),
        ).toBeDefined();
    });

    it('offers a local Funding Intent happy path without enabling live providers', () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: {
                    ...fundingReadModel,
                    providers: [
                        ...fundingReadModel.providers.map((provider) => ({
                            ...provider,
                            status: 'disabled',
                        })),
                        {
                            code: 'qrph_simulator',
                            label: 'QR Ph Simulator',
                            status: 'available',
                            authoritative_verification: true,
                            destination_mode: 'shared',
                            destination_status: 'simulation_only',
                            destination_reference: 'Local simulated clearing',
                            simulation_only: true,
                        },
                    ],
                },
            },
        });

        expect(
            wrapper.get('[data-testid="cockpit-funding-provider"]').element,
        ).toHaveProperty('value', 'qrph_simulator');
        expect(wrapper.text()).toContain('Local happy path');
        expect(wrapper.text()).toContain(
            'Create simulated funding instructions',
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-funding-submit"]')
                .attributes('disabled'),
        ).toBeUndefined();
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
