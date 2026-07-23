import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Accounts from '../../../resources/js/pages/x-change/cockpit/Accounts.vue';
import Dashboard from '../../../resources/js/pages/x-change/cockpit/Dashboard.vue';
import DistributionWorkspace from '../../../resources/js/pages/x-change/cockpit/DistributionWorkspace.vue';
import PayCodeExplorer from '../../../resources/js/pages/x-change/cockpit/PayCodeExplorer.vue';
import QuickGenerate from '../../../resources/js/pages/x-change/cockpit/QuickGenerate.vue';
import VoucherDetail from '../../../resources/js/pages/x-change/cockpit/VoucherDetail.vue';

describe('Cockpit route page adapters', () => {
    it('mounts the Accounts route adapter', () => {
        const wrapper = mount(Accounts, {
            props: {
                account_read_model: {
                    schema: 'x-change.cockpit.account-management.v1',
                    status: 'available',
                    account: {
                        reference: 'Account •••• 12345678',
                        currency: 'PHP',
                        ledger_authority: 'internal-account-ledger',
                        funding_credit_policy:
                            'verified-provider-settlement-only',
                    },
                    providers: [],
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
                },
            },
        });

        expect(
            wrapper.find('[data-testid="cockpit-accounts-page"]').exists(),
        ).toBe(true);
    });

    it('mounts the dashboard route adapter', () => {
        const wrapper = mount(Dashboard);

        expect(
            wrapper.find('[data-testid="cockpit-dashboard-shell"]').exists(),
        ).toBe(true);
    });

    it('mounts the quick generate route adapter', () => {
        const wrapper = mount(QuickGenerate);

        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-shell"]')
                .exists(),
        ).toBe(true);
    });

    it('mounts the pay code explorer route adapter', () => {
        const wrapper = mount(PayCodeExplorer);

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-explorer-shell"]')
                .exists(),
        ).toBe(true);
    });

    it('mounts the voucher detail route adapter', () => {
        const wrapper = mount(VoucherDetail);

        expect(
            wrapper
                .find('[data-testid="cockpit-voucher-detail-shell"]')
                .exists(),
        ).toBe(true);
    });

    it('mounts the distribution workspace route adapter', () => {
        const wrapper = mount(DistributionWorkspace);

        expect(
            wrapper
                .find('[data-testid="cockpit-distribution-workspace-shell"]')
                .exists(),
        ).toBe(true);
    });
});
