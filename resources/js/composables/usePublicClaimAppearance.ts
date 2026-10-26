import { router } from '@inertiajs/vue3';
import '../experience/themes.css';
import '../experience/claim-midnight.css';

type ClaimAppearance = {
    mode?: 'light' | 'dark' | 'system';
    light?: Record<string, string>;
    dark?: Record<string, string>;
};

const claimTokens = ['canvas', 'surface', 'foreground', 'muted', 'border', 'accent', 'accent_foreground'] as const;
const midnightAppearance: ClaimAppearance = {
    mode: 'dark',
    dark: {
        canvas: '#0c1020', surface: '#191f32', foreground: '#f5f6fc',
        muted: '#a9b1c8', border: '#46516c', accent: '#b8c6ff', accent_foreground: '#0c1020',
    },
};

type ClaimPage = {
    component: string;
    props: Record<string, unknown>;
};

function hasClaimContext(props: Record<string, unknown>): boolean {
    const presentation = props.presentation as { profile?: string } | undefined;
    const claimPresentation = props.claim_presentation as { schema?: string } | undefined;

    return presentation?.profile === 'guided-claim-v1'
        || claimPresentation?.schema === 'x-change.claim-presentation.v1'
        || props.claim_experience != null;
}

function appearanceForPage(page: ClaimPage): ClaimAppearance {
    const props = page.props as {
        step?: { screen?: { props?: { presentation?: { appearance?: ClaimAppearance } } } };
        presentation?: { appearance?: ClaimAppearance };
        claim_ui?: { appearance?: ClaimAppearance };
    };

    return props.step?.screen?.props?.presentation?.appearance
        ?? props.presentation?.appearance
        ?? props.claim_ui?.appearance
        ?? midnightAppearance;
}

export function isPublicClaimPage(page: ClaimPage): boolean {
    return page.component.startsWith('x-change/claim/')
        || (page.component.startsWith('form-flow/') && hasClaimContext(page.props));
}

export function initializePublicClaimAppearance(initialPage: ClaimPage): () => void {
    const root = document.documentElement;
    const systemAppearance = window.matchMedia('(prefers-color-scheme: dark)');
    let previousTheme: string | undefined;
    let previousColorScheme = '';
    let active = false;
    let appearance: ClaimAppearance = midnightAppearance;
    const previousVariables = new Map<string, string>();

    const apply = () => {
        if (!active) return;
        const scheme = appearance.mode === 'dark'
            || (appearance.mode === 'system' && systemAppearance.matches) ? 'dark' : 'light';
        if (root.classList.contains('dark') !== (scheme === 'dark')) root.classList.toggle('dark', scheme === 'dark');
        if (root.dataset.xChangeTheme !== 'default') root.dataset.xChangeTheme = 'default';
        if (root.dataset.xChangeClaimAppearance !== scheme) root.dataset.xChangeClaimAppearance = scheme;
        if (root.style.colorScheme !== scheme) root.style.colorScheme = scheme;
        for (const token of claimTokens) {
            const key = `--x-claim-${token.replaceAll('_', '-')}`;
            const value = appearance[scheme]?.[token];
            if (value && root.style.getPropertyValue(key) !== value) root.style.setProperty(key, value);
            else if (!value && root.style.getPropertyValue(key)) root.style.removeProperty(key);
        }
    };

    const restore = () => {
        active = false;
        delete root.dataset.xChangeClaimAppearance;
        root.style.colorScheme = previousColorScheme;
        if (previousTheme === undefined) delete root.dataset.xChangeTheme;
        else root.dataset.xChangeTheme = previousTheme;
        for (const [key, value] of previousVariables) {
            if (value) root.style.setProperty(key, value);
            else root.style.removeProperty(key);
        }

        let storedAppearance: string | null = null;
        try { storedAppearance = localStorage.getItem('appearance'); } catch { /* Guest storage may be disabled. */ }
        root.classList.toggle('dark', storedAppearance === 'dark'
            || ((!storedAppearance || storedAppearance === 'system') && systemAppearance.matches));
    };

    const usePage = (page: ClaimPage) => {
        const next = isPublicClaimPage(page);
        if (next) {
            if (!active) {
                previousTheme = root.dataset.xChangeTheme;
                previousColorScheme = root.style.colorScheme;
                for (const token of claimTokens) {
                    const key = `--x-claim-${token.replaceAll('_', '-')}`;
                    previousVariables.set(key, root.style.getPropertyValue(key));
                }
            }
            active = true;
            appearance = appearanceForPage(page);
            apply();
        } else if (active) {
            restore();
        }
    };

    const observer = new MutationObserver(apply);
    observer.observe(root, { attributes: true, attributeFilter: ['class', 'style', 'data-x-change-theme'] });
    const removeNavigationListener = router.on('navigate', (event) => usePage(event.detail.page));
    const onSystemChange = () => apply();
    systemAppearance.addEventListener('change', onSystemChange);
    usePage(initialPage);

    return () => {
        removeNavigationListener();
        systemAppearance.removeEventListener('change', onSystemChange);
        observer.disconnect();
        if (active) restore();
    };
}
