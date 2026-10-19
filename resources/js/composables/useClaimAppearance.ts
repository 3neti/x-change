import { router } from '@inertiajs/vue3';
import '../experience/themes.css';

type ClaimAppearancePage = { component: string; props: Record<string, unknown> };

export function isPublicClaimPage(page: ClaimAppearancePage): boolean {
    if (page.component.startsWith('x-change/claim/')) return true;
    const presentation = page.props.presentation as { profile?: string } | null;
    return page.component.startsWith('form-flow/') && presentation?.profile === 'guided-claim-v1';
}

export function initializeClaimAppearance(initialPage: ClaimAppearancePage): () => void {
    const root = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    let active = false;
    let previousColorScheme = '';
    let previousTheme: string | undefined;

    let policy: { mode?: string; light?: Record<string, string>; dark?: Record<string, string> } = {};
    const previousVariables = new Map<string, string>();
    const applyPolicy = () => {
        const scheme = policy.mode === 'dark' || (policy.mode === 'system' && media.matches) ? 'dark' : 'light';
        root.classList.toggle('dark', scheme === 'dark');
        root.style.colorScheme = scheme;
        root.dataset.xChangeClaimAppearance = scheme;
        for (const token of ['canvas', 'surface', 'foreground', 'muted', 'border', 'accent', 'accent_foreground']) {
            const key = `--x-claim-${token.replaceAll('_', '-')}`;
            const value = policy[scheme]?.[token];
            if (value) root.style.setProperty(key, value);
            else root.style.removeProperty(key);
        }
        root.dataset.xChangeTheme = 'default';
    };
    const restore = () => {
        let saved: string | null = null;
        try { saved = localStorage.getItem('appearance'); } catch { /* Storage may be unavailable in a guest browser. */ }
        root.classList.toggle('dark', saved === 'dark' || ((!saved || saved === 'system') && media.matches));
        root.style.colorScheme = previousColorScheme;
        delete root.dataset.xChangeClaimAppearance;
        for (const [key, value] of previousVariables) {
            if (value) root.style.setProperty(key, value);
            else root.style.removeProperty(key);
        }
        if (previousTheme === undefined) delete root.dataset.xChangeTheme;
        else root.dataset.xChangeTheme = previousTheme;
    };
    const applyPage = (page: ClaimAppearancePage) => {
        const next = isPublicClaimPage(page);
        if (next && !active) {
            previousColorScheme = root.style.colorScheme;
            previousTheme = root.dataset.xChangeTheme;
            for (const token of ['canvas', 'surface', 'foreground', 'muted', 'border', 'accent', 'accent-foreground']) {
                const key = `--x-claim-${token}`;
                previousVariables.set(key, root.style.getPropertyValue(key));
            }
        }
        const props = page.props as {
            step?: { screen?: { props?: { presentation?: { appearance?: typeof policy } } } };
            presentation?: { appearance?: typeof policy };
            claim_ui?: { appearance?: typeof policy };
        };
        policy = props.step?.screen?.props?.presentation?.appearance
            ?? props.presentation?.appearance ?? props.claim_ui?.appearance ?? {};
        if (next) applyPolicy();
        else if (active) restore();
        active = next;
    };
    const onSystemChange = () => { if (active) applyPolicy(); };
    applyPage(initialPage);
    const removeNavigationListener = router.on('navigate', event => applyPage(event.detail.page));
    media.addEventListener('change', onSystemChange);
    return () => {
        removeNavigationListener();
        media.removeEventListener('change', onSystemChange);
        if (active) restore();
    };
}
