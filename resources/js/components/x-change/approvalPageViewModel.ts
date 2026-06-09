import { resolveApprovalMetadataViewModel } from '@/components/x-change/approvalMetadataViewModel';
import {
    resolveSuccessCompiledClaimResultViewModel,
    type CompiledClaimResultPayload,
} from '@/components/x-change/successCompiledClaimResult';
import { resolveApprovalActionViewModel } from '@/components/x-change/approvalActionViewModel';

export const DEFAULT_APPROVAL_MESSAGE =
    'Your claim has been submitted and is awaiting approval.';

export type ApprovalPageViewModelInput = {
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
};

export function resolveApprovalPageViewModel(
    input: ApprovalPageViewModelInput,
): ApprovalPageViewModel {
    const compiledClaimResult = resolveSuccessCompiledClaimResultViewModel(
        input.compiledClaimResult ?? null,
    );

    const approvalMetadata = resolveApprovalMetadataViewModel(
        input.compiledClaimResult?.approval_metadata ?? null,
    );

    const approvalAction = resolveApprovalActionViewModel({
        otpRequired: approvalMetadata.otpRequired,
        pollingRequired: approvalMetadata.pollingRequired,
        manualReview: approvalMetadata.manualReview,
    });

    return {
        title: compiledClaimResult.title || 'Claim submitted for processing',
        status: compiledClaimResult.status || 'pending',
        message: input.message || DEFAULT_APPROVAL_MESSAGE,
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
    };
}
