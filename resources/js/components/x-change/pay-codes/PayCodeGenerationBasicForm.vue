<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';

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
}

interface Props {
    modelValue: PayCodeGenerationForm;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: PayCodeGenerationForm];
}>();

const form = computed({
    get: () => props.modelValue,
    set: (value: PayCodeGenerationForm) => emit('update:modelValue', value),
});

function updateField(key: keyof PayCodeGenerationForm, value: unknown): void {
    form.value = {
        ...form.value,
        [key]: value,
    };
}

function updateBoolean(key: keyof PayCodeGenerationForm, value: boolean | 'indeterminate'): void {
    updateField(key, value === true);
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Basic Pay Code Details</CardTitle>
            <CardDescription>
                Configure the disburseable Pay Code and the basic redemption requirements.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-6">
            <!-- Amount / Quantity -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="amount">Amount</Label>
                    <Input
                        id="amount"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        :model-value="form.amount ?? ''"
                        @update:model-value="updateField('amount', $event)"
                    />
                    <p class="text-xs text-muted-foreground">
                        Amount per Pay Code.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="quantity">Quantity</Label>
                    <Input
                        id="quantity"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="1"
                        :model-value="form.quantity ?? 1"
                        @update:model-value="updateField('quantity', $event)"
                    />
                    <p class="text-xs text-muted-foreground">
                        Number of Pay Codes to generate.
                    </p>
                </div>
            </div>

            <Separator />

            <!-- Required Inputs -->
            <div class="space-y-3">
                <div>
                    <p class="text-sm font-medium">Recipient Inputs</p>
                    <p class="text-xs text-muted-foreground">
                        These fields will be collected during redemption.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-3 rounded-lg border p-3">
                        <Checkbox
                            :checked="form.require_mobile !== false"
                            @update:model-value="updateBoolean('require_mobile', $event)"
                        />
                        <span class="text-sm font-medium">Mobile number</span>
                    </label>

                    <label class="flex items-center gap-3 rounded-lg border p-3">
                        <Checkbox
                            :checked="form.require_bank_account !== false"
                            @update:model-value="updateBoolean('require_bank_account', $event)"
                        />
                        <span class="text-sm font-medium">Bank account</span>
                    </label>
                </div>
            </div>

            <Separator />

            <div class="space-y-3">
                <div>
                    <p class="text-sm font-medium">Personal Information</p>
                    <p class="text-xs text-muted-foreground">
                        These fields will be collected during claim. If KYC is enabled, compatible fields may be auto-filled.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Full name</span>
                        <Checkbox
                            :checked="form.require_name === true"
                            @update:model-value="updateBoolean('require_name', $event)"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Email address</span>
                        <Checkbox
                            :checked="form.require_email === true"
                            @update:model-value="updateBoolean('require_email', $event)"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Birth date</span>
                        <Checkbox
                            :checked="form.require_birth_date === true"
                            @update:model-value="updateBoolean('require_birth_date', $event)"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Address</span>
                        <Checkbox
                            :checked="form.require_address === true"
                            @update:model-value="updateBoolean('require_address', $event)"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Reference code</span>
                        <Checkbox
                            :checked="form.require_reference_code === true"
                            @update:model-value="updateBoolean('require_reference_code', $event)"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Gross monthly income</span>
                        <Checkbox
                            :checked="form.require_gross_monthly_income === true"
                            @update:model-value="updateBoolean('require_gross_monthly_income', $event)"
                        />
                    </label>
                </div>
            </div>

            <Separator />

            <!-- Evidence Requirements -->
            <div class="space-y-3">
                <div>
                    <p class="text-sm font-medium">Verification Requirements</p>
                    <p class="text-xs text-muted-foreground">
                        Optional evidence required before disbursement.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">KYC</span>
                        <Checkbox
                            :checked="form.require_kyc === true"
                            @update:model-value="updateBoolean('require_kyc', $event)"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">OTP</span>
                        <Checkbox
                            :checked="form.require_otp === true"
                            @update:model-value="updateBoolean('require_otp', $event)"
                        />
                        <p v-if="form.validation_mobile" class="text-xs text-muted-foreground">
                            OTP is required because an allowed mobile number is configured.
                        </p>
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Location</span>
                        <Checkbox
                            :checked="form.require_location === true"
                            @update:model-value="updateBoolean('require_location', $event)"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Selfie</span>
                        <Checkbox
                            :checked="form.require_selfie === true"
                            @update:model-value="updateBoolean('require_selfie', $event)"
                        />
                    </label>

                    <label class="flex items-center justify-between rounded-lg border p-3">
                        <span class="text-sm font-medium">Signature</span>
                        <Checkbox
                            :checked="form.require_signature === true"
                            @update:model-value="updateBoolean('require_signature', $event)"
                        />
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Badge v-if="form.require_kyc" variant="secondary">KYC</Badge>
                    <Badge v-if="form.require_otp" variant="secondary">OTP</Badge>
                    <Badge v-if="form.require_location" variant="secondary">Location</Badge>
                    <Badge v-if="form.require_selfie" variant="secondary">Selfie</Badge>
                    <Badge v-if="form.require_signature" variant="secondary">Signature</Badge>
                </div>
            </div>

            <Separator />

            <!-- Rider -->
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium">Rider</p>
                    <p class="text-xs text-muted-foreground">
                        Optional message and redirect shown after successful redemption.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="rider_message">Message</Label>
                    <Textarea
                        id="rider_message"
                        rows="4"
                        placeholder="Thank you. Your Pay Code has been processed."
                        :model-value="form.rider_message ?? ''"
                        @update:model-value="updateField('rider_message', $event)"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="rider_url">Redirect URL</Label>
                    <Input
                        id="rider_url"
                        type="url"
                        placeholder="https://example.com/thank-you"
                        :model-value="form.rider_url ?? ''"
                        @update:model-value="updateField('rider_url', $event)"
                    />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
