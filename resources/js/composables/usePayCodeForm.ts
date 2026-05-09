import { reactive, computed, watch, ref, type Ref } from 'vue';
import { usePayCodeApi, type EstimateResult, type PricelistItem } from './usePayCodeApi';

export interface PayCodeFormState {
    amount: number | null;
    currency: string;
    count: number;
    inputFields: string[];
    validationSecret: string;
    validationMobile: string;
    feedbackEmail: string;
    feedbackMobile: string;
    feedbackWebhook: string;
    riderMessage: string;
    riderUrl: string;
    prefix: string;
    mask: string;
    ttl: string;
}

export interface PayCodeFormOptions {
    estimateDebounceMs?: number;
    issuerId?: number | string | null;
}

/**
 * Composable managing pay code form state, validation, payload building,
 * and live cost estimation. Designed for reuse across Inertia and PWA.
 */
export function usePayCodeForm(options: PayCodeFormOptions = {}) {
    const { estimateDebounceMs = 600, issuerId = null } = options;

    const api = usePayCodeApi();

    const form = reactive<PayCodeFormState>({
        amount: null,
        currency: 'PHP',
        count: 1,
        inputFields: [],
        validationSecret: '',
        validationMobile: '',
        feedbackEmail: '',
        feedbackMobile: '',
        feedbackWebhook: '',
        riderMessage: '',
        riderUrl: '',
        prefix: '',
        mask: '',
        ttl: '',
    });

    const estimate = ref<EstimateResult | null>(null);
    const estimateLoading = ref(false);
    const submitting = ref(false);
    const submitError = ref<string | null>(null);

    // Available input field options from pricelist
    const availableInputFields: Ref<PricelistItem[]> = ref([]);

    const isAmountValid = computed(() => form.amount !== null && form.amount > 0);
    const isCountValid = computed(() => form.count >= 1);

    const canSubmit = computed(() => {
        return isAmountValid.value && isCountValid.value && !submitting.value;
    });

    const isValidUrl = (value: string): boolean => {
        try {
            new URL(value);
            return true;
        } catch {
            return false;
        }
    };

    const isValidEmail = (value: string): boolean => {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    };

    /**
     * Build the payload shape required by both estimate and generate APIs.
     * CashInstructionData requires a non-nullable validation object,
     * so we always provide all fields (null when empty).
     * URL and email fields are only included when they pass basic validation
     * to avoid 422 errors during live estimate calls while typing.
     */
    const buildPayload = computed(() => {
        const payload: Record<string, unknown> = {
            cash: {
                amount: form.amount ?? 0,
                currency: form.currency,
                validation: {
                    secret: form.validationSecret || null,
                    mobile: form.validationMobile || null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null,
                },
            },
            inputs: {
                fields: form.inputFields,
            },
            feedback: {
                email: (form.feedbackEmail && isValidEmail(form.feedbackEmail)) ? form.feedbackEmail : null,
                mobile: form.feedbackMobile || null,
                webhook: (form.feedbackWebhook && isValidUrl(form.feedbackWebhook)) ? form.feedbackWebhook : null,
            },
            rider: {
                message: form.riderMessage || null,
                url: (form.riderUrl && isValidUrl(form.riderUrl)) ? form.riderUrl : null,
            },
            count: form.count,
        };

        // Optional fields
        if (form.prefix) payload.prefix = form.prefix;
        if (form.mask) payload.mask = form.mask;
        if (form.ttl) payload.ttl = form.ttl;

        // Include issuer ID so ContextUserResolver can identify the authenticated user
        if (issuerId) payload.issuer_id = issuerId;

        return payload;
    });

    /**
     * Total face value = amount × count
     */
    const totalFaceValue = computed(() => {
        return (form.amount ?? 0) * form.count;
    });

    /**
     * Total estimated cost = face value + fees
     */
    const totalEstimatedCost = computed(() => {
        if (!estimate.value) return totalFaceValue.value;
        return totalFaceValue.value + estimate.value.total;
    });

    // Debounced estimate fetching
    let estimateTimer: ReturnType<typeof setTimeout> | null = null;

    const fetchEstimate = async () => {
        if (!isAmountValid.value) {
            estimate.value = null;
            return;
        }

        estimateLoading.value = true;
        try {
            const result = await api.getEstimate(buildPayload.value);
            estimate.value = result;
        } finally {
            estimateLoading.value = false;
        }
    };

    const debouncedFetchEstimate = () => {
        if (estimateTimer) clearTimeout(estimateTimer);
        estimateTimer = setTimeout(fetchEstimate, estimateDebounceMs);
    };

    // Watch form changes that affect pricing
    watch(
        () => [
            form.amount,
            form.currency,
            form.count,
            form.inputFields.length,
            form.inputFields.join(','),
            form.validationSecret,
            form.validationMobile,
            form.feedbackEmail,
            form.feedbackMobile,
            form.feedbackWebhook,
            form.riderMessage,
            form.riderUrl,
        ],
        debouncedFetchEstimate,
    );

    /**
     * Submit the form to generate pay codes.
     */
    const submit = async (): Promise<{ success: boolean; code?: string }> => {
        if (!canSubmit.value) {
            return { success: false };
        }

        submitting.value = true;
        submitError.value = null;

        try {
            const result = await api.generatePayCode(buildPayload.value);
            if (result) {
                return { success: true, code: result.code };
            }
            // Extract error message from api
            submitError.value = api.error.value?.message ?? 'Generation failed';
            return { success: false };
        } catch (err) {
            submitError.value = err instanceof Error ? err.message : 'An unexpected error occurred';
            return { success: false };
        } finally {
            submitting.value = false;
        }
    };

    /**
     * Toggle an input field on/off.
     */
    const toggleInputField = (field: string) => {
        const index = form.inputFields.indexOf(field);
        if (index >= 0) {
            form.inputFields.splice(index, 1);
        } else {
            form.inputFields.push(field);
        }
    };

    /**
     * Reset form to defaults.
     */
    const reset = () => {
        form.amount = null;
        form.currency = 'PHP';
        form.count = 1;
        form.inputFields = [];
        form.validationSecret = '';
        form.validationMobile = '';
        form.feedbackEmail = '';
        form.feedbackMobile = '';
        form.feedbackWebhook = '';
        form.riderMessage = '';
        form.riderUrl = '';
        form.prefix = '';
        form.mask = '';
        form.ttl = '';
        estimate.value = null;
        submitError.value = null;
    };

    return {
        form,
        estimate,
        estimateLoading,
        submitting,
        submitError,
        availableInputFields,
        isAmountValid,
        isCountValid,
        canSubmit,
        buildPayload,
        totalFaceValue,
        totalEstimatedCost,
        fetchEstimate,
        submit,
        toggleInputField,
        reset,
    };
}
