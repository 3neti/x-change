import EstimatePayCodeController from '@/actions/LBHurtado/XChange/Http/Controllers/PayCode/EstimatePayCodeController';
import { onUnmounted, ref, watch, type ComputedRef, type Ref } from 'vue';

export type PayCodeCostCharge = {
    catalog_item_reference?: string | null;
    label?: string | null;
    type?: string | null;
    quantity?: number | string | null;
    price?: number | string | null;
    amount?: number | string | null;
    total?: number | string | null;
    fee?: number | string | null;
    currency?: string | null;
    [key: string]: unknown;
};

export type PayCodeCostEstimate = {
    currency?: string | null;
    base_fee?: number | string | null;
    components?: Record<string, number | string | null>;
    charges?: PayCodeCostCharge[];
    total?: number | string | null;
    [key: string]: unknown;
};

type UsePayCodeCostEstimateOptions = {
    debounceMs?: number;
};

type UsePayCodeCostEstimate = {
    estimate: Ref<PayCodeCostEstimate | null>;
    estimating: Ref<boolean>;
    estimateError: Ref<string | null>;
    refreshEstimate: () => Promise<void>;
};

export function usePayCodeCostEstimate(
    requestPayload: ComputedRef<Record<string, unknown>>,
    canEstimate: ComputedRef<boolean>,
    options: UsePayCodeCostEstimateOptions = {},
): UsePayCodeCostEstimate {
    const estimate = ref<PayCodeCostEstimate | null>(null);
    const estimating = ref(false);
    const estimateError = ref<string | null>(null);
    const debounceMs = options.debounceMs ?? 500;
    let estimateTimer: ReturnType<typeof setTimeout> | null = null;
    let estimateRequestId = 0;
    let estimateAbortController: AbortController | null = null;

    function clearEstimateTimer(): void {
        if (estimateTimer === null) {
            return;
        }

        clearTimeout(estimateTimer);
        estimateTimer = null;
    }

    function scheduleEstimate(): void {
        clearEstimateTimer();

        if (!canEstimate.value) {
            estimateAbortController?.abort();
            estimate.value = null;
            estimateError.value = null;
            estimating.value = false;

            return;
        }

        estimateTimer = setTimeout(() => {
            void refreshEstimate();
        }, debounceMs);
    }

    async function refreshEstimate(): Promise<void> {
        if (!canEstimate.value) {
            return;
        }

        const requestId = ++estimateRequestId;
        const route = EstimatePayCodeController();

        estimateAbortController?.abort();
        estimateAbortController = new AbortController();
        estimating.value = true;
        estimateError.value = null;

        try {
            const response = await fetch(route.url, {
                method: route.method.toUpperCase(),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(requestPayload.value),
                signal: estimateAbortController.signal,
            });
            const payload = await safeJson(response);

            if (requestId !== estimateRequestId) {
                return;
            }

            if (!response.ok || payload.success === false) {
                throw new Error(estimateFailureMessage(payload, response));
            }

            estimate.value = estimateValue(payload);
        } catch (error) {
            if (
                (error instanceof DOMException &&
                    error.name === 'AbortError') ||
                requestId !== estimateRequestId
            ) {
                return;
            }

            estimateError.value =
                error instanceof Error
                    ? error.message
                    : 'Unable to estimate Pay Code cost.';
        } finally {
            if (requestId === estimateRequestId) {
                estimating.value = false;
            }
        }
    }

    watch(requestPayload, scheduleEstimate, {
        deep: true,
        immediate: true,
    });
    watch(canEstimate, scheduleEstimate);

    onUnmounted(() => {
        clearEstimateTimer();
        estimateAbortController?.abort();
    });

    return {
        estimate,
        estimating,
        estimateError,
        refreshEstimate,
    };
}

async function safeJson(response: Response): Promise<Record<string, unknown>> {
    try {
        const payload: unknown = await response.json();

        return isRecord(payload) ? payload : {};
    } catch {
        return {};
    }
}

function estimateValue(payload: Record<string, unknown>): PayCodeCostEstimate {
    const value = isRecord(payload.data) ? payload.data : payload;

    return value as PayCodeCostEstimate;
}

function estimateFailureMessage(
    payload: Record<string, unknown>,
    response: Response,
): string {
    if (isRecord(payload.errors)) {
        const messages = Object.values(payload.errors)
            .flatMap((message) =>
                Array.isArray(message) ? message : [message],
            )
            .filter(
                (message): message is string =>
                    typeof message === 'string' && message.trim() !== '',
            );

        if (messages.length > 0) {
            return messages.join(' ');
        }
    }

    if (typeof payload.message === 'string' && payload.message.trim() !== '') {
        return payload.message;
    }

    if (typeof payload.error === 'string' && payload.error.trim() !== '') {
        return payload.error;
    }

    return `Unable to estimate Pay Code cost: ${response.status}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
