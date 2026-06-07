export type ClaimWidgetVoucherLike = {
    status?: string | null;
    redeemed_at?: unknown;
    expired_at?: unknown;
} | null | undefined;

export type ClaimWidgetStorageLike = {
    getItem: (key: string) => string | null;
};

export function isNonActiveVoucher(
    voucher: ClaimWidgetVoucherLike,
): boolean {
    const status = voucher?.status;

    return status === 'redeemed' || status === 'expired';
}

export function resolveVoucherStatusDate(
    voucher: ClaimWidgetVoucherLike,
): unknown {
    if (!voucher) {
        return null;
    }

    if (voucher.status === 'redeemed') {
        return voucher.redeemed_at ?? null;
    }

    if (voucher.status === 'expired') {
        return voucher.expired_at ?? null;
    }

    return null;
}

export function isReturningRedeemerFromStorage(
    storage: ClaimWidgetStorageLike | null | undefined = globalThis.localStorage,
): boolean {
    try {
        const raw = storage?.getItem('form_flow_persist_wallet_info');

        if (!raw) {
            return false;
        }

        const saved = JSON.parse(raw);

        return !!saved.mobile;
    } catch {
        return false;
    }
}
