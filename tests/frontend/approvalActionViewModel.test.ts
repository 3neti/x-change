import { describe, expect, it } from 'vitest';
import { resolveApprovalActionViewModel } from '../../resources/js/components/x-change/approvalActionViewModel';

describe('approval action view model', () => {
    it('defaults to no action', () => {
        expect(resolveApprovalActionViewModel({})).toEqual({
            mode: 'none',
            showOtpForm: false,
            showPollingNotice: false,
            showManualReviewNotice: false,
        });
    });

    it('shows OTP form when OTP is required', () => {
        expect(resolveApprovalActionViewModel({
            otpRequired: true,
        })).toEqual({
            mode: 'otp',
            showOtpForm: true,
            showPollingNotice: false,
            showManualReviewNotice: false,
        });
    });

    it('shows polling notice when provider polling is required', () => {
        expect(resolveApprovalActionViewModel({
            pollingRequired: true,
        })).toEqual({
            mode: 'polling',
            showOtpForm: false,
            showPollingNotice: true,
            showManualReviewNotice: false,
        });
    });

    it('shows manual review notice when manual review is required', () => {
        expect(resolveApprovalActionViewModel({
            manualReview: true,
        })).toEqual({
            mode: 'manual_review',
            showOtpForm: false,
            showPollingNotice: false,
            showManualReviewNotice: true,
        });
    });

    it('prioritizes OTP over polling and manual review', () => {
        expect(resolveApprovalActionViewModel({
            otpRequired: true,
            pollingRequired: true,
            manualReview: true,
        })).toMatchObject({
            mode: 'otp',
            showOtpForm: true,
        });
    });

    it('prioritizes polling over manual review', () => {
        expect(resolveApprovalActionViewModel({
            pollingRequired: true,
            manualReview: true,
        })).toMatchObject({
            mode: 'polling',
            showPollingNotice: true,
            showManualReviewNotice: false,
        });
    });
});
