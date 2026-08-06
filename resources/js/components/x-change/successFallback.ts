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

export type SuccessRiderMessagePayload = {
    enabled?: boolean | null;
    type?: string | null;
    content?: unknown;
    meta?: Record<string, unknown>;
};

const genericReadyRiderMessages = new Set([
    'Your Pay Code is ready.',
    'Your Pay Code is ready',
]);

export function resolveSuccessRiderMessage(
    riderContent: SuccessRiderMessagePayload | null | undefined,
    payload: SuccessFallbackStatePayload,
): SuccessRiderMessagePayload | null {
    if (!riderContent?.enabled || !riderContent.content) {
        return null;
    }

    if (
        isPendingClaimOutcome(payload)
        && typeof riderContent.content === 'string'
        && genericReadyRiderMessages.has(riderContent.content.trim())
    ) {
        return {
            ...riderContent,
            content: 'Your claim is being processed.',
        };
    }

    return riderContent;
}

export function shouldRenderSuccessRiderMessage(
    riderContent: SuccessRiderMessagePayload | null | undefined,
): boolean {
    return Boolean(riderContent?.enabled && riderContent?.content);
}
