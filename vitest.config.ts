import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@/components/AppLogoIcon.vue': path.resolve(
                __dirname,
                'tests/frontend/stubs/AppLogoIcon.ts',
            ),

            '@/components/ui/card': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-card.ts',
            ),
            '@/components/ui/button': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-button.ts',
            ),
            '@/components/ui/badge': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-badge.ts',
            ),
            '@/components/ui/checkbox': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-checkbox.ts',
            ),
            '@/components/ui/input': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-input.ts',
            ),
            '@/components/ui/label': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-label.ts',
            ),
            '@/components/ui/textarea': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-textarea.ts',
            ),
            '@/components/ui/alert': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-alert.ts',
            ),
            '@/components/ui/separator': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-separator.ts',
            ),
            '@/components/ui/tabs': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-tabs.ts',
            ),
            '@/components/ui/spinner': path.resolve(
                __dirname,
                'tests/frontend/stubs/ui-spinner.ts',
            ),

            '@/components/x-rider/RiderCountdown.vue': path.resolve(
                __dirname,
                'tests/frontend/stubs/RiderCountdown.ts',
            ),
            '@/components/x-rider/RiderStagePresenter.vue': path.resolve(
                __dirname,
                'tests/frontend/stubs/RiderStagePresenter.ts',
            ),
            '@/components/x-rider/RiderRuntimeSequencer.vue': path.resolve(
                __dirname,
                'tests/frontend/stubs/RiderRuntimeSequencer.ts',
            ),
            '@/components/x-rider/useRiderStagePhase': path.resolve(
                __dirname,
                'tests/frontend/stubs/useRiderStagePhase.ts',
            ),
            '@/routes/x-change/cockpit/funding/intents/instructions':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/funding-instruction-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/intents/verification-checks':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/funding-verification-check-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/intents': path.resolve(
                __dirname,
                'tests/frontend/stubs/funding-intent-route.ts',
            ),
            '@/routes/x-change/cockpit/funding/standing-addresses/netbank/history-checks':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/netbank-standing-funding-history-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/standing-addresses/netbank/receipts':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/netbank-standing-funding-receipt-routes.ts',
                ),
            '@/routes/x-change/cockpit/funding/standing-addresses/netbank':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/netbank-standing-funding-address-route.ts',
                ),
            '@/routes/x-change/cockpit/funding/scenarios/qrph': path.resolve(
                __dirname,
                'tests/frontend/stubs/qrph-funding-simulation-route.ts',
            ),
            '@/routes/x-change/cockpit/funding/reconciliations': path.resolve(
                __dirname,
                'tests/frontend/stubs/funding-reconciliation-routes.ts',
            ),
            '@/routes/x-change/cockpit/funding/suspense/reconciliation-requests':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/funding-reconciliation-request-route.ts',
                ),
            '@/routes/x-change/cockpit/accounts/providers/funding-destination':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/funding-destination-route.ts',
                ),
            '@/routes/x-change/cockpit/accounts/funding-qr-merchant-profile':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/funding-qr-merchant-profile-route.ts',
                ),
            '@/routes/x-change/cockpit/accounts/scenarios/funding-destinations':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/account-scenario-route.ts',
                ),
            '@/routes/x-change/cockpit/accounts': path.resolve(
                __dirname,
                'tests/frontend/stubs/accounts-route.ts',
            ),
            '@/routes/x-change/onboarding/mobile-verification': path.resolve(
                __dirname,
                'tests/frontend/stubs/mobile-verification-routes.ts',
            ),

            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/frontend/**/*.test.ts'],
        exclude: ['vendor/**', 'node_modules/**'],
    },
});
