import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
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

describe('Cockpit Accounts foundation', () => {
    it('renders masked provider destinations and the secure credit boundary', () => {
        const wrapper = mount(Accounts, {
            props: {
                account_read_model: accountReadModel,
            },
        });

        expect(wrapper.text()).toContain('Accounts');
        expect(wrapper.text()).toContain('Verified provider settlement only');
        expect(wrapper.text()).toContain('•••• 0019 · VCA 91500');
        expect(wrapper.text()).toContain('•••• LLET01');
        expect(wrapper.text()).not.toContain('test-vca-alias-token');
        expect(
            wrapper.find('[data-testid="netbank-token-field"]').exists(),
        ).toBe(false);
    });

    it('reveals write-only dedicated fields without weakening ownership policy', async () => {
        const wrapper = mount(Accounts, {
            props: {
                account_read_model: {
                    ...accountReadModel,
                    providers: accountReadModel.providers.map((provider) => ({
                        ...provider,
                        mode: 'dedicated' as const,
                    })),
                },
            },
        });

        expect(
            wrapper.find('[data-testid="netbank-dedicated-fields"]').exists(),
        ).toBe(true);

        await wrapper.find('#netbank-enrollment').setValue('import');
        await nextTick();

        expect(
            wrapper.find('[data-testid="netbank-token-field"]').exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain(
            'Write-only; it will not be shown again.',
        );

        expect(
            wrapper.find('[data-testid="paynamics-ownership-warning"]').text(),
        ).toContain('reachable wallet is not proof of ownership');
    });
});
