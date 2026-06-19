<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import XChangeLayout from '@/layouts/x-change/XChangeLayout.vue';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    PayCodeCostEstimateCard,
    PayCodeGenerationAdvancedForm,
    PayCodeGenerationBasicForm,
    PayCodeInstructionPreview,
} from '@/components/x-change/pay-codes';
import ProvisioningSetup from '../../../components/x-change/ProvisioningSetup.vue';
import {
    normalizeProvisioningRequirement,
    type ProvisioningRequirement,
} from '../../../components/x-change/provisioningRequirement';
import type { BalanceOverview } from '../../../components/x-change/BalanceOverviewCards.vue';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';
import { AlertCircle, ArrowLeft, Loader2, PlusCircle } from 'lucide-vue-next';

defineOptions({
    layout: XChangeLayout,
});

interface PayCodeGenerationForm {
    amount?: number | string | null;
    quantity?: number | string | null;

    require_mobile?: boolean;
    require_bank_account?: boolean;

    require_name?: boolean;
    require_email?: boolean;
    require_birth_date?: boolean;
    require_address?: boolean;
    require_reference_code?: boolean;
    require_gross_monthly_income?: boolean;

    require_kyc?: boolean;
    require_otp?: boolean;
    require_location?: boolean;
    require_selfie?: boolean;
    require_signature?: boolean;

    rider_message?: string | null;
    rider_url?: string | null;

    prefix?: string | null;
    mask?: string | null;
    code_length?: number | string | null;

    starts_at?: string | null;
    expires_at?: string | null;
    ttl_minutes?: number | string | null;

    splash_enabled?: boolean;
    splash_timeout?: number | string | null;
    splash_title?: string | null;
    splash_content?: string | null;

    feedback_sms?: boolean;
    feedback_email?: boolean;

    validation_secret?: string | null;
    validation_mobile?: string | null;

    metadata?: string | null;
    named_slices_enabled?: boolean;
    named_slices?: PayCodeNamedSlice[];
}

interface PayCodeNamedSlice {
    id?: string | null;
    amount?: number | string | null;
    description?: string | null;
    tag?: string | null;
    claim_on?: string | null;
    claim_by?: string | null;
}

const props = defineProps<{
    provisioning_requirement?: ProvisioningRequirement | null;
    balance_overview?: BalanceOverview | null;
}>();

const routes = useXChangeRoutes();

const activeTab = ref<'basic' | 'advanced'>('basic');
const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const provisioningRequirement = ref<ProvisioningRequirement | null>(
    normalizeProvisioningRequirement(props.provisioning_requirement),
);
const hasProvisioningRequirement = computed(
    () => provisioningRequirement.value !== null,
);

const estimate = ref<Record<string, any> | null>(null);
const estimating = ref(false);
const estimateError = ref<string | null>(null);
let estimateTimer: ReturnType<typeof setTimeout> | null = null;
let estimateRequestId = 0;
let estimateAbortController: AbortController | null = null;

const form = ref<PayCodeGenerationForm>({
    amount: '',
    quantity: 1,

    require_mobile: true,
    require_bank_account: true,

    require_name: false,
    require_email: false,
    require_birth_date: false,
    require_address: false,
    require_reference_code: false,
    require_gross_monthly_income: false,

    require_kyc: false,
    require_otp: false,
    require_location: false,
    require_selfie: false,
    require_signature: false,

    rider_message: '',
    rider_url: '',

    prefix: '',
    mask: '',
    code_length: '',

    starts_at: '',
    expires_at: '',
    ttl_minutes: '',

    splash_enabled: true,
    splash_timeout: 5,
    splash_title: '',
    splash_content: '',

    feedback_sms: false,
    feedback_email: false,

    validation_secret: '',
    validation_mobile: '',

    metadata: '',
    named_slices_enabled: false,
    named_slices: [],
});

const normalizedAmount = computed(() => Number(form.value.amount || 0));
const normalizedQuantity = computed(() => Number(form.value.quantity || 1));
const normalizedValidationMobile = computed(() => {
    const value = String(form.value.validation_mobile || '').trim();

    return value === '' ? null : value;
});

