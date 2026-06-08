export type CompiledClaimResultPayload = {
    status?: string | null;
    claim_type?: string | null;
    voucher_code?: string | null;
    claimed?: boolean | null;
    requested_amount?: number | string | null;
    disbursed_amount?: number | string | null;
    currency?: string | null;
    remaining_balance?: number | string | null;
    fully_claimed?: boolean | null;
    messages?: string[] | null;
} | null;

export type SuccessCompiledClaimResultViewModel = {
    visible: boolean;
    status: string | null;
    title: string;
    messages: string[];
    amountText: string | null;
    isPending: boolean;
};

function formatAmount(
    amount: number | string | null | undefined,
    currency: string | null | undefined,
): string | null {
    if (amount === null || amount === undefined || amount === '' || !currency) {
        return null;
    }

    const numericAmount = Number(amount);

    if (!Number.isFinite(numericAmount)) {
        return `${currency} ${amount}`;
    }

    return `${currency} ${numericAmount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export function resolveSuccessCompiledClaimResultViewModel(
    result: CompiledClaimResultPayload,
): SuccessCompiledClaimResultViewModel {
    if (!result) {
        return {
            visible: false,
            status: null,
            title: '',
            messages: [],
            amountText: null,
            isPending: false,
        };
    }

    const status = result.status ?? null;
    const isPending = status === 'pending';

    return {
        visible: true,
        status,
        title: isPending
            ? 'Claim submitted for processing'
            : 'Claim completed',
        messages: Array.isArray(result.messages) ? result.messages : [],
        amountText: formatAmount(result.disbursed_amount, result.currency),
        isPending,
    };
}
