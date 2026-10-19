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

    const enforceLight = () => {
        root.classList.remove('dark');
        root.style.colorScheme = 'light';
        root.dataset.xChangeClaimAppearance = 'light';
        root.dataset.xChangeTheme = 'default';
    };
    const restore = () => {
        let saved: string | null = null;
        try { saved = localStorage.getItem('appearance'); } catch { /* Storage may be unavailable in a guest browser. */ }
        root.classList.toggle('dark', saved === 'dark' || ((!saved || saved === 'system') && media.matches));
        root.style.colorScheme = previousColorScheme;
        delete root.dataset.xChangeClaimAppearance;
        if (previousTheme === undefined) delete root.dataset.xChangeTheme;
        else root.dataset.xChangeTheme = previousTheme;
    };
    const applyPage = (page: ClaimAppearancePage) => {
        const next = isPublicClaimPage(page);
        if (next && !active) {
            previousColorScheme = root.style.colorScheme;
            previousTheme = root.dataset.xChangeTheme;
        }
        if (next) enforceLight();
        else if (active) restore();
        active = next;
    };
    const onSystemChange = () => { if (active) enforceLight(); };
    applyPage(initialPage);
    const removeNavigationListener = router.on('navigate', event => applyPage(event.detail.page));
    media.addEventListener('change', onSystemChange);
    return () => {
        removeNavigationListener();
        media.removeEventListener('change', onSystemChange);
        if (active) restore();
    };
}
