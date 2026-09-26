export type SuccessPageToneInput = {
    compiledClaimStatus?: string | null;
    claimOutcome?: string | null;
    claimWorkflowKey?: string | null;
    riderState?: string | null;
    successPresentationState?: string | null;
};

export type SuccessPageTone = {
    isPending: boolean;
    isAttention: boolean;
    isWarning: boolean;
    iconClass: string;
};

function isPendingValue(value: string | null | undefined): boolean {
    return value === 'pending' || value === 'accepted_pending';
}
export function resolveSuccessPageTone(input: SuccessPageToneInput): SuccessPageTone {
    if (input.claimWorkflowKey === 'lead-intake.v1') {
        return {
            isPending: false,
            isAttention: false,
            isWarning: false,
            iconClass: 'text-green-500',
        };
    }

    if (input.successPresentationState === 'needs_attention') {
        return {
            isPending: false,
            isAttention: true,
            isWarning: true,
            iconClass: 'text-amber-500',
        };
    }

    const isPending =
        isPendingValue(input.compiledClaimStatus)
        || isPendingValue(input.claimOutcome)
        || isPendingValue(input.riderState);

    return {
        isPending,
        isAttention: false,
        isWarning: isPending,
        iconClass: isPending ? 'text-amber-500' : 'text-green-500',
    };
}
