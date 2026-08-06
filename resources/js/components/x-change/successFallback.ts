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

export type SuccessRiderStagePayload = {
    content?: string | null;
    payload?: (Record<string, unknown> & {
        content?: string | null;
    }) | null;
    [key: string]: unknown;
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

const pendingClaimRiderMessage = 'Your claim is being processed.';

const genericReadyRiderMessages = new Set([
    'Your Pay Code is ready.',
    'Your Pay Code is ready',
]);

function isGenericReadyRiderMessage(content: unknown): boolean {
    return typeof content === 'string'
        && genericReadyRiderMessages.has(content.trim());
}

export function resolveSuccessRiderMessage(
    riderContent: SuccessRiderMessagePayload | null | undefined,
    payload: SuccessFallbackStatePayload,
): SuccessRiderMessagePayload | null {
    if (!riderContent?.enabled || !riderContent.content) {
        return null;
    }

    if (
        isPendingClaimOutcome(payload)
        && isGenericReadyRiderMessage(riderContent.content)
    ) {
        return {
            ...riderContent,
            content: pendingClaimRiderMessage,
        };
    }

    return riderContent;
}

export function resolveSuccessRiderStage(
    stage: SuccessRiderStagePayload,
    payload: SuccessFallbackStatePayload,
): SuccessRiderStagePayload {
    if (!isPendingClaimOutcome(payload)) {
        return stage;
    }

    const hasGenericContent = isGenericReadyRiderMessage(stage.content);
    const hasGenericPayloadContent = isGenericReadyRiderMessage(
        stage.payload?.content,
    );

    if (!hasGenericContent && !hasGenericPayloadContent) {
        return stage;
    }

    return {
        ...stage,
        ...(hasGenericContent ? { content: pendingClaimRiderMessage } : {}),
        ...(hasGenericPayloadContent
            ? {
                payload: {
                    ...stage.payload,
                    content: pendingClaimRiderMessage,
                },
            }
            : {}),
    };
}

export function shouldRenderSuccessRiderMessage(
    riderContent: SuccessRiderMessagePayload | null | undefined,
): boolean {
    return Boolean(riderContent?.enabled && riderContent?.content);
}