const normalizedNamedSlices = computed(() => {
    if (form.value.named_slices_enabled !== true) {
        return [];
    }

    const source =
        Array.isArray(form.value.named_slices) &&
        form.value.named_slices.length > 0
            ? form.value.named_slices
            : [
                  {
                      id: 'slice_1',
                      amount: normalizedAmount.value,
                      description: 'Whole amount',
                      tag: '',
                      claim_on: '',
                      claim_by: '',
                  },
              ];

    return source.map((slice, index) => ({
        id: slice.id || `slice_${index + 1}`,
        amount: Number(slice.amount || 0),
        description:
            String(slice.description || '').trim() ||
            (source.length === 1 ? 'Whole amount' : `Slice ${index + 1}`),
        tag: String(slice.tag || '').trim() || null,
        claim_on: String(slice.claim_on || '').trim() || null,
        claim_by: String(slice.claim_by || '').trim() || null,
    }));
});

const namedSliceTotal = computed(() =>
    normalizedNamedSlices.value.reduce(
        (total, slice) => total + Number(slice.amount || 0),
        0,
    ),
);

const namedSliceValidationMessage = computed(() => {
    if (form.value.named_slices_enabled !== true) {
        return null;
    }

    if (normalizedNamedSlices.value.length === 0) {
        return 'Add at least one named slice.';
    }

    if (normalizedNamedSlices.value.some((slice) => slice.amount <= 0)) {
        return 'Each named slice must have an amount greater than zero.';
    }

    if (Math.abs(namedSliceTotal.value - normalizedAmount.value) >= 0.01) {
        return 'Named slice amounts must equal the Pay Code amount.';
    }

    return null;
});

const metadataPayload = computed<Record<string, unknown>>(() => {
    const raw = String(form.value.metadata || '').trim();

    if (raw === '') {
        return {};
    }

    try {
        const parsed = JSON.parse(raw);

        return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
            ? parsed
            : {};
    } catch {
        return {};
    }
});

const canSubmit = computed(() => {
    return (
        normalizedAmount.value > 0 &&
        normalizedQuantity.value > 0 &&
        namedSliceValidationMessage.value === null &&
        !submitting.value
    );
});

const voucherInputFields = computed<string[]>(() => {
    const fields: string[] = [];

    if (form.value.require_name) fields.push('name');
    if (form.value.require_email) fields.push('email');
    if (form.value.require_birth_date) fields.push('birth_date');
    if (form.value.require_address) fields.push('address');
    if (form.value.require_reference_code) fields.push('reference_code');
    if (form.value.require_gross_monthly_income)
        fields.push('gross_monthly_income');

    if (form.value.require_kyc) fields.push('kyc');
    if (form.value.require_location) fields.push('location');
    if (form.value.require_otp || normalizedValidationMobile.value)
        fields.push('otp');
    if (form.value.require_selfie) fields.push('selfie');
    if (form.value.require_signature) fields.push('signature');

    return fields;
});

const generatedInstructions = computed(() => {
    return {
        amount: normalizedAmount.value,
        quantity: normalizedQuantity.value,

        cash: {
            validation: {
                secret: form.value.validation_secret ? 'configured' : null,
                mobile: normalizedValidationMobile.value,
            },
            slices: normalizedNamedSlices.value,
        },

        inputs: {
            fields: voucherInputFields.value,
        },

        evidence: {
            kyc: form.value.require_kyc === true,
            otp: form.value.require_otp === true,
            location: form.value.require_location === true,
            selfie: form.value.require_selfie === true,
            signature: form.value.require_signature === true,
        },

        redemption_form: {
            collect_mobile: form.value.require_mobile !== false,
            collect_bank_account: form.value.require_bank_account !== false,
        },

        feedback: {
            email: null,
            mobile: null,
            webhook: null,
        },

        rider: {
            message: form.value.rider_message || null,
            url: form.value.rider_url || null,
            splash: form.value.splash_enabled
                ? form.value.splash_content || null
                : null,
            splash_timeout: form.value.splash_enabled
                ? form.value.splash_timeout || null
                : null,
        },

        code: {
            prefix: form.value.prefix || null,
            mask: form.value.mask || null,
            length: form.value.code_length || null,
        },

        timing: {
            starts_at: form.value.starts_at || null,
            expires_at: form.value.expires_at || null,
            ttl_minutes: form.value.ttl_minutes || null,
        },
    };
});

