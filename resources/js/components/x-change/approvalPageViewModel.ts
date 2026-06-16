import { resolveApprovalMetadataViewModel } from '@/components/x-change/approvalMetadataViewModel';
import {
    resolveSuccessCompiledClaimResultViewModel,
    type CompiledClaimResultPayload,
} from '@/components/x-change/successCompiledClaimResult';
import { resolveApprovalActionViewModel } from '@/components/x-change/approvalActionViewModel';

export const DEFAULT_APPROVAL_MESSAGE =
    'Your claim has been submitted and is awaiting approval.';

export type ApprovalPayload = {
    required: boolean;
    provider: string | null;
    authorization_type: string | null;
    reference_id: string | null;
    otp_required: boolean;
    message: string | null;
};

export type ApprovalPageViewModelInput = {
    approval?: ApprovalPayload | null;
    compiledClaimResult?: CompiledClaimResultPayload;
    message?: string | null;
};

export type ApprovalPageViewModel = {
    title: string;
    status: string;
    message: string;
    amountText: string | null;
    messages: string[];
    headline: string;
    provider: string | null;
    authorizationType: string | null;
    referenceId: string | null;
    expiresAt: string | null;
    metadataMessage: string | null;
    actionMode: 'otp' | 'polling' | 'manual_review' | 'none';
    showOtpForm: boolean;
    showPollingNotice: boolean;
    showManualReviewNotice: boolean;
    missingContext: boolean;
};

export function resolveApprovalPageViewModel(
    input: ApprovalPageViewModelInput,
): ApprovalPageViewModel {
    const compiledClaimResult = resolveSuccessCompiledClaimResultViewModel(
        input.compiledClaimResult ?? null,
    );

    const approvalMetadata = resolveApprovalMetadataViewModel(
        input.approval
            ? {
                provider: input.approval.provider,
                authorization_type: input.approval.authorization_type,
                reference_id: input.approval.reference_id,
                otp_required: input.approval.otp_required,
                message: input.approval.message,
            }
            : input.compiledClaimResult?.approval_metadata ?? null,
    );

    const approvalAction = resolveApprovalActionViewModel({
        otpRequired: approvalMetadata.otpRequired,
        pollingRequired: approvalMetadata.pollingRequired,
        manualReview: approvalMetadata.manualReview,
    });

    const hasApprovalContext =
        input.approval?.required === true
        || input.compiledClaimResult !== null;

    const missingContext = !hasApprovalContext;

    return {
        title: missingContext
            ? 'Approval session unavailable'
            : compiledClaimResult.title || 'Claim submitted for processing',
        status:
            compiledClaimResult.status
            || (input.approval?.required ? 'approval_required' : 'pending'),
        message: missingContext
            ? 'We could not find the approval session for this claim. Please restart the claim flow or try again from your voucher.'
            : input.message || DEFAULT_APPROVAL_MESSAGE,
        amountText: compiledClaimResult.amountText,
        messages: compiledClaimResult.messages,
        headline: approvalMetadata.headline,
        provider: approvalMetadata.provider,
        authorizationType: approvalMetadata.authorizationType,
        referenceId: approvalMetadata.referenceId,
        expiresAt: approvalMetadata.expiresAt,
        metadataMessage: approvalMetadata.message,
        actionMode: approvalAction.mode,
        showOtpForm: approvalAction.showOtpForm,
        showPollingNotice: approvalAction.showPollingNotice,
        showManualReviewNotice: approvalAction.showManualReviewNotice,
        missingContext,
    };
}
