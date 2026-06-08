export type ApprovalMetadataPayload = {
    provider?: string | null;
    authorization_type?: string | null;
    reference_id?: string | null;
    expires_at?: string | null;
    otp_required?: boolean | null;
    polling_required?: boolean | null;
    manual_review?: boolean | null;
    message?: string | null;
} | null;

export type ApprovalMetadataViewModel = {
    provider: string | null;
    authorizationType: string | null;
    referenceId: string | null;
    expiresAt: string | null;
    otpRequired: boolean;
    pollingRequired: boolean;
    manualReview: boolean;
    message: string | null;
    headline: string;
};

function nullableString(value: unknown): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return String(value);
}

export function resolveApprovalMetadataViewModel(
    metadata: ApprovalMetadataPayload,
): ApprovalMetadataViewModel {
    const otpRequired = Boolean(metadata?.otp_required);
    const pollingRequired = Boolean(metadata?.polling_required);
    const manualReview = Boolean(metadata?.manual_review);

    let headline = 'Approval required';

    if (otpRequired) {
        headline = 'OTP verification required';
    } else if (pollingRequired) {
        headline = 'Waiting for provider confirmation';
    } else if (manualReview) {
        headline = 'Manual review required';
    }

    return {
        provider: nullableString(metadata?.provider),
        authorizationType: nullableString(metadata?.authorization_type),
        referenceId: nullableString(metadata?.reference_id),
        expiresAt: nullableString(metadata?.expires_at),
        otpRequired,
        pollingRequired,
        manualReview,
        message: nullableString(metadata?.message),
        headline,
    };
}

