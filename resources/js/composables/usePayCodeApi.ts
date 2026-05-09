import { ref } from 'vue';
import { useXChangeRoutes } from './useXChangeRoutes';

export interface PricelistItem {
    code: string | null;
    name: string | null;
    category: string | null;
    amount: number | null;
    currency: string | null;
    active: boolean | null;
}

export interface Pricelist {
    name: string | null;
    currency: string | null;
    items: PricelistItem[];
}

export interface EstimateCharge {
    index: string;
    label: string;
    unit_price: number;
    quantity: number;
    price: number;
    currency: string;
}

export interface EstimateResult {
    currency: string;
    base_fee: number;
    components: Record<string, number>;
    total: number;
    charges: EstimateCharge[];
}

export interface GeneratePayCodeResult {
    voucher_id: number;
    code: string;
    amount: number;
    currency: string;
    [key: string]: unknown;
}

export interface PayCodeApiError {
    message: string;
    status?: number;
    errors?: Record<string, string[]>;
}

/**
 * Generate a UUID v4, with fallback for insecure (non-HTTPS) contexts.
 */
function generateUUID(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback for insecure contexts (HTTP)
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

/**
 * Composable for x-change Pay Code API interactions.
 * Designed to be reusable across Inertia pages and future PWA.
 */
export function usePayCodeApi() {
    const loading = ref(false);
    const error = ref<PayCodeApiError | null>(null);

    const fetchJson = async (url: string, options?: RequestInit) => {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            ...options,
        });

        const json = await response.json();

        if (!response.ok) {
            const apiError: PayCodeApiError = {
                message: json.message ?? `HTTP ${response.status}: ${response.statusText}`,
                status: response.status,
                errors: json.errors ?? undefined,
            };
            throw apiError;
        }

        return json;
    };

    const routes = useXChangeRoutes();

    const fetchPricelist = async (): Promise<Pricelist | null> => {
        loading.value = true;
        error.value = null;

        try {
            const json = await fetchJson(routes.api.pricelist);
            return json.data as Pricelist;
        } catch (err) {
            error.value = normalizeError(err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    const getEstimate = async (payload: Record<string, unknown>): Promise<EstimateResult | null> => {
        loading.value = true;
        error.value = null;

        try {
            const json = await fetchJson(routes.api.estimatePayCode, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            return json.data as EstimateResult;
        } catch (err) {
            error.value = normalizeError(err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    const generatePayCode = async (payload: Record<string, unknown>): Promise<GeneratePayCodeResult | null> => {
        loading.value = true;
        error.value = null;

        try {
            const idempotencyKey = generateUUID();
            const json = await fetchJson(routes.api.generatePayCode, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Idempotency-Key': idempotencyKey,
                },
                body: JSON.stringify(payload),
            });
            return json.data as GeneratePayCodeResult;
        } catch (err) {
            error.value = normalizeError(err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    const getWalletBalance = async (): Promise<{ balance: number; currency: string } | null> => {
        try {
            const json = await fetchJson(routes.api.dashboardStats);
            const stats = json.data?.stats;
            if (stats?.disbursements) {
                return {
                    balance: stats.disbursements.total_disbursed ?? 0,
                    currency: stats.disbursements.currency ?? 'PHP',
                };
            }
            return null;
        } catch {
            return null;
        }
    };

    return {
        loading,
        error,
        fetchPricelist,
        getEstimate,
        generatePayCode,
        getWalletBalance,
    };
}

function normalizeError(err: unknown): PayCodeApiError {
    if (err && typeof err === 'object' && 'message' in err) {
        return err as PayCodeApiError;
    }
    if (err instanceof Error) {
        return { message: err.message };
    }
    return { message: 'An unexpected error occurred' };
}
