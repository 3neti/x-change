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

    it('applies midnight across claim screens and restores the host after leaving', async () => {
        const stop = initializePublicClaimAppearance({ component: 'x-change/claim/Entry', props: {} });

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.dataset.xChangeTheme).toBe('default');
        expect(document.documentElement.style.colorScheme).toBe('dark');
        expect(document.documentElement.style.getPropertyValue('--x-claim-canvas')).toBe('#0c1020');

        document.documentElement.classList.remove('dark');
        await Promise.resolve();
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        navigate?.({ detail: { page: { component: 'form-flow/core/GenericForm', props: { claim_experience: {} } } } });
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        navigate?.({ detail: { page: { component: 'settings/Appearance', props: {} } } });
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.dataset.xChangeTheme).toBe('amber');
        stop();
    });

    it('honors an explicit light appearance on a guided claim screen', () => {
        const stop = initializePublicClaimAppearance({ component: 'form-flow/core/GenericForm', props: {
            presentation: { profile: 'guided-claim-v1', appearance: {
                mode: 'light', light: { canvas: '#fffef7', foreground: '#172033' },
            } },
        } });

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.style.colorScheme).toBe('light');
        expect(document.documentElement.style.getPropertyValue('--x-claim-canvas')).toBe('#fffef7');
        stop();
    });
});
