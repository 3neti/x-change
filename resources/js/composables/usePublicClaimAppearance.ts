import { router } from '@inertiajs/vue3';
import '../experience/themes.css';

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

    const apply = () => {
        if (!active) return;
        if (root.classList.contains('dark')) root.classList.remove('dark');
        if (root.dataset.xChangeTheme !== 'default') root.dataset.xChangeTheme = 'default';
        if (root.dataset.xChangeClaimAppearance !== 'light') root.dataset.xChangeClaimAppearance = 'light';
        if (root.style.colorScheme !== 'light') root.style.colorScheme = 'light';
    };

    const restore = () => {
        active = false;
        delete root.dataset.xChangeClaimAppearance;
        root.style.colorScheme = previousColorScheme;
        if (previousTheme === undefined) delete root.dataset.xChangeTheme;
        else root.dataset.xChangeTheme = previousTheme;

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
            }
            active = true;
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
