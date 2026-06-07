export type LegacyClaimStartForm = {
    code: string;
    get: (
        url: string,
        options: {
            preserveState: (page: { props: { errors?: Record<string, unknown> } }) => boolean;
            preserveScroll: boolean;
        },
    ) => void;
};

export function normalizeClaimCode(
    code: string | null | undefined,
): string {
    return (code || '').trim().toUpperCase();
}

export function shouldPreserveClaimStartState(
    page: { props: { errors?: Record<string, unknown> } },
): boolean {
    const hasErrors = Object.keys(page.props.errors || {}).length > 0;

    return !hasErrors;
}

export function submitLegacyClaimStart(
    form: LegacyClaimStartForm,
    enteredCode: string | null | undefined,
): void {
    form.code = normalizeClaimCode(enteredCode || form.code);

    form.get('/x/claim', {
        preserveState: shouldPreserveClaimStartState,
        preserveScroll: true,
    });
}

