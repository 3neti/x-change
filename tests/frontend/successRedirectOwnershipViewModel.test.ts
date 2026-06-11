import { describe, expect, it } from 'vitest';
import {
    REDIRECT_OWNER_CLAIM_WIDGET,
    REDIRECT_OWNER_SUCCESS_PAGE,
    REDIRECT_OWNER_X_RIDER,
    resolveSuccessRedirectOwnershipViewModel,
} from '../../resources/js/components/x-change/successRedirectOwnershipViewModel';

describe('success redirect ownership view model', () => {
    it('defaults to no redirect owner', () => {
        expect(resolveSuccessRedirectOwnershipViewModel(null)).toEqual({
            owner: null,
            showCountdown: false,
            delaySeconds: null,
            ownedBySuccessPage: false,
            ownedByClaimWidget: false,
            ownedByXRider: false,
        });
    });

    it('shows countdown when claim widget owns redirect and countdown is enabled', () => {
        expect(resolveSuccessRedirectOwnershipViewModel({
            owner: REDIRECT_OWNER_CLAIM_WIDGET,
            show_countdown: true,
            delay_seconds: 5,
        })).toEqual({
            owner: REDIRECT_OWNER_CLAIM_WIDGET,
            showCountdown: true,
            delaySeconds: 5,
            ownedBySuccessPage: false,
            ownedByClaimWidget: true,
            ownedByXRider: false,
        });
    });

    it('does not show countdown when claim widget owns redirect but countdown is disabled', () => {
        expect(resolveSuccessRedirectOwnershipViewModel({
            owner: REDIRECT_OWNER_CLAIM_WIDGET,
            show_countdown: false,
            delay_seconds: 5,
        })).toMatchObject({
            owner: REDIRECT_OWNER_CLAIM_WIDGET,
            showCountdown: false,
            delaySeconds: 5,
            ownedByClaimWidget: true,
        });
    });

    it('does not show countdown when success page owns redirect', () => {
        expect(resolveSuccessRedirectOwnershipViewModel({
            owner: REDIRECT_OWNER_SUCCESS_PAGE,
            show_countdown: true,
            delay_seconds: 5,
        })).toMatchObject({
            owner: REDIRECT_OWNER_SUCCESS_PAGE,
            showCountdown: false,
            ownedBySuccessPage: true,
            ownedByClaimWidget: false,
            ownedByXRider: false,
        });
    });

    it('does not show countdown when x-rider owns redirect', () => {
        expect(resolveSuccessRedirectOwnershipViewModel({
            owner: REDIRECT_OWNER_X_RIDER,
            show_countdown: true,
            delay_seconds: 5,
        })).toMatchObject({
            owner: REDIRECT_OWNER_X_RIDER,
            showCountdown: false,
            ownedBySuccessPage: false,
            ownedByClaimWidget: false,
            ownedByXRider: true,
        });
    });

    it('preserves unknown owners without enabling countdown', () => {
        expect(resolveSuccessRedirectOwnershipViewModel({
            owner: 'external',
            show_countdown: true,
            delay_seconds: 10,
        })).toEqual({
            owner: 'external',
            showCountdown: false,
            delaySeconds: 10,
            ownedBySuccessPage: false,
            ownedByClaimWidget: false,
            ownedByXRider: false,
        });
    });
});
