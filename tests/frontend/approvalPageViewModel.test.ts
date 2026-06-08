import { describe, expect, it } from 'vitest';
import {
    DEFAULT_APPROVAL_MESSAGE,
    resolveApprovalPageViewModel,
} from '../../resources/js/components/x-change/approvalPageViewModel';

describe('approval page view model', () => {
    it('uses default approval copy without compiled claim result', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: null,
            message: null,
        })).toEqual({
            title: 'Claim submitted for processing',
            status: 'pending',
            message: DEFAULT_APPROVAL_MESSAGE,
            amountText: null,
            messages: [],
            headline: 'Approval required',
            provider: null,
            authorizationType: null,
            referenceId: null,
            expiresAt: null,
            metadataMessage: null,
        });
    });

    it('uses provided approval message', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: null,
            message: 'Please wait while your claim is reviewed.',
        })).toMatchObject({
            message: 'Please wait while your claim is reviewed.',
        });
    });

    it('resolves pending compiled claim result details', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
                status: 'pending',
                claim_type: 'withdraw',
                voucher_code: 'TEST123',
                claimed: false,
                requested_amount: null,
                disbursed_amount: 1000,
                currency: 'PHP',
                remaining_balance: null,
                fully_claimed: false,
                messages: ['Approval required.'],
            },
            message: null,
        })).toEqual({
            title: 'Claim submitted for processing',
            status: 'pending',
            message: DEFAULT_APPROVAL_MESSAGE,
            amountText: 'PHP 1,000.00',
            messages: ['Approval required.'],
            headline: 'Approval required',
            provider: null,
            authorizationType: null,
            referenceId: null,
            expiresAt: null,
            metadataMessage: null,
        });
    });

    it('falls back to pending status when compiled result has no status', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
                messages: ['Waiting for provider confirmation.'],
            },
            message: null,
        })).toMatchObject({
            status: 'pending',
            messages: ['Waiting for provider confirmation.'],
            headline: 'Approval required',
            provider: null,
            authorizationType: null,
            referenceId: null,
            expiresAt: null,
            metadataMessage: null,
        });
    });

    it('resolves approval metadata details', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
                status: 'pending',
                approval_metadata: {
                    provider: 'payanamics',
                    authorization_type: 'otp',
                    reference_id: 'AUTH-123',
                    expires_at: '2026-06-08T12:00:00+08:00',
                    otp_required: true,
                    message: 'Enter the OTP sent to your mobile number.',
                },
            },
            message: null,
        })).toMatchObject({
            headline: 'OTP verification required',
            provider: 'payanamics',
            authorizationType: 'otp',
            referenceId: 'AUTH-123',
            expiresAt: '2026-06-08T12:00:00+08:00',
            metadataMessage: 'Enter the OTP sent to your mobile number.',
        });
    });
});
