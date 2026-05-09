import { ref, onMounted } from 'vue';

export type ThemeId = 'default' | 'steampunk' | 'amber';

export interface ThemeOption {
    id: ThemeId;
    name: string;
    description: string;
    preview: { bg: string; accent: string; text: string };
}

const STORAGE_KEY = 'pwa-theme';

const availableThemes: ThemeOption[] = [
    {
        id: 'default',
        name: 'Default',
        description: 'Clean, neutral interface',
        preview: { bg: 'bg-background', accent: 'bg-primary', text: 'text-foreground' },
    },
    {
        id: 'steampunk',
        name: 'Steampunk',
        description: 'Warm brass & aged parchment',
        preview: { bg: 'bg-amber-50 dark:bg-amber-950', accent: 'bg-amber-700', text: 'text-amber-900 dark:text-amber-100' },
    },
    {
        id: 'amber',
        name: 'Amber',
        description: 'Sunlit gold, quiet warmth',
        preview: { bg: 'bg-orange-50 dark:bg-orange-950', accent: 'bg-orange-500', text: 'text-orange-900 dark:text-orange-100' },
    },
];

const currentTheme = ref<ThemeId>('default');

function applyTheme(id: ThemeId) {
    const html = document.documentElement;

    // Remove all theme classes
    html.classList.forEach((cls) => {
        if (cls.startsWith('theme-')) html.classList.remove(cls);
    });

    // Apply new theme (skip for default — it uses base variables)
    if (id !== 'default') {
        html.classList.add(`theme-${id}`);
    }
}

export function initializeTheme() {
    if (typeof window === 'undefined') return;

    const saved = localStorage.getItem(STORAGE_KEY) as ThemeId | null;
    const id = saved && availableThemes.some((t) => t.id === saved) ? saved : 'default';

    currentTheme.value = id;
    applyTheme(id);
}

export function useTheme() {
    onMounted(() => {
        initializeTheme();
    });

    function setTheme(id: ThemeId) {
        currentTheme.value = id;
        localStorage.setItem(STORAGE_KEY, id);
        applyTheme(id);
    }

    return { currentTheme, setTheme, availableThemes };
}
