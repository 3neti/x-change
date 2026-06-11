import type { RiderRedirectPayload } from '@/components/x-change/successRedirect';
import type { SuccessRedirectOwnershipViewModel } from '@/components/x-change/successRedirectOwnershipViewModel';

export type SuccessCountdownViewModelInput = {
    countdownRedirect?: RiderRedirectPayload | null;
    redirectEndpoint?: string | null;
    redirectOwnership: SuccessRedirectOwnershipViewModel;
};

export type SuccessCountdownViewModel = {
    visible: boolean;
    redirect: RiderRedirectPayload | null;
    endpoint: string | null;
    delaySeconds: number | null;
};

function redirectDelaySeconds(
    redirect: RiderRedirectPayload | null | undefined,
): number | null {
    const value = redirect?.delay_seconds ?? redirect?.timeout ?? null;

    return typeof value === 'number'
        ? value
        : null;
}

export function resolveSuccessCountdownViewModel(
    input: SuccessCountdownViewModelInput,
): SuccessCountdownViewModel {
    if (!input.redirectEndpoint) {
        return {
            visible: false,
            redirect: null,
            endpoint: null,
            delaySeconds: null,
        };
    }

    if (!input.redirectOwnership.showCountdown) {
        return {
            visible: false,
            redirect: null,
            endpoint: input.redirectEndpoint,
            delaySeconds: null,
        };
    }

    if (!input.countdownRedirect) {
        return {
            visible: false,
            redirect: null,
            endpoint: input.redirectEndpoint,
            delaySeconds: null,
        };
    }

    const delaySeconds =
        redirectDelaySeconds(input.countdownRedirect)
        ?? input.redirectOwnership.delaySeconds
        ?? 5;

    return {
        visible: true,
        redirect: {
            ...input.countdownRedirect,
            delay_seconds: delaySeconds,
        },
        endpoint: input.redirectEndpoint,
        delaySeconds,
    };
}
