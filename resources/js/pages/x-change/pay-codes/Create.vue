<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import XChangeLayout from '@/layouts/x-change/XChangeLayout.vue';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import {
    PayCodeCostEstimateCard,
    PayCodeGenerationAdvancedForm,
    PayCodeGenerationBasicForm,
    PayCodeInstructionPreview,
} from '@/components/x-change/pay-codes';
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

    metadata?: string | null;
}

interface Props {
    issuer_id?: number | string | null;
    issuer?: {
        id?: number | string | null;
    } | null;
}

const props = defineProps<Props>();

const issuerId = computed(() => {
    return props.issuer_id ?? props.issuer?.id ?? null;
});

const routes = useXChangeRoutes();

const activeTab = ref<'basic' | 'advanced'>('basic');
const submitting = ref(false);
const errorMessage = ref<string | null>(null);

const form = ref<PayCodeGenerationForm>({
    amount: '',
    quantity: 1,

    require_mobile: true,
    require_bank_account: true,
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

    metadata: '',
});

const normalizedAmount = computed(() => Number(form.value.amount || 0));
const normalizedQuantity = computed(() => Number(form.value.quantity || 1));

const canSubmit = computed(() => {
    return normalizedAmount.value > 0 && normalizedQuantity.value > 0 && !submitting.value;
});

const generatedInstructions = computed(() => {
    const fields: any[] = [];

    if (form.value.require_mobile !== false) {
        fields.push({
            name: 'mobile',
            type: 'tel',
            label: 'Mobile Number',
            required: true,
            persist: true,
            group: 'recipient',
        });
    }

    if (form.value.require_bank_account !== false) {
        fields.push({
            name: 'settlement_rail',
            type: 'settlement_rail',
            label: 'Settlement Rail',
            required: true,
            group: 'bank_account',
        });

        fields.push({
            name: 'bank_code',
            type: 'bank_account',
            label: 'Bank',
            required: true,
            group: 'bank_account',
        });

        fields.push({
            name: 'account_number',
            type: 'text',
            label: 'Account Number',
            required: true,
            group: 'bank_account',
        });
    }

    const payload: Record<string, any> = {
        amount: normalizedAmount.value,
        quantity: normalizedQuantity.value,
        inputs: {
            fields,
        },
        evidence: {
            kyc: form.value.require_kyc === true,
            otp: form.value.require_otp === true,
            location: form.value.require_location === true,
            selfie: form.value.require_selfie === true,
            signature: form.value.require_signature === true,
        },
        rider: {
            message: form.value.rider_message || null,
            url: form.value.rider_url || null,
        },
        splash: {
            enabled: form.value.splash_enabled === true,
            timeout: Number(form.value.splash_timeout || 0),
            title: form.value.splash_title || null,
            content: form.value.splash_content || null,
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
        feedback: {
            email: null,
            mobile: null,
            webhook: null,
        },
    };

    if (form.value.metadata) {
        try {
            payload.metadata = JSON.parse(String(form.value.metadata));
        } catch {
            payload.metadata = form.value.metadata;
        }
    }

    return payload;
});

const requestPayload = computed(() => {
    const inputFields: string[] = [];

    if (form.value.require_mobile !== false) inputFields.push('mobile');
    if (form.value.require_kyc) inputFields.push('kyc');
    if (form.value.require_location) inputFields.push('location');
    if (form.value.require_otp) inputFields.push('otp');
    if (form.value.require_selfie) inputFields.push('selfie');
    if (form.value.require_signature) inputFields.push('signature');

    return {
        cash: {
            amount: normalizedAmount.value,
            currency: 'PHP',
            validation: {
                secret: null,
                mobile: null,
                payable: null,
                country: null,
                location: null,
                radius: null,
            },
        },

        inputs: {
            fields: inputFields,
        },

        feedback: {
            email: null,
            mobile: null,
            webhook: null,
        },

        rider: {
            message: form.value.rider_message || null,
            url: form.value.rider_url || null,
            splash: form.value.splash_enabled ? form.value.splash_content || null : null,
            splash_timeout: form.value.splash_enabled ? form.value.splash_timeout || null : null,
        },

        count: normalizedQuantity.value,

        prefix: form.value.prefix || null,
        mask: form.value.mask || null,
        ttl: form.value.ttl_minutes || null,
    };
});

function goBack(): void {
    router.visit(routes.payCodes.index());
}

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        errorMessage.value = 'Please enter a valid amount and quantity.';

        return;
    }

    submitting.value = true;
    errorMessage.value = null;

    try {
        const response = await fetch(routes.api.generatePayCode, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(requestPayload.value),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload?.success === false) {
            const firstValidationError = payload?.errors
                ? Object.values(payload.errors).flat().join(' ')
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
        errorMessage.value = error instanceof Error
            ? error.message
            : 'Unable to generate Pay Code.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head title="Generate Pay Code" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
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
                        Create disburseable Pay Codes with optional redemption requirements.
                    </p>
                </div>
            </div>

            <Button :disabled="!canSubmit" @click="submit">
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

        <!-- Main layout -->
        <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
            <!-- Form column -->
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
                        <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                        <PlusCircle v-else class="mr-2 h-4 w-4" />
                        {{ submitting ? 'Generating…' : 'Generate Pay Code' }}
                    </Button>
                </div>
            </div>

            <!-- Side column -->
            <div class="space-y-6">
                <PayCodeCostEstimateCard :form="form" />

                <PayCodeInstructionPreview
                    :form="form"
                    :instructions="generatedInstructions"
                />
            </div>
        </div>
    </div>
</template>
