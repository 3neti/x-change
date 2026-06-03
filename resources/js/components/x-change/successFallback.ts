export type SuccessVoucherPayload = {
    amount?: number | string | null;
    formatted_amount?: string | null;
    formattedAmount?: string | null;
    currency?: string | null;
};

export type SuccessFallbackStatePayload = {
    claimOutcome?: string | null;
    riderState?: string | null;
};

export function numericVoucherAmount(voucher: SuccessVoucherPayload): number {
    return Number(voucher.amount ?? 0);
}

export function hasNonZeroVoucherAmount(voucher: SuccessVoucherPayload): boolean {
    return numericVoucherAmount(voucher) > 0;
}

export function formatSuccessVoucherAmount(voucher: SuccessVoucherPayload): string {
    return voucher.formatted_amount
        ?? voucher.formattedAmount
        ?? (hasNonZeroVoucherAmount(voucher)
            ? `${voucher.currency ?? ''} ${numericVoucherAmount(voucher).toLocaleString()}`
            : '');
}

export function isPendingClaimOutcome(payload: SuccessFallbackStatePayload): boolean {
    return payload.claimOutcome === 'accepted_pending'
        || payload.riderState === 'accepted_pending';
}

export function resolveSuccessFallbackTitle(
    voucher: SuccessVoucherPayload,
    payload: SuccessFallbackStatePayload,
): string {
    if (isPendingClaimOutcome(payload)) {
        return 'Your claim is being processed';
    }

    return hasNonZeroVoucherAmount(voucher)
        ? 'Disbursed to your account'
        : 'Pay Code claimed';
}

export function shouldRenderSuccessVoucherCodeBadge(
    hasSuccessStages: boolean,
    hasRiderMessage: boolean,
): boolean {
    return !hasSuccessStages
        && !hasRiderMessage;
}
