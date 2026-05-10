import { ref, watch } from 'vue';
import type { InspectResponse } from '@/types/voucher';

interface UseVoucherPreviewOptions {
    debounceMs?: number;
    minCodeLength?: number;
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
            const response = await fetch(`/api/x/v1/vouchers/code/${voucherCode}`, {
                signal: abortController.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (response.ok && data.success !== false) {
                // Normalize x-change lifecycle API response to match preview shape
                const d = data.data ?? data;
                voucherData.value = {
                    success: true,
                    code: d.code ?? voucherCode,
                    status: d.status ?? 'active',
                    metadata: d.metadata ?? {},
                    info: d,
                    instructions: d.instructions ?? null,
                    redeemed_at: d.redeemed_at ?? null,
                    expired_at: d.expires_at ?? null,
                };
                error.value = null;
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