const requestPayload = computed(() => {
    const cash: Record<string, unknown> = {
        amount: normalizedAmount.value,
        currency: 'PHP',
        validation: {
            secret: form.value.validation_secret || null,
            mobile: normalizedValidationMobile.value,
            payable: null,
            country: null,
            location: null,
            radius: null,
        },
    };

    const metadata = {
        ...metadataPayload.value,
    };

    if (normalizedNamedSlices.value.length > 0) {
        cash.slice_mode = 'open';
        cash.max_slices = normalizedNamedSlices.value.length;
        cash.min_withdrawal = Math.min(
            ...normalizedNamedSlices.value.map((slice) => slice.amount),
        );

        Object.assign(metadata, {
            slices: normalizedNamedSlices.value,
            slice_policy: {
                mode: 'named',
                selection: 'one_or_many',
                enforced: true,
            },
        });
    }

    return {
        cash,

        inputs: {
            fields: voucherInputFields.value,
        },

        feedback: {
            email: null,
            mobile: null,
            webhook: null,
        },

        rider: {
            message: form.value.rider_message || null,
            url: form.value.rider_url || null,
            splash: form.value.splash_enabled
                ? form.value.splash_content || null
                : null,
            splash_timeout: form.value.splash_enabled
                ? form.value.splash_timeout || null
                : null,
        },

        count: normalizedQuantity.value,
        prefix: form.value.prefix || null,
        mask: form.value.mask || null,
        ttl: form.value.ttl_minutes || null,
        metadata,
    };
});

const canEstimate = computed(() => {
    return normalizedAmount.value > 0 && normalizedQuantity.value > 0;
});

function requestPayloadWithProvisioningReference(): Record<string, unknown> {
    const payload = {
        ...requestPayload.value,
    } as Record<string, unknown>;
    const reference = provisioningRequirement.value?.onboarding?.reference;

    if (typeof reference === 'string' && reference.trim() !== '') {
        payload.onboarding = {
            reference,
        };
        payload.metadata = {
            ...(typeof payload.metadata === 'object' &&
            payload.metadata !== null
                ? payload.metadata
                : {}),
            onboarding_reference: reference,
        };
    }

    return payload;
}

function scheduleEstimate(): void {
    if (estimateTimer) {
        clearTimeout(estimateTimer);
    }

    if (!canEstimate.value) {
        estimate.value = null;
        estimateError.value = null;
        estimating.value = false;

        return;
    }

    estimateTimer = setTimeout(() => {
        void fetchEstimate();
    }, 500);
}

