import CockpitQuickGenerateFundingPreflightController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitQuickGenerateFundingPreflightController';
import { onUnmounted, ref, watch, type ComputedRef, type Ref } from 'vue';

export type IssuanceFundingPreflight = {
    schema?: string;
    status?: 'ready' | 'unavailable' | string;
    currency?: string;
    pay_code_value?: number | null;
    account_debit?: number | null;
    pricing?: {
        base_fee?: number;
        total?: number;
        components?: Record<string, number>;
        charges?: Array<Record<string, unknown>>;
        commercial_offering_reference?: string | null;
        commercial_offering_version?: number | null;
        commercial_offering_snapshot_hash?: string | null;
        commercial_quote_reference?: string | null;
        [key: string]: unknown;
    } | null;
    funding?: {
        policy_enabled?: boolean;
        basis?: 'full_amount' | 'shortfall' | string;
        available_client_funds_minor?: number | null;
        required_amount_minor?: number;
        reserved_client_funds_minor?: number;
        external_amount_minor?: number;
        execution?: 'direct' | 'payment_required' | string;
        reason?: string;
        [key: string]: unknown;
    } | null;
    expires_at?: string | null;
    [key: string]: unknown;
};

type UseIssuanceFundingPreflight = {
    preflight: Ref<IssuanceFundingPreflight | null>;
    preflighting: Ref<boolean>;
    preflightError: Ref<string | null>;
    refreshPreflight: () => Promise<void>;
};

export function useIssuanceFundingPreflight(
    requestPayload: ComputedRef<Record<string, unknown>>,
    canPreflight: ComputedRef<boolean>,
    options: { debounceMs?: number } = {},
): UseIssuanceFundingPreflight {
    const preflight = ref<IssuanceFundingPreflight | null>(null);
    const preflighting = ref(false);
    const preflightError = ref<string | null>(null);
    const debounceMs = options.debounceMs ?? 500;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let requestId = 0;
    let controller: AbortController | null = null;

    function clear(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function schedule(): void {
        clear();

        if (typeof window === 'undefined' || !canPreflight.value) {
            controller?.abort();
            preflight.value = null;
            preflightError.value = null;
            preflighting.value = false;

            return;
        }

        timer = setTimeout(() => {
            void refreshPreflight();
        }, debounceMs);
    }

    async function refreshPreflight(): Promise<void> {
        if (typeof window === 'undefined' || !canPreflight.value) {
            return;
        }

        const currentRequestId = ++requestId;
        controller?.abort();
        controller = new AbortController();
        preflighting.value = true;
        preflightError.value = null;

        try {
            const route = CockpitQuickGenerateFundingPreflightController();
            const response = await fetch(route.url, {
                method: route.method.toUpperCase(),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(requestPayload.value),
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => ({}));

            if (currentRequestId !== requestId) {
                return;
            }

            if (!response.ok) {
                throw new Error(
                    typeof payload.message === 'string'
                        ? payload.message
                        : `Unable to determine issuance funding: ${response.status}`,
                );
            }

            preflight.value = payload as IssuanceFundingPreflight;
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            if (currentRequestId === requestId) {
                preflight.value = null;
                preflightError.value =
                    error instanceof Error
                        ? error.message
                        : 'Unable to determine issuance funding.';
            }
        } finally {
            if (currentRequestId === requestId) {
                preflighting.value = false;
            }
        }
    }

    watch(requestPayload, schedule, { deep: true, immediate: true });
    watch(canPreflight, schedule);

    onUnmounted(() => {
        clear();
        controller?.abort();
    });

    return { preflight, preflighting, preflightError, refreshPreflight };
}
