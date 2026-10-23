import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, 'stored-value-collection-harness');

export default defineConfig({
    root,
    plugins: [vue()],
    resolve: {
        alias: {
            '@inertiajs/vue3': path.join(root, 'inertia-shim.ts'),
        },
    },
    server: {
        fs: { allow: [path.resolve(import.meta.dirname, '../..')] },
    },
});
