import { flushPromises, mount } from '@vue/test-utils';
import { useEcho } from '@laravel/echo-vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import Funding from '../../../resources/js/cockpit/pages/Funding.vue';

const {
    echoCallback,
    workflowEchoCallback,
    routerPostMock,
    routerReloadMock,
    usePollMock,
} = vi.hoisted(() => ({
    echoCallback: {
        current: null as null | ((payload: Record<string, string>) => void),
    },
    workflowEchoCallback: {
        current: null as null | ((payload: Record<string, string>) => void),
    },
    routerPostMock: vi.fn(),
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
            if (_event === '.FundingRequestChanged') {
                workflowEchoCallback.current = callback;
            } else {
                echoCallback.current = callback;
            }

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
            post: routerPostMock,
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

const standingFundingAddress = {
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
};

const fundingQrMerchantProfile = {
    name: 'Treasury Desk',
    city: 'Manila',
    merchant_category_code: '0000',
    merchant_name_template: '{name} - {city}',
    category_options: [],
    presentation_only: true as const,
    controls_routing: false as const,
    controls_settlement: false as const,
};

const fundingRealtime = {
    enabled: true,
    channel: 'x-change.funding.opaque-owner-token',
    event: '.FundingProjectionChanged' as const,
    workflow_event: '.FundingRequestChanged' as const,
};

const payCodeFundingPreview = {
    eligible: true,
    status: 'eligible',
    message: 'This Pay Code can be added to Client Funds.',
    code_hint: '••••F9K2',
    amount: '₱20,000.00',
    currency: 'PHP',
    expires_at: '2026-08-01T08:00:00+08:00',
    provider_calls: false as const,
    inspection_token:
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ012345678901',
};

const fundingRequestReadModel = {
    schema: 'x-change.cockpit.account-funding-requests.v1',
    requests: [
        {
            reference: '01J-REQUEST-1',
            type: 'bank_transfer',
            type_label: 'Bank transfer',
            requested_value: '₱20,000.00',
            recognized_value: '₱20,000.00',
            currency: 'PHP',
            status: 'pay_code_issued',
            receipt_status: 'funding',
            receipt_status_label: 'Adding funds',
            description: 'Matched corporate bank transfer.',
            transfer: {
                provider: 'netbank',
                target_label: 'NetBank ••••0019',
                reference_hint: '••••1236',
                verification_status: 'ready_to_check',
                last_checked_at: null,
                can_check: true,
                provider_authority_required: true as const,
            },
            submitted_at: '2026-07-25T08:00:00+08:00',
            completed_at: null,
            evidence: {
                attachment_count: 1,
                pending_count: 0,
                accepted_count: 1,
                envelope_status: 'locked',
                documents: [
                    {
                        id: 81,
                        type: 'BANK_TRANSFER_PROOF',
                        filename: 'bank-transfer-proof.pdf',
                        mime_type: 'application/pdf',
                        size: 24000,
                        review_status: 'accepted',
                        url: '/x/cockpit/funding/requests/01J-REQUEST-1/evidence/81',
                    },
                ],
            },
            pay_code: {
                request_reference: '01J-REQUEST-1',
                code: 'FUNDF9K2',
                display_code: 'FUNDF9K2',
                last_four: 'F9K2',
                status: 'awaiting_system_treasury',
                amount: '₱20,000.00',
                voucher_type: 'payable',
                collection_mode: 'system_treasury',
                can_claim: false,
                can_copy: true,
                expires_at: '2026-08-01T08:00:00+08:00',
            },
        },
    ],
    notices: [],
    review_queue: [],
    controls: {
        attachments_enabled: true,
        evidence_authorizes_credit: false,
        maker_checker_required: true,
        reviewer: false,
        provider_payout_enabled: false,
    },
    redactions: {},
};

describe('Cockpit Funding foundation', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        routerPostMock.mockClear();
        routerReloadMock.mockClear();
        usePollMock.mockClear();
        vi.mocked(useEcho).mockClear();
        echoCallback.current = null;
        workflowEchoCallback.current = null;
    });

    it('renders provider-verified funding posture and opens the reusable QR immediately', async () => {
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({
                schema: 'x-change.cockpit.standing-funding-address.v1',
                address: standingFundingAddress,
            }),
        });
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: standingFundingAvailability,
                funding_qr_merchant_profile: fundingQrMerchantProfile,
                funding_realtime: fundingRealtime,
            },
        });
        await flushPromises();

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
                .get('[data-testid="funding-mode-pay_code"]')
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
        ).toContain('Liquidity & reconciliation');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('NetBank liquidity fresh');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Provider Inventory');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Position control');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('₱24,950.00');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Internal positions reconciled');
        expect(
            wrapper.get('[data-testid="funding-liquidity-control"]').text(),
        ).not.toContain('₱30,000.00');
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
                .get('[data-testid="funding-treasury-provider-breakdown"]')
                .text(),
        ).toContain('Internal positions reconciled');
        expect(
            wrapper
                .get('[data-testid="funding-provider-controls"]')
                .attributes('open'),
        ).toBeUndefined();
        expect(
            wrapper
                .get('[data-testid="funding-exception-controls"]')
                .attributes('open'),
        ).toBeUndefined();
        expect(
            wrapper.get('[data-testid="cockpit-funding-activity"]').text(),
        ).toContain('One-time Funding Intent History');
        expect(
            wrapper
                .get('[data-testid="cockpit-funding-activity"]')
                .element.closest('[data-testid="funding-provider-controls"]'),
        ).not.toBeNull();
        expect(
            wrapper
                .get('[data-testid="standing-funding-address-qr"]')
                .attributes('src'),
        ).toBe('data:image/png;base64,REUSABLE');
        expect(wrapper.text()).toContain('Check NetBank');
        expect(wrapper.text()).toContain('Account Funding QR Ph');
        expect(
            wrapper
                .get('[data-testid="cockpit-standing-funding-address"]')
                .text(),
        ).not.toContain('915001234567890123456');
        expect(
            wrapper
                .get('[data-testid="cockpit-standing-funding-address"]')
                .text(),
        ).not.toContain('production rejects this scheme');
        expect(
            wrapper.get('[data-testid="funding-provider-controls"]').text(),
        ).toContain('production rejects this scheme');
        expect(wrapper.text()).not.toContain('Purpose bound');
        expect(wrapper.text()).not.toContain('Reusable address');
        expect(wrapper.text()).not.toContain('Stable NetBank QR Ph address');
        expect(wrapper.text()).not.toContain('Payer enters amount');
        expect(wrapper.text()).not.toContain('Per-transfer range');
        expect(wrapper.text()).not.toContain(
            'Scanning the QR does not itself change the Account',
        );
        expect(
            wrapper.get('[data-testid="funding-qr-merchant-profile"]').text(),
        ).toContain('Update QR');
        expect(
            wrapper.get('[data-testid="funding-qr-merchant-profile"]').text(),
        ).toContain('Merchant label');
        expect(
            wrapper.get('[data-testid="funding-qr-merchant-profile"]').text(),
        ).not.toContain('QR presentation');
        expect(
            wrapper
                .get('[data-testid="funding-qr-merchant-profile"]')
                .findAll('input')
                .map((input) => (input.element as HTMLInputElement).value),
        ).toEqual(['Treasury Desk', 'Manila']);
        expect(wrapper.text()).not.toContain('Reveal Account Funding QR');
        expect(wrapper.text()).not.toContain(
            'Reveal your reusable QR Ph address',
        );
        expect(wrapper.text()).not.toContain('Hide sensitive QR');
        expect(wrapper.text()).not.toContain('Top up an exact amount');
        expect(wrapper.text()).not.toContain('Create one-time instructions');
        expect(wrapper.text()).not.toContain('provider transaction');
        expect(wrapper.findAll('table tbody tr')).toHaveLength(1);
        expect(wrapper.get('table').classes()).toContain('min-w-[56rem]');
        expect(usePollMock).toHaveBeenCalledWith(
            5000,
            {
                only: [
                    'cockpit_header_read_model',
                    'funding_read_model',
                    'funding_requests',
                    'funding_notice',
                ],
            },
            {
                autoStart: true,
                mode: 'rest',
            },
        );
    });

    it('explains when stale provider liquidity pauses issuance capacity', () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: {
                    ...fundingReadModel,
                    treasury_portfolio: {
                        ...fundingReadModel.treasury_portfolio,
                        connections:
                            fundingReadModel.treasury_portfolio.connections.map(
                                (connection) =>
                                    connection.provider === 'netbank'
                                        ? {
                                              ...connection,
                                              provider_liquidity_is_stale: true,
                                              provider_liquidity_status:
                                                  'stale',
                                              provider_liquidity_checked_at:
                                                  '2026-07-23T18:12:00+08:00',
                                              issuance_capacity: null,
                                              issuance_capacity_minor: null,
                                          }
                                        : connection,
                            ),
                    },
                },
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
            },
        });

        expect(
            wrapper.get('[data-testid="funding-liquidity-freshness"]').text(),
        ).toContain('NetBank liquidity stale');
        expect(
            wrapper.get('[data-testid="funding-liquidity-freshness"]').text(),
        ).toContain('Jul 23, 2026');
        expect(
            wrapper.get('[data-testid="funding-liquidity-refresh"]').text(),
        ).toBe('Refresh liquidity');
    });

    it('requests a server-resolved liquidity refresh without financial input', async () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
            },
        });

        expect(
            wrapper.get('[data-testid="funding-liquidity-freshness"]').text(),
        ).toContain('NetBank liquidity fresh');

        await wrapper
            .get('[data-testid="funding-liquidity-refresh"]')
            .trigger('click');

        expect(routerPostMock).toHaveBeenCalledOnce();
        expect(routerPostMock.mock.calls[0]?.[0]).toMatchObject({
            url: '/x/cockpit/funding/liquidity-refreshes',
            method: 'post',
        });
        expect(routerPostMock.mock.calls[0]?.[1]).toEqual({});
    });

    it('keeps two funding paths primary and removes exact-amount tooling', async () => {
        const fetch = vi.fn();
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                    status: 'not_configured',
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
            wrapper.find('[data-testid="exact-amount-self-top-up"]').exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="funding-mode-funding_intent"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper.get('[data-testid="funding-advanced-paths"]').text(),
        ).toContain('Lifecycle simulation');
        expect(
            wrapper.get('[data-testid="funding-advanced-paths"]').text(),
        ).not.toContain('Exact provider instructions');

        await wrapper
            .get('[data-testid="funding-mode-pay_code"]')
            .trigger('click');
        await nextTick();

        expect(fetch).not.toHaveBeenCalled();
        expect(
            wrapper
                .get('[data-testid="funding-mode-pay_code"]')
                .attributes('aria-selected'),
        ).toBe('true');
        expect(
            wrapper.get('#funding-panel-pay_code').attributes('style'),
        ).not.toContain('display: none');
        expect(
            wrapper.find('[data-testid="funding-mode-description"]').exists(),
        ).toBe(false);

        expect(fetch).not.toHaveBeenCalled();
    });

    it('makes Pay Code Funding primary and keeps reviewed requests secondary', async () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                funding_requests: fundingRequestReadModel,
                pay_code_funding_preview: payCodeFundingPreview,
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
            },
        });

        await wrapper
            .get('[data-testid="funding-mode-pay_code"]')
            .trigger('click');
        await nextTick();

        const panel = wrapper.get('[data-testid="cockpit-pay-code-funding"]');

        expect(panel.text()).toContain('Fund with Pay Code');
        expect(
            panel
                .get('[data-testid="pay-code-funding-inspection-form"] input')
                .attributes('placeholder'),
        ).toBe('Enter Pay Code');
        expect(panel.text()).toContain('Check the code');
        expect(panel.text()).toContain('Check Code');
        expect(panel.text()).toContain('••••F9K2');
        expect(panel.text()).toContain('₱20,000.00');
        expect(panel.text()).toContain('Confirm Account funding');
        expect(panel.text()).toContain('Ready to add');
        expect(panel.text()).toContain(
            'Code checked. No funds have moved yet.',
        );
        expect(panel.text()).toContain('Add ₱20,000.00 to my Account');
        expect(panel.text()).toContain('no provider payout');
        expect(
            panel
                .get('[data-testid="funding-request-form"]')
                .attributes('open'),
        ).toBeUndefined();
        expect(panel.text()).toContain('Request Account Funding');
        expect(panel.text()).toContain('Message');
        expect(panel.text()).toContain('(optional)');
        expect(panel.text()).toContain('Add proof or transfer details');
        expect(panel.text()).toContain('Evidence document');
        expect(
            panel
                .get('[data-testid="funding-request-evidence"]')
                .attributes('accept'),
        ).toContain('application/pdf');
        expect(panel.text()).toContain('Request Account Funding');
        expect(panel.text()).toContain('Account Funding Requests');
        expect(panel.text()).toContain('Pay Code');
        expect(panel.text()).toContain('Amount');
        expect(panel.text()).toContain('Status');
        expect(panel.text()).toContain('Requested');
        expect(panel.text()).toContain('Funded');
        expect(panel.text()).toContain('Control');
        expect(panel.text()).toContain('FUNDF9K2');
        expect(panel.text()).toContain('Adding funds');
        expect(panel.text()).toContain('bank-transfer-proof.pdf');
        expect(panel.text()).not.toContain('Verification details');
        expect(panel.text()).not.toContain('Submit for Review');
        expect(
            panel
                .find('[data-testid="claim-reviewed-funding-pay-code"]')
                .exists(),
        ).toBe(false);
        expect(panel.text()).not.toContain('wallet');
        expect(panel.text()).not.toContain('Two different operators');
        expect(panel.text()).not.toContain('1 · Request');
        expect(panel.text()).not.toContain('2 · Verify and reserve');
        expect(panel.text()).not.toContain('3 · Claim once');
        expect(
            wrapper.find('[data-testid="open-funding-request-modal"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="funding-request-modal"]').exists(),
        ).toBe(false);
    });

    it('keeps privileged maker-checker controls out of the requester workspace', async () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                funding_requests: {
                    ...fundingRequestReadModel,
                    review_queue: [
                        {
                            reference: '01J-REQUEST-1',
                            type: 'bank_transfer',
                            type_label: 'Bank transfer',
                            requested_value: '₱20,000.00',
                            recognized_value: '₱20,000.00',
                            requested_value_minor: 2_000_000,
                            currency: 'PHP',
                            status: 'awaiting_approval',
                            description: 'Matched corporate bank transfer.',
                            evidence_reference: 'bank-match:1001',
                            connection_reference: 'netbank-primary',
                            maker_id: '41',
                            can_prepare: false,
                            can_approve: true,
                        },
                    ],
                    controls: {
                        ...fundingRequestReadModel.controls,
                        reviewer: true,
                    },
                },
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
            },
        });

        await wrapper
            .get('[data-testid="funding-mode-pay_code"]')
            .trigger('click');

        expect(
            wrapper
                .find('[data-testid="funding-request-review-queue"]')
                .exists(),
        ).toBe(false);
        expect(wrapper.text()).not.toContain('Approve and fund Account');
        expect(wrapper.text()).not.toContain(
            'Record backing and request approval',
        );
    });

    it('shows and copies the newly requested Pay Code and follow-up message', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', {
            clipboard: { writeText },
        });
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                funding_requests: {
                    ...fundingRequestReadModel,
                    requests: [
                        {
                            ...fundingRequestReadModel.requests[0],
                            status: 'submitted',
                            receipt_status: 'pending',
                            receipt_status_label: 'Pending',
                            pay_code: {
                                ...fundingRequestReadModel.requests[0].pay_code,
                                code: 'FUNDABCD',
                                display_code: 'FUNDABCD',
                                status: 'locked_pending_review',
                            },
                        },
                    ],
                },
                funding_request_submitted_reference: '01J-REQUEST-1',
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
            },
        });

        await wrapper
            .get('[data-testid="funding-mode-pay_code"]')
            .trigger('click');
        const result = wrapper.get('[data-testid="funding-request-result"]');
        const buttons = result.findAll('button');

        expect(result.text()).toContain('Funding requested');
        expect(result.text()).toContain('₱20,000.00');
        expect(result.text()).toContain('FUNDABCD');
        expect(result.text()).toContain('Pending');

        await buttons[0].trigger('click');
        await flushPromises();
        expect(writeText).toHaveBeenCalledWith('FUNDABCD');

        await buttons[1].trigger('click');
        await flushPromises();
        expect(writeText).toHaveBeenCalledWith(
            'Please process my ₱20,000.00 Account Funding request. Pay Code: FUNDABCD.',
        );
    });

    it('checks a bank transfer through the owner-scoped Wayfinder route', async () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                funding_requests: fundingRequestReadModel,
                funding_workspace_mode: 'pay_code',
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
            },
        });

        const requests = wrapper.get('[data-testid="my-funding-requests"]');

        expect(requests.text()).toContain('NetBank ••••0019');
        expect(requests.text()).toContain('Ref ••••1236');

        await requests
            .findAll('button')
            .find((button) => button.text() === 'Check transfer')
            ?.trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/x/cockpit/funding/requests/01J-REQUEST-1/transfer-checks',
            {},
            expect.objectContaining({
                preserveScroll: true,
            }),
        );
    });

    it('refreshes balance projections once for a valid private funding event', async () => {
        vi.useFakeTimers();
        mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
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
            only: [
                'cockpit_header_read_model',
                'funding_read_model',
                'funding_requests',
            ],
            preserveScroll: true,
            preserveState: true,
        });
        vi.useRealTimers();
    });

    it('does not open a realtime connection unless broadcasting is explicitly enabled', () => {
        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
                funding_realtime: {
                    ...fundingRealtime,
                    enabled: false,
                },
            },
        });

        expect(useEcho).not.toHaveBeenCalled();
    });

    it('refreshes request history for a valid private workflow event', () => {
        mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: {
                    ...standingFundingAvailability,
                    available: false,
                },
                funding_realtime: fundingRealtime,
            },
        });

        workflowEchoCallback.current?.({
            schema: 'x-change.funding-request-changed.v1',
            event_id: 'request-event-1',
            reason: 'funding_request_changed',
            request_reference: '01J-REQUEST-1',
            status: 'submitted',
            occurred_at: '2026-07-25T08:00:00+08:00',
        });

        expect(routerReloadMock).toHaveBeenCalledOnce();
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

        await flushPromises();

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
                .get('[data-testid="cockpit-standing-funding-address"]')
                .text(),
        ).not.toContain('915001234567890123456');
        expect(wrapper.text()).toContain('Merchant label');
        expect(wrapper.text()).toContain('Update QR');

        await wrapper
            .get('[data-testid="check-standing-funding-history"]')
            .trigger('click');
        await flushPromises();

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
        await flushPromises();
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
            only: [
                'cockpit_header_read_model',
                'funding_read_model',
                'funding_requests',
            ],
            preserveScroll: true,
            preserveState: true,
        });

        expect(
            wrapper
                .find('[data-testid="hide-standing-funding-address"]')
                .exists(),
        ).toBe(false);
    });

    it('restores persisted Account Funding Receipts without checking NetBank again', async () => {
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({
                schema: 'x-change.cockpit.standing-funding-address.v1',
                address: standingFundingAddress,
                persisted_history: {
                    observations: [
                        {
                            reference: 'AF-PERSISTED123',
                            gross_amount_minor: 5000,
                            fee_amount_minor: 0,
                            net_amount_minor: 5000,
                            gross_amount: '₱50.00',
                            net_amount: '₱50.00',
                            currency: 'PHP',
                            status: 'settled',
                            provider_status: 'settled',
                            applied: true,
                            applied_amount_minor: 5000,
                            applied_amount: '₱50.00',
                            applied_at: '2026-07-25T08:55:00+00:00',
                            provisional: false,
                            can_approve: false,
                            approval_reference: null,
                            occurred_at: '2026-07-25T08:54:00+00:00',
                            provider_settled_at: '2026-07-25T08:55:00+00:00',
                        },
                    ],
                    last_checked_at: '2026-07-25T08:56:00+00:00',
                    provider_calls: false,
                },
            }),
        });
        vi.stubGlobal('fetch', fetch);

        const wrapper = mount(Funding, {
            props: {
                funding_read_model: fundingReadModel,
                standing_funding_address: standingFundingAvailability,
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('AF-PERSISTED123');
        expect(wrapper.text()).toContain('Yes · ₱50.00');
        expect(wrapper.text()).toContain('Last synchronized');
        expect(fetch).toHaveBeenCalledOnce();
        expect(fetch).toHaveBeenCalledWith(
            '/x/cockpit/funding/standing-addresses/netbank',
            expect.objectContaining({
                method: 'POST',
            }),
        );
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

        await flushPromises();
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
            only: [
                'cockpit_header_read_model',
                'funding_read_model',
                'funding_requests',
            ],
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

        await flushPromises();
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

        expect(wrapper.text()).toContain('No one-time funding intents');
        expect(
            wrapper.find('[data-testid="funding-exception-controls"]').exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain(
            'No provider Treasury connection is configured.',
        );
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).toContain('Provider Inventory Not available');
        expect(
            wrapper.get('[data-testid="funding-treasury-portfolio"]').text(),
        ).not.toContain('Issuance Capacity');
    });

    it('shows installed providers without exposing exact-amount intake', () => {
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

        const providerControls = wrapper.get(
            '[data-testid="funding-provider-controls"]',
        );
        expect(providerControls.text()).toContain('NetBank');
        expect(providerControls.text()).toContain('Paynamics');
        expect(wrapper.text()).toContain('2 installed');
        expect(
            wrapper.find('[data-testid="cockpit-funding-submit"]').exists(),
        ).toBe(false);
    });

    it('keeps simulation providers out of operational funding activity', () => {
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
            wrapper.get('[data-testid="funding-provider-controls"]').text(),
        ).toContain('QR Ph Simulator');
        expect(
            wrapper.get('[data-testid="cockpit-funding-activity"]').text(),
        ).toContain('No one-time funding intents');
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
