import { mount } from '@vue/test-utils';
import { useEcho } from '@laravel/echo-vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import Funding from '../../../resources/js/cockpit/pages/Funding.vue';

const { echoCallback, routerReloadMock, usePollMock } = vi.hoisted(() => ({
    echoCallback: {
        current: null as null | ((payload: Record<string, string>) => void),
    },
    routerReloadMock: vi.fn(),
    usePollMock: vi.fn(() => ({
        start: vi.fn(),
        stop: vi.fn(),
    })),
}));

vi.mock('@laravel/echo-vue', () => ({
    useEcho: vi.fn(
        (
            _channel: string,
            _event: string,
            callback: (payload: Record<string, string>) => void,
        ) => {
            echoCallback.current = callback;

            return {
                leaveChannel: vi.fn(),
                leave: vi.fn(),
                stopListening: vi.fn(),
                listen: vi.fn(),
                channel: vi.fn(),
            };
        },
    ),
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();

    return {
        ...actual,
        router: {
            ...actual.router,
            reload: routerReloadMock,
        },
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
    treasury_portfolio: {
        schema: 'x-change.cockpit.funding-treasury-portfolio.v1',
        status: 'available',
        read_only: true,
        provider_calls: false,
        currency: 'PHP',
        vocabulary: {},
        totals: {
            client_funds_minor: 2_000_000,
            client_funds: '₱20,000.00',
            pay_code_reserve_minor: 495_000,
            pay_code_reserve: '₱4,950.00',
            account_position_minor: 2_495_000,
            account_position: '₱24,950.00',
            provider_inventory_minor: 2_495_000,
            provider_inventory: '₱24,950.00',
            issuance_capacity_minor: 1_505_000,
            issuance_capacity: '₱15,050.00',
        },
        connections: [
            {
                provider: 'netbank',
                provider_label: 'NetBank',
                mode: 'live',
                currency: 'PHP',
                status: 'active',
                client_funds_minor: 2_000_000,
                client_funds: '₱20,000.00',
                pay_code_reserve_minor: 495_000,
                pay_code_reserve: '₱4,950.00',
                account_position_minor: 2_495_000,
                account_position: '₱24,950.00',
                provider_inventory_minor: 2_495_000,
                provider_inventory: '₱24,950.00',
                provider_liquidity_minor: 3_000_000,
                provider_liquidity: '₱30,000.00',
                provider_liquidity_status: 'cached',
                provider_liquidity_is_stale: false,
                provider_liquidity_checked_at: '2026-07-24T09:00:00+08:00',
                issuance_capacity_minor: 1_505_000,
                issuance_capacity: '₱15,050.00',
                inventory_matches_positions: true,
                control_status: 'reconciled',
                provider_calls: false,
            },
            {
                provider: 'paynamics_constellation',
                provider_label: 'Paynamics',
                mode: 'disabled',
                currency: 'PHP',
                status: 'disabled',
                client_funds_minor: 0,
                client_funds: '₱0.00',
                pay_code_reserve_minor: 0,
                pay_code_reserve: '₱0.00',
                account_position_minor: 0,
                account_position: '₱0.00',
                provider_inventory_minor: null,
                provider_inventory: null,
                provider_liquidity_minor: null,
                provider_liquidity: null,
                provider_liquidity_status: 'disabled',
                provider_liquidity_is_stale: true,
                provider_liquidity_checked_at: null,
                issuance_capacity_minor: null,
                issuance_capacity: null,
                inventory_matches_positions: null,
                control_status: 'not_registered',
                provider_calls: false,
            },
        ],
        accounting_boundary: {
            provider_outflow: 'provider_principal_only',
            sender_system_charge: 'deferred_accounting_wave',
            provider_liquidity_source: 'cached_projection_only',
        },
        redactions: {
            raw_evidence_exposed: false,
        },
    },
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

const standingFundingAvailability = {
    enabled: true,
    available: true,
    status: 'available',
    provider: 'netbank' as const,
    exists: false,
    address_scheme: 'netbank-mobile-v1',
    scheme_label: 'Verified mobile suffix',
    scheme_warning:
        'Development-friendly but easier to correlate; production rejects this scheme.',
    production_safe: false,
    purpose: 'account_funding' as const,
    recognition_mode: 'supervised' as const,
    address_status: null,
    temporary: false as const,
    provider_calls: true as const,
    funding_intent_created: false as const,
    automatic_credit_enabled: false as const,
    minimum_amount_minor: 100,
    maximum_amount_minor: 5_000_000,
    daily_limit_minor: 10_000_000,
};

const fundingRealtime = {
    enabled: true,
    channel: 'x-change.funding.opaque-owner-token',
    event: '.FundingProjectionChanged' as const,
};

describe('Cockpit Funding foundation', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        routerReloadMock.mockClear();
        usePollMock.mockClear();
        vi.mocked(useEcho).mockClear();
        echoCallback.current = null;
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
                standing_funding_address: standingFundingAvailability,
                funding_realtime: fundingRealtime,
            },
        });

        expect(
            wrapper.get('[data-testid="cockpit-funding-page"]').text(),
        ).toContain('Account Funding');
        expect(
            wrapper.get('[data-testid="cockpit-funding-header"]').classes(),
        ).toContain('py-3');
        expect(
            wrapper.get('[data-testid="cockpit-funding-header"]').classes(),
        ).not.toContain('bg-slate-950');
        expect(
            wrapper
                .get('[data-testid="cockpit-funding-summary-strip"]')
                .findAll('article'),
        ).toHaveLength(4);
        expect(
            wrapper
                .get('[data-testid="funding-mode-self_top_up"]')
                .attributes('aria-selected'),
        ).toBe('true');
        expect(
            wrapper
                .get('[data-testid="funding-mode-funding_intent"]')
                .attributes('aria-selected'),
        ).toBe('false');
        expect(
            wrapper.html().indexOf('cockpit-funding-summary-strip'),
        ).toBeLessThan(
            wrapper.html().indexOf('cockpit-standing-funding-address'),
        );
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
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Funding position');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Client Funds');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Reserved for Pay Codes');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Provider Inventory');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Issuance Capacity');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('₱15,050.00');
        expect(
            wrapper
                .get('[data-testid="funding-treasury-provider-breakdown"]')
                .attributes('open'),
        ).toBeUndefined();
        expect(
            wrapper
                .get('[data-testid="funding-treasury-provider-breakdown"]')
                .text(),
        ).toContain('Provider Liquidity');
        expect(
            wrapper
                .get('[data-testid="funding-treasury-provider-breakdown"]')
                .text(),
        ).toContain('deferred Accounting Wave');
        expect(
            wrapper
                .get('[data-testid="funding-provider-controls"]')
                .attributes('open'),
        ).toBeUndefined();
        expect(
            wrapper
                .get('[data-testid="funding-exception-controls"]')
                .attributes('open'),
        ).toBe('');
        expect(
            wrapper.get('[data-testid="cockpit-funding-activity"]').text(),
        ).toContain('Funding Activity');
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
        expect(wrapper.text()).toContain('Account Funding Address');
        expect(wrapper.text()).toContain('Verified mobile suffix');
        expect(wrapper.text()).toContain('production rejects this scheme');
        expect(wrapper.text()).toContain('Create Account Funding QR');
        expect(wrapper.text()).toContain('Live funding updates');
        expect(wrapper.text()).toContain(
            'payer mobile, amount, timing, and merchant text never decide',
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

    it('keeps self top-up primary and changes funding modes without provider activity', async () => {
        const fetch = vi.fn();
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: {
                    ...standingFundingAvailability,
                    exists: true,
                },
                funding_simulation: fundingSimulation,
            },
        });

        expect(fetch).not.toHaveBeenCalled();
        expect(
            wrapper
                .get('[data-testid="funding-mode-self_top_up"]')
                .attributes('aria-selected'),
        ).toBe('true');
        expect(
            wrapper
                .get('[data-testid="cockpit-funding-intent-form"]')
                .isVisible(),
        ).toBe(false);

        await wrapper
            .get('[data-testid="funding-mode-funding_intent"]')
            .trigger('click');
        await nextTick();

        expect(fetch).not.toHaveBeenCalled();
        expect(
            wrapper
                .get('[data-testid="funding-mode-funding_intent"]')
                .attributes('aria-selected'),
        ).toBe('true');
        expect(
            wrapper.get('#funding-panel-funding_intent').attributes('style'),
        ).not.toContain('display: none');
        expect(
            wrapper.get('[data-testid="funding-mode-description"]').text(),
        ).toContain('one-time provider instructions');

        await wrapper
            .get('[data-testid="funding-mode-simulation"]')
            .trigger('click');
        await nextTick();

        expect(
            wrapper
                .get('[data-testid="funding-mode-simulation"]')
                .attributes('aria-selected'),
        ).toBe('true');
        expect(
            wrapper.get('#funding-panel-simulation').attributes('style'),
        ).not.toContain('display: none');
        expect(fetch).not.toHaveBeenCalled();
    });

    it('refreshes balance projections once for a valid private funding event', async () => {
        vi.useFakeTimers();
        mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: standingFundingAvailability,
                funding_realtime: fundingRealtime,
            },
        });

        echoCallback.current?.({
            schema: 'x-change.funding-projection-changed.v1',
            event_id: 'event-1',
            reason: 'account_funding_settled',
            occurred_at: '2026-07-24T09:00:00+08:00',
        });
        echoCallback.current?.({
            schema: 'x-change.funding-projection-changed.v1',
            event_id: 'event-1',
            reason: 'account_funding_settled',
            occurred_at: '2026-07-24T09:00:00+08:00',
        });
        await vi.runAllTimersAsync();

        expect(routerReloadMock).toHaveBeenCalledOnce();
        expect(routerReloadMock).toHaveBeenCalledWith({
            only: ['cockpit_header_read_model', 'funding_read_model'],
            preserveScroll: true,
            preserveState: true,
        });
        vi.useRealTimers();
    });

    it('does not open a realtime connection unless broadcasting is explicitly enabled', () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: standingFundingAvailability,
                funding_realtime: {
                    ...fundingRealtime,
                    enabled: false,
                },
            },
        });

        expect(useEcho).not.toHaveBeenCalled();
        expect(
            wrapper.find('[data-testid="funding-realtime-status"]').exists(),
        ).toBe(false);
    });

    it('opens a standing QR, checks sanitized receipts, and approves supervised credit', async () => {
        const fetch = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.standing-funding-address.v1',
                    address: {
                        reference: '01J-STANDING-1',
                        provider: 'netbank',
                        funding_address: '915001234567890123456',
                        masked_funding_address: '•••• 123456',
                        purpose: 'account_funding',
                        recognition_mode: 'supervised',
                        status: 'active',
                        currency: 'PHP',
                        institution: 'NetBank',
                        merchant_name: 'X Change',
                        qr_code: 'data:image/png;base64,REUSABLE',
                        qr_mode: 'static',
                        transaction_type: 'p2m',
                        embedded_amount: false,
                        provider_generated: true,
                        temporary: false,
                        funding_intent_created: false,
                        automatic_credit_enabled: false,
                        minimum_amount_minor: 100,
                        maximum_amount_minor: 5_000_000,
                        daily_limit_minor: 10_000_000,
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.standing-funding-history.v1',
                    observations: [
                        {
                            reference: 'AF-ABC123',
                            gross_amount_minor: 2500,
                            fee_amount_minor: 0,
                            net_amount_minor: 2500,
                            gross_amount: '₱25.00',
                            net_amount: '₱25.00',
                            currency: 'PHP',
                            status: 'awaiting_approval',
                            provider_status: 'settled',
                            applied: false,
                            applied_amount_minor: 0,
                            applied_amount: '₱0.00',
                            applied_at: null,
                            provisional: false,
                            can_approve: true,
                            approval_reference: '01KY8R71ZNS1Y8HTRPQ7QDD41Q',
                            occurred_at: '2026-07-23T01:05:00+00:00',
                            provider_settled_at: '2026-07-23T01:06:00+00:00',
                        },
                    ],
                    checked_at: '2026-07-23T01:07:00+00:00',
                    balance_changed: false,
                    funding_intent_created: false,
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.account-funding-receipt-approval.v1',
                    receipt: {
                        reference: 'AF-ABC123',
                        status: 'settled',
                        settled_at: '2026-07-23T01:08:00+00:00',
                    },
                    message:
                        'Verified funding was recognized in Treasury Inventory and credited to the Account.',
                }),
            });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: standingFundingAvailability,
            },
        });

        await wrapper
            .get('[data-testid="open-standing-funding-address"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(fetch).toHaveBeenNthCalledWith(
            1,
            '/x/cockpit/funding/standing-addresses/netbank',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify({
                    confirm_account_funding_address: true,
                }),
            }),
        );
        expect(
            wrapper
                .get('[data-testid="standing-funding-address-qr"]')
                .attributes('src'),
        ).toBe('data:image/png;base64,REUSABLE');
        expect(
            wrapper
                .get('[data-testid="standing-funding-address-value"]')
                .text(),
        ).toContain('915001234567890123456');
        expect(wrapper.text()).toContain('Recognition');
        expect(wrapper.text()).toContain('Supervised');

        await wrapper
            .get('[data-testid="check-standing-funding-history"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(fetch).toHaveBeenNthCalledWith(
            2,
            '/x/cockpit/funding/standing-addresses/netbank/history-checks',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
            }),
        );
        expect(wrapper.text()).toContain('AF-ABC123');
        expect(wrapper.text()).toContain('₱25.00');
        expect(wrapper.text()).toContain('Awaiting Approval');
        expect(wrapper.text()).toContain('No');

        await wrapper
            .get('[data-testid="approve-standing-funding-receipt"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(fetch).toHaveBeenNthCalledWith(
            3,
            '/x/cockpit/funding/standing-addresses/netbank/receipts/01KY8R71ZNS1Y8HTRPQ7QDD41Q/approve',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
            }),
        );
        expect(wrapper.text()).toContain(
            'Verified funding was recognized in Treasury Inventory',
        );
        expect(wrapper.text()).toContain('Yes · ₱25.00');
        expect(routerReloadMock).toHaveBeenCalledOnce();
        expect(routerReloadMock).toHaveBeenCalledWith({
            only: ['cockpit_header_read_model', 'funding_read_model'],
            preserveScroll: true,
            preserveState: true,
        });

        await wrapper
            .get('[data-testid="hide-standing-funding-address"]')
            .trigger('click');

        expect(
            wrapper
                .find('[data-testid="standing-funding-address-qr"]')
                .exists(),
        ).toBe(false);
    });

    it('shows a pending NetBank receipt as applied once without confusing it with final settlement', async () => {
        const automaticAddress = {
            ...standingFundingAvailability,
            exists: true,
            recognition_mode: 'automatic' as const,
            automatic_credit_enabled: true as const,
        };
        const appliedReceipt = {
            reference: 'AF-PENDING123',
            gross_amount_minor: 3000,
            fee_amount_minor: 0,
            net_amount_minor: 3000,
            gross_amount: '₱30.00',
            net_amount: '₱30.00',
            currency: 'PHP',
            status: 'settled',
            provider_status: 'pending',
            applied: true,
            applied_amount_minor: 3000,
            applied_amount: '₱30.00',
            applied_at: '2026-07-24T01:05:00+00:00',
            provisional: true,
            can_approve: false,
            approval_reference: null,
            occurred_at: '2026-07-24T01:02:00+00:00',
            provider_settled_at: null,
        };
        const fetch = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.standing-funding-address.v1',
                    address: {
                        reference: '01J-STANDING-AUTO',
                        provider: 'netbank',
                        funding_address: '9150012345678901',
                        masked_funding_address: '•••• 678901',
                        purpose: 'account_funding',
                        recognition_mode: 'automatic',
                        status: 'active',
                        currency: 'PHP',
                        institution: 'NetBank',
                        merchant_name: 'X Change',
                        qr_code: 'data:image/png;base64,REUSABLE',
                        qr_mode: 'static',
                        transaction_type: 'p2m',
                        embedded_amount: false,
                        provider_generated: true,
                        temporary: false,
                        funding_intent_created: false,
                        automatic_credit_enabled: true,
                        minimum_amount_minor: 100,
                        maximum_amount_minor: 5_000_000,
                        daily_limit_minor: 10_000_000,
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.standing-funding-history.v1',
                    observations: [appliedReceipt],
                    checked_at: '2026-07-24T01:06:00+00:00',
                    balance_changed: true,
                    funding_intent_created: false,
                    sync: {
                        observed: 0,
                        settled: 1,
                        applied: 1,
                        awaiting_approval: 0,
                        suspense: 0,
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.standing-funding-history.v1',
                    observations: [appliedReceipt],
                    checked_at: '2026-07-24T01:07:00+00:00',
                    balance_changed: false,
                    funding_intent_created: false,
                    sync: {
                        observed: 0,
                        settled: 1,
                        applied: 0,
                        awaiting_approval: 0,
                        suspense: 0,
                    },
                }),
            });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: automaticAddress,
            },
        });

        expect(fetch).not.toHaveBeenCalled();
        await wrapper
            .get('[data-testid="open-standing-funding-address"]')
            .trigger('click');
        await nextTick();
        await nextTick();
        await wrapper
            .get('[data-testid="check-standing-funding-history"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(wrapper.text()).toContain('Pending');
        expect(wrapper.text()).toContain('Yes · ₱30.00');
        expect(wrapper.text()).toContain('Provisional provider status');
        expect(wrapper.text()).toContain(
            'New NetBank funding was applied to Client Funds exactly once.',
        );
        expect(routerReloadMock).toHaveBeenCalledOnce();
        expect(routerReloadMock).toHaveBeenCalledWith({
            only: ['cockpit_header_read_model', 'funding_read_model'],
            preserveScroll: true,
            preserveState: true,
        });

        await wrapper
            .get('[data-testid="check-standing-funding-history"]')
            .trigger('click');
        await nextTick();
        await nextTick();

        expect(wrapper.text()).toContain(
            'Previously applied receipts were not applied again.',
        );
        expect(fetch).toHaveBeenCalledTimes(3);
        expect(routerReloadMock).toHaveBeenCalledOnce();
    });

    it('honors the server cooldown after a rate-limited NetBank check', async () => {
        const automaticAddress = {
            ...standingFundingAvailability,
            exists: true,
            recognition_mode: 'automatic' as const,
            automatic_credit_enabled: true as const,
        };
        const fetch = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    schema: 'x-change.cockpit.standing-funding-address.v1',
                    address: {
                        reference: '01J-STANDING-COOLDOWN',
                        provider: 'netbank',
                        funding_address: '9150012345678901',
                        masked_funding_address: '•••• 678901',
                        purpose: 'account_funding',
                        recognition_mode: 'automatic',
                        status: 'active',
                        currency: 'PHP',
                        institution: 'NetBank',
                        merchant_name: 'X Change',
                        qr_code: 'data:image/png;base64,REUSABLE',
                        qr_mode: 'static',
                        transaction_type: 'p2m',
                        embedded_amount: false,
                        provider_generated: true,
                        temporary: false,
                        funding_intent_created: false,
                        automatic_credit_enabled: true,
                        minimum_amount_minor: 100,
                        maximum_amount_minor: 5_000_000,
                        daily_limit_minor: 10_000_000,
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: false,
                status: 429,
                headers: new Headers({ 'Retry-After': '45' }),
                json: async () => ({
                    message: 'Too Many Attempts.',
                }),
            });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: automaticAddress,
            },
        });

        expect(fetch).not.toHaveBeenCalled();
        await wrapper
            .get('[data-testid="open-standing-funding-address"]')
            .trigger('click');
        await nextTick();
        await wrapper
            .get('[data-testid="check-standing-funding-history"]')
            .trigger('click');
        await nextTick();

        const checkButton = wrapper.get(
            '[data-testid="check-standing-funding-history"]',
        );

        expect(checkButton.attributes('disabled')).toBeDefined();
        expect(checkButton.text()).toBe('Try again in 45s');
        expect(wrapper.text()).toContain(
            'NetBank was checked recently. Wait for the cooldown',
        );

        await checkButton.trigger('click');

        expect(fetch).toHaveBeenCalledTimes(2);
        wrapper.unmount();
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
                    treasury_portfolio: {
                        ...fundingReadModel.treasury_portfolio,
                        status: 'not_configured',
                        totals: {
                            client_funds_minor: 0,
                            client_funds: '₱0.00',
                            pay_code_reserve_minor: 0,
                            pay_code_reserve: '₱0.00',
                            account_position_minor: 0,
                            account_position: '₱0.00',
                            provider_inventory_minor: null,
                            provider_inventory: null,
                            issuance_capacity_minor: null,
                            issuance_capacity: null,
                        },
                        connections: [],
                    },
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
            'No provider Treasury connection is configured.',
        );
        expect(wrapper.text()).toContain('Provider InventoryNot available');
        expect(wrapper.text()).toContain('Issuance CapacityNot available');
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
                    intents: [
                        {
                            ...fundingReadModel.intents[0],
                            reference: '01J-SIMULATED-FUNDING',
                            provider: 'qrph_simulator',
                            can_check_provider: false,
                            can_reopen_instructions: false,
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
        expect(
            wrapper.get('[data-testid="cockpit-funding-activity"]').text(),
        ).toContain('Simulation only');
        expect(
            wrapper
                .get('[data-testid="cockpit-funding-activity"]')
                .find('[data-testid="check-netbank-01J-SIMULATED-FUNDING"]')
                .exists(),
        ).toBe(false);
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
