export type SuccessRedirectPayload = {
    owner?: string | null;
    show_countdown?: boolean | null;
    delay_seconds?: number | null;
};

export function shouldRenderSuccessRedirectCountdown(
    redirect: SuccessRedirectPayload | null | undefined
): boolean {
    if (!redirect) {
        return false;
    }

    return redirect.show_countdown === true
        && ['claim-widget', 'success-page'].includes(String(redirect.owner ?? ''));
}
