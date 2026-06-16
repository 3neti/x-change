import { describe, expect, it } from 'vitest';
import {
    DEFAULT_APPROVAL_MESSAGE,
    resolveApprovalPageViewModel,
} from '../../resources/js/components/x-change/approvalPageViewModel';

describe('approval page view model', () => {
    it('uses missing context copy without approval or compiled claim result', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: null,
            message: null,
        })).toEqual({
            title: 'Approval session unavailable',
            status: 'pending',
            message: 'We could not find the approval session for this claim. Please restart the claim flow or try again from your voucher.',
            amountText: null,
            messages: [],
            headline: 'Approval required',
            provider: null,
            authorizationType: null,
            referenceId: null,
            expiresAt: null,
            metadataMessage: null,
            actionMode: 'none',
            showOtpForm: false,
            showPollingNotice: false,
            showManualReviewNotice: false,
            missingContext: true,
        });
    });

    it('uses missing context message when approval result is unavailable', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: null,
            message: 'Please wait while your claim is reviewed.',
        })).toMatchObject({
            message: 'We could not find the approval session for this claim. Please restart the claim flow or try again from your voucher.',
            missingContext: true,
        });
    });

    it('uses provided approval message when approval result is available', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
                status: 'pending',
                messages: [],
            },
            message: 'Please wait while your claim is reviewed.',
        })).toMatchObject({
            message: 'Please wait while your claim is reviewed.',
            missingContext: false,
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
            actionMode: 'none',
            showOtpForm: false,
            showPollingNotice: false,
            showManualReviewNotice: false,
            missingContext: false,
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
                    provider: 'paynamics',
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
            provider: 'paynamics',
            authorizationType: 'otp',
            referenceId: 'AUTH-123',
            expiresAt: '2026-06-08T12:00:00+08:00',
            metadataMessage: 'Enter the OTP sent to your mobile number.',
        });
    });

    it('shows OTP action when approval metadata requires OTP', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
                status: 'pending',
                approval_metadata: {
                    otp_required: true,
                },
            },
            message: null,
        })).toMatchObject({
            actionMode: 'otp',
            showOtpForm: true,
            showPollingNotice: false,
            showManualReviewNotice: false,
        });
    });

    it('shows polling notice when approval metadata requires polling', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
                status: 'pending',
                approval_metadata: {
                    polling_required: true,
                },
            },
            message: null,
        })).toMatchObject({
            actionMode: 'polling',
            showOtpForm: false,
            showPollingNotice: true,
            showManualReviewNotice: false,
        });
    });

    it('shows manual review notice when approval metadata requires manual review', () => {
        expect(resolveApprovalPageViewModel({
            compiledClaimResult: {
                status: 'pending',
                approval_metadata: {
                    manual_review: true,
                },
            },
            message: null,
        })).toMatchObject({
            actionMode: 'manual_review',
            showOtpForm: false,
            showPollingNotice: false,
            showManualReviewNotice: true,
        });
    });

    it('prefers approval payload over compiled claim approval metadata', () => {
        const viewModel = resolveApprovalPageViewModel({
            approval: {
                required: true,
                provider: 'paynamics',
                authorization_type: 'otp',
                reference_id: 'APPROVAL-REF',
                otp_required: true,
                message: 'Approval prop message.',
            },
            compiledClaimResult: {
                status: null,
                claim_type: null,
                voucher_code: 'TEST-1234',
                claimed: null,
                requested_amount: null,
                disbursed_amount: null,
                currency: null,
                remaining_balance: null,
                fully_claimed: null,
                messages: [],
                approval_metadata: {
                    provider: 'old-provider',
                    authorization_type: 'old-auth',
                    reference_id: 'OLD-REF',
                    otp_required: false,
                    expires_at: null,
                    polling_required: false,
                    manual_review: false,
                    message: 'Old metadata message.',
                },
            },
            message: null,
        });

        expect(viewModel.status).toBe('approval_required');
        expect(viewModel.provider).toBe('paynamics');
        expect(viewModel.authorizationType).toBe('otp');
        expect(viewModel.referenceId).toBe('APPROVAL-REF');
        expect(viewModel.metadataMessage).toBe('Approval prop message.');
        expect(viewModel.actionMode).toBe('otp');
        expect(viewModel.showOtpForm).toBe(true);
    });

    it('keeps OTP form available after failed OTP approval result', () => {
        const viewModel = resolveApprovalPageViewModel({
            compiledClaimResult: {
                status: 'failed',
                claim_type: null,
                voucher_code: 'TEST123',
                claimed: null,
                requested_amount: null,
                disbursed_amount: null,
                currency: null,
                remaining_balance: null,
                fully_claimed: null,
                messages: ['Invalid OTP.'],
                approval_metadata: {
                    provider: 'paynamics',
                    authorization_type: 'otp',
                    reference_id: 'AUTH-123',
                    otp_required: true,
                    expires_at: null,
                    polling_required: false,
                    manual_review: false,
                    message: 'Paynamics payout OTP is pending.',
                },
            },
            message: null,
        });

        expect(viewModel.status).toBe('failed');
        expect(viewModel.messages).toContain('Invalid OTP.');
        expect(viewModel.provider).toBe('paynamics');
        expect(viewModel.referenceId).toBe('AUTH-123');
        expect(viewModel.showOtpForm).toBe(true);
        expect(viewModel.actionMode).toBe('otp');
    });

    it('marks approval context missing when no approval result is available', () => {
        const viewModel = resolveApprovalPageViewModel({
            compiledClaimResult: null,
            approval: null,
            message: null,
        });

        expect(viewModel.missingContext).toBe(true);
        expect(viewModel.title).toBe('Approval session unavailable');
        expect(viewModel.showOtpForm).toBe(false);
    });
});
