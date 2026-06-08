export type ApprovalActionViewModelInput = {
    otpRequired?: boolean | null;
    pollingRequired?: boolean | null;
    manualReview?: boolean | null;
};

export type ApprovalActionMode =
    | 'otp'
    | 'polling'
    | 'manual_review'
    | 'none';

export type ApprovalActionViewModel = {
    mode: ApprovalActionMode;
    showOtpForm: boolean;
    showPollingNotice: boolean;
    showManualReviewNotice: boolean;
};

export function resolveApprovalActionViewModel(
    input: ApprovalActionViewModelInput,
): ApprovalActionViewModel {
    if (input.otpRequired) {
        return {
            mode: 'otp',
            showOtpForm: true,
            showPollingNotice: false,
            showManualReviewNotice: false,
        };
    }

    if (input.pollingRequired) {
        return {
            mode: 'polling',
            showOtpForm: false,
            showPollingNotice: true,
            showManualReviewNotice: false,
        };
    }

    if (input.manualReview) {
        return {
            mode: 'manual_review',
            showOtpForm: false,
            showPollingNotice: false,
            showManualReviewNotice: true,
        };
    }

    return {
        mode: 'none',
        showOtpForm: false,
        showPollingNotice: false,
        showManualReviewNotice: false,
    };
}
