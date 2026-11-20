import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { ModuleKind, transpileModule } from 'typescript';
import { describe, expect, it } from 'vitest';

function resolveLayout(pageName: string): unknown {
    let options: { layout: (name: string) => unknown } | undefined;
    const source = readFileSync(
        'stubs/resources/js/app.ts.stub',
        'utf8',
    ).replace('import.meta.env.VITE_APP_NAME', "'Test'");
    const compiled = transpileModule(source, {
        compilerOptions: { module: ModuleKind.CommonJS },
    });

    runInNewContext(compiled.outputText, {
        exports: {},
        require: (name: string) => {
            if (name === '@inertiajs/vue3') {
                return {
                    createInertiaApp: (value: typeof options) => {
                        options = value;
                    },
                };
            }
            if (name.endsWith('.vue')) {
                return { default: name };
            }
            if (name === '@/composables/useAppearance') {
                return { initializeTheme: () => {} };
            }
            if (name === '@/composables/usePublicClaimAppearance') {
                return { initializePublicClaimAppearance: () => {} };
            }
            throw new Error(`Unexpected bootstrap dependency: ${name}`);
        },
    });

    if (!options) {
        throw new Error('The host bootstrap did not configure Inertia.');
    }

    return options.layout(pageName);
}

describe('host authentication page layouts', () => {
    it.each([
        'auth/Login',
        'auth/Register',
        'x-change/auth/ForgotPin',
        'x-change/auth/ResetPin',
    ])('renders %s with the guest authentication shell', (pageName) => {
        expect(resolveLayout(pageName)).toBe('@/layouts/AuthLayout.vue');
    });

    it('preserves the authenticated cockpit shell', () => {
        expect(resolveLayout('x-change/cockpit/QuickGenerate')).toBe(
            '@/layouts/app/AppSidebarLayoutCockpit.vue',
        );
    });

    it('preserves the public claim experience', () => {
        expect(resolveLayout('x-change/claim/Entry')).toBeNull();
    });
});
