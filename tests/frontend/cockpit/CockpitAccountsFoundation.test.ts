import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Accounts from '../../../resources/js/cockpit/pages/Accounts.vue';

const accountReadModel = {
    schema: 'x-change.cockpit.account-management.v1',
    status: 'available',
    account: {
        reference: 'Account •••• 12345678',
        currency: 'PHP',
        ledger_authority: 'internal-account-ledger',
        funding_credit_policy: 'verified-provider-settlement-only',
    },
    providers: [
        {
            code: 'netbank' as const,
            label: 'NetBank',
            mode: 'shared' as const,
            shared: {
                status: 'ready',
                display_reference: '•••• 0019 · VCA 91500',
                managed_by: 'platform configuration',
            },
            dedicated: {
                configured: false,
                display_reference: null,
                status: 'not_configured',
                verification_status: 'not_configured',
                verified_at: null,
                last_synced_at: null,
                can_activate: false,
                can_rotate_token: false,
                ownership_verification_required: false,
            },
        },
        {
            code: 'paynamics_constellation' as const,
            label: 'Paynamics',
            mode: 'shared' as const,
            shared: {
                status: 'ready',
                display_reference: '•••• LLET01',
                managed_by: 'platform configuration',
            },
            dedicated: {
                configured: false,
                display_reference: null,
                status: 'not_configured',
                verification_status: 'not_configured',
                verified_at: null,
                last_synced_at: null,
                can_activate: false,
                can_rotate_token: false,
                ownership_verification_required: true,
            },
        },
    ],
    connection_history: [],
    controls: {
        shared_is_default: true,
        dedicated_fallback_enabled: false,
        pin_confirmation_required: true,
        manual_balance_adjustment_enabled: false,
        provider_webhook_settlement_required: true,
    },
    redactions: {
        account_numbers: 'masked',
        wallet_ids: 'masked',
        routing_tokens: 'write-only',
        credentials_exposed: false,
    },
};

const scenarioResult = {
    schema: 'x-change.lifecycle.account-management-scenario.v1',
    scenario: 'account_management_funding_destinations_demo',
    label: 'Account Management Funding Destinations',
    mode: 'account_management',
    success: true,
    message: 'Rollback-only account-management lifecycle completed.',
    rollback_completed: true,
    simulation: {
        rollback_only: true,
        provider_calls: 0,
        balance_changed: false,
        persisted: false,
        funding_instructions_issued: false,
        webhooks_received: false,
    },
    steps: [
        {
            key: 'shared_defaults',
            label: 'Shared destinations are the safe default',
            outcome: 'ready',
            summary: 'Shared treasury routing is selected.',
            providers: [
                {
                    code: 'netbank',
                    label: 'NetBank',
                    mode: 'shared',
                    shared: {
                        status: 'ready',
                        display_reference: '•••• 1111 · VCA 91001',
                    },
                    dedicated: {
                        configured: false,
                        display_reference: null,
                        status: 'not_configured',
                        verification_status: 'not_configured',
                        can_activate: false,
                        can_rotate_token: false,
                        ownership_verification_required: false,
                    },
                },
            ],
            facts: [{ label: 'Balance impact', value: 'None' }],
        },
        {
            key: 'netbank_dedicated_ready',
            label: 'NetBank dedicated routing becomes eligible',
            outcome: 'ready',
            summary: 'Account routing is configured without a stored token.',
            providers: [
                {
                    code: 'netbank',
                    label: 'NetBank',
                    mode: 'dedicated',
                    shared: {
                        status: 'ready',
                        display_reference: '•••• 1111 · VCA 91001',
                    },
                    dedicated: {
                        configured: true,
                        display_reference: '•••• 4242 · VCA 54321',
                        status: 'ready',
                        verification_status: 'routing_configured',
                        can_activate: true,
                        can_rotate_token: false,
                        ownership_verification_required: false,
                    },
                },
            ],
            facts: [{ label: 'Routing', value: 'Configured' }],
        },
    ],
};