async function fetchEstimate(): Promise<void> {
    const requestId = ++estimateRequestId;

    estimateAbortController?.abort();
    estimateAbortController = new AbortController();

    estimating.value = true;
    estimateError.value = null;

    try {
        const response = await fetch(routes.api.estimatePayCode, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(requestPayload.value),
            signal: estimateAbortController.signal,
        });

        const payload = await response.json().catch(() => ({}));

        if (requestId !== estimateRequestId) {
            return;
        }

        if (!response.ok || payload?.success === false) {
            const firstValidationError = payload?.errors
                ? Object.values(payload.errors).flat().join(' ')
                : null;

            throw new Error(
                firstValidationError ||
                    payload?.message ||
                    payload?.error ||
                    `Unable to estimate Pay Code cost: ${response.status}`,
            );
        }

        estimate.value = payload?.data ?? payload;
    } catch (error) {
        if ((error as any)?.name === 'AbortError') {
            return;
        }

        if (requestId !== estimateRequestId) {
            return;
        }

        // Keep the last good estimate to avoid flicker.
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

watch(
    requestPayload,
    () => {
        scheduleEstimate();
    },
    {
        deep: true,
        immediate: true,
    },
);

onUnmounted(() => {
    if (estimateTimer) {
        clearTimeout(estimateTimer);
    }

    estimateAbortController?.abort();
});

function goBack(): void {
    router.visit(routes.payCodes.index());
}

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        errorMessage.value =
            namedSliceValidationMessage.value ||
            'Please enter a valid amount and quantity.';
        return;
    }

    const payloadToSubmit = requestPayloadWithProvisioningReference();

    submitting.value = true;
    errorMessage.value = null;
    provisioningRequirement.value = null;

    try {
        const response = await fetch(routes.api.generatePayCode, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payloadToSubmit),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload?.success === false) {
            provisioningRequirement.value = normalizeProvisioningRequirement(
                payload?.errors?.provisioning,
            );
            const firstValidationError = payload?.errors
                ? Object.entries(payload.errors)
                      .filter(([key]) => key !== 'provisioning')
                      .flatMap(([, value]) =>
                          Array.isArray(value) ? value : [],
                      )
                      .join(' ')
                : null;

            throw new Error(
                firstValidationError ||
                    payload?.message ||
                    payload?.error ||
                    `Failed to generate Pay Code: ${response.status}`,
            );
        }

        const code =
            payload?.data?.code ||
            payload?.code ||
            payload?.data?.voucher?.code ||
            payload?.voucher?.code ||
            null;

        if (code) {
            router.visit(routes.payCodes.show(code));
            return;
        }

        router.visit(routes.payCodes.index());
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to generate Pay Code.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head title="Generate Pay Code" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 sm:p-6 lg:p-8"
    >
        <div
            class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
        >
            <div class="space-y-2">
                <Button variant="ghost" class="-ml-3" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Pay Codes
                </Button>

                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Generate Pay Code
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Create disburseable Pay Codes with optional redemption
                        requirements.
                    </p>
                </div>
            </div>

            <Button
                v-if="!hasProvisioningRequirement"
                :disabled="!canSubmit"
                @click="submit"
            >
                <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                <PlusCircle v-else class="mr-2 h-4 w-4" />
                {{ submitting ? 'Generating…' : 'Generate' }}
            </Button>
        </div>

        <Alert v-if="errorMessage" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                {{ errorMessage }}
            </AlertDescription>
        </Alert>

        <ProvisioningSetup
            :requirement="provisioningRequirement"
            resume-label="Continue setup"
            @resume="submit"
        />

        <div
            v-if="!hasProvisioningRequirement"
            class="grid gap-6 lg:grid-cols-[1fr_380px]"
        >
            <div class="space-y-6">
                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="basic">Basic</TabsTrigger>
                        <TabsTrigger value="advanced">Advanced</TabsTrigger>
                    </TabsList>

                    <TabsContent value="basic" class="mt-6">
                        <PayCodeGenerationBasicForm v-model="form" />
                    </TabsContent>

                    <TabsContent value="advanced" class="mt-6">
                        <PayCodeGenerationAdvancedForm v-model="form" />
                    </TabsContent>
                </Tabs>

                <div class="flex justify-end">
                    <Button :disabled="!canSubmit" @click="submit">
                        <Loader2
                            v-if="submitting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <PlusCircle v-else class="mr-2 h-4 w-4" />
                        {{ submitting ? 'Generating…' : 'Generate Pay Code' }}
                    </Button>
                </div>
            </div>

            <div class="space-y-6">
                <PayCodeCostEstimateCard
                    :form="form"
                    :estimate="estimate"
                    :loading="estimating"
                    :error="estimateError"
                    :balance-overview="props.balance_overview ?? null"
                />

                <PayCodeInstructionPreview
                    :form="form"
                    :instructions="generatedInstructions"
                />
            </div>
        </div>
    </div>
</template>
