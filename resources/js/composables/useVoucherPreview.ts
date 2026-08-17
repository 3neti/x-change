import type { InspectResponse } from '@/types/voucher';
import { ref, watch } from 'vue';

interface UseVoucherPreviewOptions {
    debounceMs?: number;
    minCodeLength?: number;
}

type XRayPreviewResponse = {
    success?: boolean;
    message?: string;
    data?: {
        xray?: {
            status?: string | null;
            visible?: boolean | null;
            disclosures?: Array<{
                key?: string | null;
                value?: unknown;
            }>;
            requirements?: unknown[];
            redactions?: unknown[];
            next_actions?: unknown[];
        };
    };
};

function disclosureValue(
    disclosures: XRayPreviewResponse['data'] extends { xray?: infer XRay }
        ? XRay extends { disclosures?: infer Disclosures }
            ? Disclosures
            : never
        : never,
    key: string,
): unknown {
    if (!Array.isArray(disclosures)) {
        return null;
    }

    return disclosures.find((disclosure) => disclosure?.key === key)?.value ?? null;
}

function previewStatusFromXRay(status: string): string {
    if (status === 'claimable' || status === 'partially_claimable') {
        return 'active';
    }

    if (status === 'paid') {
        return 'redeemed';
    }

    return status;
}

function normalizeXRayPreview(
    data: XRayPreviewResponse,
    voucherCode: string,
): InspectResponse | null {
    const xray = data.data?.xray;

    if (!xray || xray.status === 'not_found' || xray.visible === false) {
        return null;
    }

    const status = previewStatusFromXRay(String(xray.status ?? 'claimable'));
    const disclosures = xray.disclosures;
    const amount = disclosureValue(disclosures, 'amount');

    return {
        success: true,
        code: voucherCode,
        status,
        metadata: {},
        info: xray,
        preview: { enabled: true },
        instructions: {
            formatted_amount: typeof amount === 'string' ? amount : undefined,
            requirements: xray.requirements ?? [],
        },
        rider: null,
        redeemed_at: null,
        expired_at: null,
    };
}

export function useVoucherPreview(options: UseVoucherPreviewOptions = {}) {
    const debounceMs = options.debounceMs ?? 500;
    const minCodeLength = options.minCodeLength ?? 4;

    const code = ref('');
    const loading = ref(false);
    const error = ref<string | null>(null);
    const voucherData = ref<InspectResponse | null>(null);
    const showPreview = ref(false);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let abortController: AbortController | null = null;

    async function fetchVoucher(voucherCode: string) {
        // Cancel previous request
        if (abortController) {
            abortController.abort();
        }

        // Reset state
        error.value = null;
        voucherData.value = null;
        showPreview.value = true;
        loading.value = true;

        // Create new abort controller
        abortController = new AbortController();

        try {
            const response = await fetch('/api/x/v1/pay-codes/x-ray', {
                method: 'POST',
                signal: abortController.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    code: voucherCode,
                    channel: 'claim',
                }),
            });

            const data = (await response.json()) as XRayPreviewResponse;

            if (response.ok && data.success !== false) {
                const preview = normalizeXRayPreview(data, voucherCode);

                if (preview) {
                    voucherData.value = preview;
                    error.value = null;

                    return;
                }

                error.value = data.message || 'Voucher not found';
                voucherData.value = null;
            } else {
                error.value = data.message || 'Voucher not found';
                voucherData.value = null;
            }
        } catch (err: any) {
            if (err.name === 'AbortError') {
                // Request was cancelled, ignore
                return;
            }

            error.value = 'Network error. Please try again.';
            voucherData.value = null;
        } finally {
            loading.value = false;
        }
    }

    function debouncedFetch(newCode: string) {
        // Clear previous timer
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        // Hide preview immediately when code changes
        showPreview.value = false;

        // Trim and uppercase
        const trimmedCode = newCode.trim().toUpperCase();

        // Check minimum length
        if (trimmedCode.length < minCodeLength) {
            return;
        }

        // Start new debounce timer
        debounceTimer = setTimeout(() => {
            fetchVoucher(trimmedCode);
        }, debounceMs);
    }

    // Watch code changes
    watch(code, (newCode, oldCode) => {
        // Auto-uppercase (only if different to avoid infinite loop)
        const uppercased = newCode.toUpperCase();
        if (uppercased !== newCode) {
            code.value = uppercased;
            return; // The watch will trigger again with uppercase value
        }

        debouncedFetch(newCode);
    });

    function reset() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        if (abortController) {
            abortController.abort();
        }
        code.value = '';
        loading.value = false;
        error.value = null;
        voucherData.value = null;
        showPreview.value = false;
    }

    function hidePreview() {
        showPreview.value = false;
    }

    return {
        code,
        loading,
        error,
        voucherData,
        showPreview,
        reset,
        hidePreview,
    };
}
