import { router } from '@inertiajs/vue3';

export type ApprovalOtpSubmitAdapterPayload = {
    code: string;
    otp: string;
    referenceId: string | null;
    provider: string | null;
};

export type ApprovalOtpSubmitAdapterOptions = {
    onSuccess?: () => void;
    onError?: (errors: Record<string, unknown>) => void;
    onFinish?: () => void;
};

export function toApprovalOtpPostPayload(
    payload: ApprovalOtpSubmitAdapterPayload,
): Record<string, string | null> {
    return {
        otp: payload.otp,
        reference_id: payload.referenceId,
        provider: payload.provider,
    };
}

export function approvalOtpEndpoint(code: string): string {
    return `/x/claim/${encodeURIComponent(code)}/approval/otp`;
}

export function submitApprovalOtp(
    payload: ApprovalOtpSubmitAdapterPayload,
    options: ApprovalOtpSubmitAdapterOptions = {},
): void {
    router.post(
        approvalOtpEndpoint(payload.code),
        toApprovalOtpPostPayload(payload),
        {
            preserveScroll: true,
            onSuccess: options.onSuccess,
            onError: options.onError,
            onFinish: options.onFinish,
        },
    );
}
