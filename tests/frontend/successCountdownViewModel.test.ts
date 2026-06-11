import { describe, expect, it } from 'vitest';
import { resolveSuccessCountdownViewModel } from '../../resources/js/components/x-change/successCountdownViewModel';

const redirectOwnership = {
    owner: 'claim-widget',
    showCountdown: true,
    delaySeconds: 5,
    ownedBySuccessPage: false,
    ownedByClaimWidget: true,
    ownedByXRider: false,
};

describe('success countdown view model', () => {
    it('is hidden when redirect endpoint is missing', () => {
        expect(resolveSuccessCountdownViewModel({
            countdownRedirect: {
                enabled: true,
                delay_seconds: 5,
            },
            redirectEndpoint: null,
            redirectOwnership,
        })).toEqual({
            visible: false,
            redirect: null,
            endpoint: null,
            delaySeconds: null,
        });
    });

    it('is hidden when redirect ownership does not allow countdown', () => {
        expect(resolveSuccessCountdownViewModel({
            countdownRedirect: {
                enabled: true,
                delay_seconds: 5,
            },
            redirectEndpoint: '/x/claim/TEST123/redirect',
            redirectOwnership: {
                ...redirectOwnership,
                showCountdown: false,
            },
        })).toEqual({
            visible: false,
            redirect: null,
            endpoint: '/x/claim/TEST123/redirect',
            delaySeconds: null,
        });
    });

    it('is hidden when countdown redirect is missing', () => {
        expect(resolveSuccessCountdownViewModel({
            countdownRedirect: null,
            redirectEndpoint: '/x/claim/TEST123/redirect',
            redirectOwnership,
        })).toEqual({
            visible: false,
            redirect: null,
            endpoint: '/x/claim/TEST123/redirect',
            delaySeconds: null,
        });
    });

    it('is visible with redirect endpoint, ownership, and redirect payload', () => {
        expect(resolveSuccessCountdownViewModel({
            countdownRedirect: {
                enabled: true,
                delay_seconds: 7,
            },
            redirectEndpoint: '/x/claim/TEST123/redirect',
            redirectOwnership,
        })).toEqual({
            visible: true,
            redirect: {
                enabled: true,
                delay_seconds: 7,
            },
            endpoint: '/x/claim/TEST123/redirect',
            delaySeconds: 7,
        });
    });

    it('uses timeout as redirect delay fallback', () => {
        expect(resolveSuccessCountdownViewModel({
            countdownRedirect: {
                enabled: true,
                timeout: 9,
            },
            redirectEndpoint: '/x/claim/TEST123/redirect',
            redirectOwnership,
        })).toMatchObject({
            visible: true,
            redirect: {
                delay_seconds: 9,
            },
            delaySeconds: 9,
        });
    });

    it('uses redirect ownership delay when redirect payload has no delay', () => {
        expect(resolveSuccessCountdownViewModel({
            countdownRedirect: {
                enabled: true,
            },
            redirectEndpoint: '/x/claim/TEST123/redirect',
            redirectOwnership: {
                ...redirectOwnership,
                delaySeconds: 12,
            },
        })).toMatchObject({
            visible: true,
            redirect: {
                delay_seconds: 12,
            },
            delaySeconds: 12,
        });
    });

    it('uses default delay when no delay is provided', () => {
        expect(resolveSuccessCountdownViewModel({
            countdownRedirect: {
                enabled: true,
            },
            redirectEndpoint: '/x/claim/TEST123/redirect',
            redirectOwnership: {
                ...redirectOwnership,
                delaySeconds: null,
            },
        })).toMatchObject({
            visible: true,
            redirect: {
                delay_seconds: 5,
            },
            delaySeconds: 5,
        });
    });
});
