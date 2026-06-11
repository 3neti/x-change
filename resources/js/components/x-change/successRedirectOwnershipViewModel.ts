export type SuccessRedirectOwnershipPayload = {
    owner?: string | null;
    show_countdown?: boolean | null;
    delay_seconds?: number | null;
} | null;

export type SuccessRedirectOwnershipViewModel = {
    owner: string | null;
    showCountdown: boolean;
    delaySeconds: number | null;
    ownedBySuccessPage: boolean;
    ownedByClaimWidget: boolean;
    ownedByXRider: boolean;
};

export const REDIRECT_OWNER_SUCCESS_PAGE = 'success-page';
export const REDIRECT_OWNER_CLAIM_WIDGET = 'claim-widget';
export const REDIRECT_OWNER_X_RIDER = 'x-rider';

export function resolveSuccessRedirectOwnershipViewModel(
    redirect: SuccessRedirectOwnershipPayload,
): SuccessRedirectOwnershipViewModel {
    const owner = redirect?.owner ?? null;
    const ownedBySuccessPage = owner === REDIRECT_OWNER_SUCCESS_PAGE;
    const ownedByClaimWidget = owner === REDIRECT_OWNER_CLAIM_WIDGET;
    const ownedByXRider = owner === REDIRECT_OWNER_X_RIDER;

    return {
        owner,
        showCountdown: ownedByClaimWidget && redirect?.show_countdown === true,
        delaySeconds: redirect?.delay_seconds ?? null,
        ownedBySuccessPage,
        ownedByClaimWidget,
        ownedByXRider,
    };
}
