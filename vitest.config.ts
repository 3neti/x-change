import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@/components/AppLogoIcon.vue': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/AppLogoIcon.ts',
            ),

            '@/components/ui/card': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-card.ts',
            ),
            '@/components/ui/button': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-button.ts',
            ),
            '@/components/ui/badge': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-badge.ts',
            ),
            '@/components/ui/checkbox': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-checkbox.ts',
            ),
            '@/components/ui/input': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-input.ts',
            ),
            '@/components/ui/label': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-label.ts',
            ),
            '@/components/ui/textarea': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-textarea.ts',
            ),
            '@/components/ui/alert': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-alert.ts',
            ),
            '@/components/ui/separator': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-separator.ts',
            ),
            '@/components/ui/tabs': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-tabs.ts',
            ),
            '@/components/ui/spinner': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-spinner.ts',
            ),
            '@/components/ui/select': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/ui-select.ts',
            ),
            '@/components/financial/BankEMISelect.vue': path.resolve(
                import.meta.dirname,
                'resources/js/components/x-change-shared-financial/BankEMISelect.vue',
            ),

            '@/components/x-rider/RiderCountdown.vue': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/RiderCountdown.ts',
            ),
            '@/components/x-rider/RiderStagePresenter.vue': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/RiderStagePresenter.ts',
            ),
            '@/components/x-rider/RiderRuntimeSequencer.vue': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/RiderRuntimeSequencer.ts',
            ),
            '@/components/x-rider/useRiderStagePhase': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/useRiderStagePhase.ts',
            ),
            '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodeExplorerPageController':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/cockpit-pay-code-explorer-route.ts',
                ),
            '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodePayoutCorrectionController':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/pay-code-payout-correction-route.ts',
                ),
            '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodeTemplateStoreController':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/pay-code-template-store-route.ts',
                ),
            '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodeTemplateUpdateController':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/pay-code-template-update-route.ts',
                ),
            '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitRiderArtworkPreviewController':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/rider-artwork-preview-route.ts',
                ),
            '@/actions/LBHurtado/XChange/Http/Controllers/PayCode/EstimatePayCodeController':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/estimate-pay-code-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/intents/instructions':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/funding-instruction-route.ts',
                ),
            '@/routes/x-change/cockpit/commercial/offerings/approvals':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/commercial-offering-approval-route.ts',
                ),
            '@/routes/x-change/cockpit/commercial/offerings': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/commercial-offering-route.ts',
            ),
            '@/routes/x-change/cockpit/funding/intents/verification-checks':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/funding-verification-check-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/intents': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/funding-intent-route.ts',
            ),
            '@/routes/x-change/cockpit/funding/liquidity-refreshes':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/funding-liquidity-refresh-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/requests/approvals':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/funding-request-approval-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/requests/reviews': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/funding-request-review-route.ts',
            ),
            '@/routes/x-change/cockpit/funding/requests/pay-code-claims':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/reviewed-funding-pay-code-claim-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/requests/transfer-checks':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/funding-transfer-check-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/requests': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/funding-request-route.ts',
            ),
            '@/routes/x-change/cockpit/funding/pay-code-inspections':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/pay-code-funding-inspection-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/pay-code-claims': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/pay-code-funding-claim-route.ts',
            ),
            '@/routes/x-change/cockpit/funding/standing-addresses/netbank/history-checks':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/netbank-standing-funding-history-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/standing-addresses/netbank/receipts':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/netbank-standing-funding-receipt-routes.ts',
                ),
            '@/routes/x-change/cockpit/funding/standing-addresses/netbank':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/netbank-standing-funding-address-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/scenarios/qrph': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/qrph-funding-simulation-route.ts',
            ),
            '@/routes/x-change/cockpit/funding/reconciliations': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/funding-reconciliation-routes.ts',
            ),
            '@/routes/x-change/cockpit/funding/suspense/reconciliation-requests':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/funding-reconciliation-request-route.ts',
                ),
            '@/routes/x-change/cockpit/funding': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/funding-index-route.ts',
            ),
            '@/routes/x-change/cockpit/campaigns/intakes': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/campaign-intake-route.ts',
            ),
            '@/routes/x-change/cockpit/campaigns/authorizations': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/campaign-authorization-route.ts',
            ),
            '@/routes/x-change/cockpit/campaigns/voucher-blueprint':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/campaign-voucher-blueprint-route.ts',
                ),
            '@/routes/x-change/cockpit/campaigns': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/campaign-worksheet-route.ts',
            ),
            '@/routes/x-change/claim/flows': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/claim-flow-route.ts',
            ),
            '@/routes/x-change/pay/attempts/checks': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/payment-verification-check-route.ts',
            ),
            '@/routes/x-change/pay/attempts': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/payment-attempt-route.ts',
            ),
            '@/routes/x-change/cockpit/accounts/providers/funding-destination':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/funding-destination-route.ts',
                ),
            '@/routes/x-change/cockpit/accounts/funding-qr-merchant-profile':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/funding-qr-merchant-profile-route.ts',
                ),
            '@/routes/x-change/cockpit/accounts/scenarios/funding-destinations':
                path.resolve(
                    import.meta.dirname,
                    'tests/frontend/stubs/account-scenario-route.ts',
                ),
            '@/routes/x-change/cockpit/accounts': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/accounts-route.ts',
            ),
            '@/routes/x-change/cockpit': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/cockpit-route.ts',
            ),
            '@/routes/x-change/onboarding/mobile-verification': path.resolve(
                import.meta.dirname,
                'tests/frontend/stubs/mobile-verification-routes.ts',
            ),

            '@': path.resolve(import.meta.dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/frontend/**/*.test.ts'],
        exclude: ['vendor/**', 'node_modules/**'],
    },
});
