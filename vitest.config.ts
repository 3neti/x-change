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
            '@/routes/x-change/cockpit/funding/intents': path.resolve(
                __dirname,
                'tests/frontend/stubs/funding-intent-route.ts',
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
            '@/routes/x-change/cockpit/accounts/providers/netbank/token-rotation':
                path.resolve(
                    __dirname,
                    'tests/frontend/stubs/netbank-token-rotation-route.ts',
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
