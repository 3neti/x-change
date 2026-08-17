import { beforeEach, describe, expect, it } from 'vitest';
import {
    EXPERIENCE_THEME_STORAGE_KEY,
    applyExperienceTheme,
    initializeTheme,
    resolveExperienceTheme,
    setTheme,
} from '../../resources/js/composables/useTheme';
import {
    experienceProfile,
    experienceThemeRegistry,
} from '../../resources/js/experience/themes';

describe('x-change experience themes', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.removeAttribute('data-x-change-theme');
        document.head
            .querySelectorAll('meta[name="theme-color"]')
            .forEach((element) => element.remove());
    });

    it('ships a versioned profile with Default, Amber, and Steampunk', () => {
        expect(experienceProfile).toMatchObject({
            id: 'x-change-core',
            version: 1,
            defaultTheme: 'default',
            branding: { id: 'x-change', version: 1 },
            dictionary: { id: 'x-change-core', version: 1 },
            copy: { id: 'x-change-core', version: 1 },
        });
        expect(experienceThemeRegistry.map((theme) => theme.id)).toEqual([
            'default',
            'amber',
            'steampunk',
        ]);
        expect(
            experienceThemeRegistry.map((theme) => theme.stampDesign.id),
        ).toEqual(['x-change-default', 'x-change-amber', 'x-change-steampunk']);
    });

    it('treats local storage as untrusted and falls back safely', () => {
        expect(resolveExperienceTheme('steampunk')).toBe('steampunk');
        expect(resolveExperienceTheme('javascript:alert(1)')).toBe('default');
        expect(resolveExperienceTheme(null)).toBe('default');
    });

    it('persists a guest choice and synchronizes the browser color', () => {
        setTheme('amber');

        expect(localStorage.getItem(EXPERIENCE_THEME_STORAGE_KEY)).toBe(
            'amber',
        );
        expect(document.documentElement.dataset.xChangeTheme).toBe('amber');
        expect(
            document
                .querySelector('meta[name="theme-color"]')
                ?.getAttribute('content'),
        ).toBe('#9a3412');
    });

    it('migrates the guest preference from the redeem-x storage key', () => {
        localStorage.setItem('pwa-theme', 'steampunk');

        initializeTheme();

        expect(document.documentElement.dataset.xChangeTheme).toBe('steampunk');
        expect(localStorage.getItem(EXPERIENCE_THEME_STORAGE_KEY)).toBe(
            'steampunk',
        );
        expect(localStorage.getItem('pwa-theme')).toBeNull();
    });

    it('never applies an arbitrary theme identifier to the document', () => {
        applyExperienceTheme('not-registered');

        expect(document.documentElement.dataset.xChangeTheme).toBe('default');
        expect(document.documentElement.className).not.toContain(
            'not-registered',
        );
    });
});
