export const SUCCESS_REDIRECT_OWNER_CLAIM_WIDGET = 'claim-widget';

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

    return redirect.owner === SUCCESS_REDIRECT_OWNER_CLAIM_WIDGET
        && redirect.show_countdown === true;
}

export type RiderRedirectPayload = {
    enabled?: boolean | null;
    url?: string | null;
    delay_seconds?: number | null;
};

export type ResolvedSuccessRedirect = {
    enabled: true;
    url: string;
    delay_seconds: number;
    content?: string;
};

export function resolveSuccessRedirect(
    riderRedirect: RiderRedirectPayload | null | undefined,
    redirect: SuccessRedirectPayload | null | undefined,
    redirectEndpoint: string | null | undefined,
): ResolvedSuccessRedirect | null {
    if (!redirectEndpoint) {
        return null;
    }

    if (riderRedirect?.enabled && riderRedirect.url) {
        return {
            enabled: true,
            url: riderRedirect.url,
            delay_seconds: Number(riderRedirect.delay_seconds ?? 5),
        };
    }

    if (
        redirect?.owner === SUCCESS_REDIRECT_OWNER_CLAIM_WIDGET
        && redirect.show_countdown === true
    ) {
        return {
            enabled: true,
            url: redirectEndpoint,
            delay_seconds: Number(redirect.delay_seconds ?? 5),
            content: 'Redirecting shortly...',
        };
    }

    return null;
}
