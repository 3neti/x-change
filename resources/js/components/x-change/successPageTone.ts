export type SuccessPageToneInput = {
    compiledClaimStatus?: string | null;
    claimOutcome?: string | null;
    riderState?: string | null;
};

export type SuccessPageTone = {
    isPending: boolean;
    iconClass: string;
};

function isPendingValue(value: string | null | undefined): boolean {
    return value === 'pending';
}

export function resolveSuccessPageTone(input: SuccessPageToneInput): SuccessPageTone {
    const isPending =
        isPendingValue(input.compiledClaimStatus)
        || isPendingValue(input.claimOutcome)
        || isPendingValue(input.riderState);

    return {
        isPending,
        iconClass: isPending ? 'text-amber-500' : 'text-green-500',
    };
}
