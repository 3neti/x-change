import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@/components/ui/card': path.resolve(__dirname, 'tests/frontend/stubs/ui-card.ts'),
            '@/components/ui/button': path.resolve(__dirname, 'tests/frontend/stubs/ui-button.ts'),

            '@/components/x-rider/RiderCountdown.vue': path.resolve(__dirname, 'tests/frontend/stubs/RiderCountdown.ts'),
            '@/components/x-rider/RiderStagePresenter.vue': path.resolve(__dirname, 'tests/frontend/stubs/RiderStagePresenter.ts'),
            '@/components/x-rider/RiderRuntimeSequencer.vue': path.resolve(__dirname, 'tests/frontend/stubs/RiderRuntimeSequencer.ts'),
            '@/components/x-rider/useRiderStagePhase': path.resolve(__dirname, 'tests/frontend/stubs/useRiderStagePhase.ts'),

            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/frontend/**/*.test.ts'],
        exclude: ['vendor/**', 'node_modules/**'],
    },
})
