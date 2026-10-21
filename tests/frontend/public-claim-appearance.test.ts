import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initializePublicClaimAppearance, isPublicClaimPage } from '../../resources/js/composables/usePublicClaimAppearance';

let navigate: ((event: { detail: { page: { component: string; props: Record<string, unknown> } } }) => void) | undefined;

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: vi.fn((_event, callback) => {
            navigate = callback;
            return vi.fn();
        }),
    },
}));

beforeEach(() => {
    vi.stubGlobal('matchMedia', () => ({
        matches: true,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    }));
    document.documentElement.className = 'dark';
    document.documentElement.dataset.xChangeTheme = 'amber';
    document.documentElement.style.colorScheme = 'dark';
    localStorage.setItem('appearance', 'system');
});

afterEach(() => {
    document.documentElement.className = '';
    delete document.documentElement.dataset.xChangeTheme;
    delete document.documentElement.dataset.xChangeClaimAppearance;
    document.documentElement.style.colorScheme = '';
    localStorage.clear();
    vi.unstubAllGlobals();
});

describe('public Pay Code claim appearance', () => {
    it('recognizes guided Form Flow and excludes unrelated flows', () => {
        expect(isPublicClaimPage({ component: 'form-flow/core/GenericForm', props: { claim_experience: {} } })).toBe(true);
        expect(isPublicClaimPage({ component: 'form-flow/core/GenericForm', props: {} })).toBe(false);
    });

    it('keeps the claim light during system theme changes and restores the host after leaving', async () => {
        const stop = initializePublicClaimAppearance({ component: 'x-change/claim/Entry', props: {} });

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.dataset.xChangeTheme).toBe('default');
        expect(document.documentElement.style.colorScheme).toBe('light');

        document.documentElement.classList.add('dark');
        await Promise.resolve();
        expect(document.documentElement.classList.contains('dark')).toBe(false);

        navigate?.({ detail: { page: { component: 'form-flow/core/GenericForm', props: { claim_experience: {} } } } });
        expect(document.documentElement.classList.contains('dark')).toBe(false);

        navigate?.({ detail: { page: { component: 'settings/Appearance', props: {} } } });
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.dataset.xChangeTheme).toBe('amber');
        stop();
    });
});
