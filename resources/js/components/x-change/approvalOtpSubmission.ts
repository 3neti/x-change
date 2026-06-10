export type ApprovalOtpSubmissionInput = {
    otp: string;
    referenceId?: string | null;
    provider?: string | null;
};

export type ApprovalOtpSubmissionPayload = {
    otp: string;
    referenceId: string | null;
    provider: string | null;
};

export type ApprovalOtpSubmissionEvent =
    | {
    intent: 'blocked';
    payload: null;
    error: string;
}
    | {
    intent: 'submit';
    payload: ApprovalOtpSubmissionPayload;
    error: null;
};

export function resolveApprovalOtpSubmission(
    input: ApprovalOtpSubmissionInput,
): ApprovalOtpSubmissionEvent {
    const otp = input.otp.trim();

    if (otp === '') {
        return {
            intent: 'blocked',
            payload: null,
            error: 'OTP is required.',
        };
    }

    return {
        intent: 'submit',
        payload: {
            otp,
            referenceId: input.referenceId ?? null,
            provider: input.provider ?? null,
        },
        error: null,
    };
}