const fundingQrMerchantProfile = {
    name: 'Treasury Operator',
    city: 'Manila',
    merchant_category_code: '0000',
    merchant_name_template: '{name}',
    category_options: [{ code: '0000', label: 'General/Personal' }],
    presentation_only: true as const,
    controls_routing: false as const,
    controls_settlement: false as const,
};

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Cockpit Accounts foundation', () => {
    it('renders masked provider destinations and the secure credit boundary', () => {
        const wrapper = mount(Accounts, {
            props: {
                account_read_model: accountReadModel,
                funding_qr_merchant_profile: fundingQrMerchantProfile,
            },
        });

        expect(wrapper.text()).toContain('Accounts');
        expect(wrapper.text()).toContain('Verified provider settlement only');
        expect(wrapper.text()).toContain('•••• 0019 · VCA 91500');
        expect(wrapper.text()).toContain('•••• LLET01');
        expect(wrapper.text()).toContain('Funding QR merchant profile');
        expect(wrapper.text()).toContain('Presentation only');
        expect(
            (
                wrapper.get('[data-testid="funding-qr-merchant-name"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('Treasury Operator');
        expect(wrapper.text()).not.toContain('test-vca-alias-token');
        expect(
            wrapper.find('[data-testid="netbank-token-field"]').exists(),
        ).toBe(false);
    });

    it('shows token-free dedicated fields without weakening ownership policy', async () => {
        const wrapper = mount(Accounts, {
            props: {
                account_read_model: {
                    ...accountReadModel,
                    providers: accountReadModel.providers.map((provider) => ({
                        ...provider,
                        mode: 'dedicated' as const,
                    })),
                },
                funding_qr_merchant_profile: fundingQrMerchantProfile,
            },
        });

        expect(
            wrapper.find('[data-testid="netbank-dedicated-fields"]').exists(),
        ).toBe(true);

        expect(
            wrapper.find('[data-testid="netbank-token-field"]').exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain(
            'registration tokens are generated automatically',
        );
        expect(wrapper.text()).toContain(
            'generating one does not revoke an earlier token',
        );

        expect(
            wrapper.find('[data-testid="paynamics-ownership-warning"]').text(),
        ).toContain('reachable provider account is not proof of ownership');
    });

    it('keeps the lifecycle walkthrough disabled when the environment gate is off', () => {
        const wrapper = mount(Accounts, {
            props: {
                account_read_model: accountReadModel,
                funding_qr_merchant_profile: fundingQrMerchantProfile,
                account_scenario: {
                    enabled: false,
                    mode: 'rollback-only',
                    provider_calls: false,
                    balance_changes: false,
                },
            },
        });

        const button = wrapper.get('[data-testid="run-account-scenario"]');

        expect(button.attributes('disabled')).toBeDefined();
        expect(button.text()).toContain('Unavailable in this environment');
    });

    it('guides operators through returned rollback snapshots', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => scenarioResult,
            }),
        );
        const wrapper = mount(Accounts, {
            props: {
                account_read_model: accountReadModel,
                funding_qr_merchant_profile: fundingQrMerchantProfile,
                account_scenario: {
                    enabled: true,
                    mode: 'rollback-only',
                    provider_calls: false,
                    balance_changes: false,
                },
            },
        });

        await wrapper
            .get('[data-testid="run-account-scenario"]')
            .trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(
            wrapper.get('[data-testid="account-scenario-stepper"]').text(),
        ).toContain('Shared destinations are the safe default');
        expect(wrapper.text()).toContain(
            'Rollback confirmed · funding position unchanged · nothing persisted',
        );

        await wrapper
            .get('[data-testid="next-scenario-step"]')
            .trigger('click');

        expect(
            wrapper.get('[data-testid="account-scenario-stepper"]').text(),
        ).toContain('NetBank dedicated routing becomes eligible');
        expect(wrapper.text()).not.toContain(
            'scenario-write-only-netbank-token',
        );

        await wrapper
            .get(
                '[aria-label="Open step 1: Shared destinations are the safe default"]',
            )
            .trigger('click');

        expect(
            wrapper.get('[data-testid="account-scenario-stepper"]').text(),
        ).toContain('Shared destinations are the safe default');
    });
});
