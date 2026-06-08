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
};

export function resolveSuccessCompiledClaimResultViewModel(
    result: CompiledClaimResultPayload,
): SuccessCompiledClaimResultViewModel {
    if (!result) {
        return {
            visible: false,
            status: null,
            title: '',
            messages: [],
        };
    }

    const status = result.status ?? null;

    return {
        visible: true,
        status,
        title: status === 'pending'
            ? 'Claim submitted for processing'
            : 'Claim completed',
        messages: Array.isArray(result.messages) ? result.messages : [],
    };
}
