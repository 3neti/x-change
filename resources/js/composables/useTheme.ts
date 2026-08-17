import { onMounted, readonly, ref } from 'vue';
import {
    experienceProfile,
    experienceThemeRegistry,
    findExperienceTheme,
    type ExperienceThemeId,
} from '../experience/themes';
import '../experience/themes.css';

export type ThemeId = ExperienceThemeId;
export type ThemeOption = (typeof experienceThemeRegistry)[number];

export const EXPERIENCE_THEME_STORAGE_KEY = 'x-change:experience-theme:v1';
const LEGACY_STORAGE_KEY = 'pwa-theme';

const currentTheme = ref<ThemeId>(experienceProfile.defaultTheme);

export function resolveExperienceTheme(
    value: string | null | undefined,
): ThemeId {
    return findExperienceTheme(value)?.id ?? experienceProfile.defaultTheme;
}

export function applyExperienceTheme(value: string | null | undefined): void {
    if (typeof document === 'undefined') {
        return;
    }

    const id = resolveExperienceTheme(value);
    const definition = findExperienceTheme(id);
    const root = document.documentElement;
    root.dataset.xChangeTheme = id;
    currentTheme.value = id;

    if (definition === null) {
        return;
    }

    let themeColor = document.head.querySelector<HTMLMetaElement>(
        'meta[name="theme-color"]',
    );

    if (themeColor === null) {
        themeColor = document.createElement('meta');
        themeColor.name = 'theme-color';
        document.head.append(themeColor);
    }

    themeColor.content = definition.browserColor;
}

function readStoredTheme(): ThemeId {
    try {
        const saved = localStorage.getItem(EXPERIENCE_THEME_STORAGE_KEY);

        if (saved !== null) {
            return resolveExperienceTheme(saved);
        }

        const legacy = localStorage.getItem(LEGACY_STORAGE_KEY);

        if (legacy !== null && findExperienceTheme(legacy) !== null) {
            const migrated = resolveExperienceTheme(legacy);
            localStorage.setItem(EXPERIENCE_THEME_STORAGE_KEY, migrated);
            localStorage.removeItem(LEGACY_STORAGE_KEY);

            return migrated;
        }
    } catch {
        return experienceProfile.defaultTheme;
    }

    return experienceProfile.defaultTheme;
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    applyExperienceTheme(readStoredTheme());
}

export function setTheme(value: string): void {
    const id = resolveExperienceTheme(value);

    try {
        localStorage.setItem(EXPERIENCE_THEME_STORAGE_KEY, id);
    } catch {
        // A blocked storage API must not prevent cosmetic theme selection.
    }

    applyExperienceTheme(id);
}

export function useTheme() {
    onMounted(initializeTheme);

    return {
        currentTheme: readonly(currentTheme),
        setTheme,
        availableThemes: experienceThemeRegistry,
    };
}

if (typeof window !== 'undefined') {
    initializeTheme();
}
