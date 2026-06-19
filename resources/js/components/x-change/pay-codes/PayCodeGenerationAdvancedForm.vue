<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import PayCodeNamedSlicesBuilder from './PayCodeNamedSlicesBuilder.vue';

interface PayCodeNamedSlice {
    id?: string | null;
    amount?: number | string | null;
    description?: string | null;
    tag?: string | null;
    claim_on?: string | null;
    claim_by?: string | null;
}

interface PayCodeGenerationForm {
    amount?: number | string | null;
    prefix?: string | null;
    mask?: string | null;
    code_length?: number | string | null;

    validation_secret?: string | null;
    validation_mobile?: string | null;

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
    named_slices_enabled?: boolean;
    named_slices?: PayCodeNamedSlice[];
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

function updateBoolean(
    key: keyof PayCodeGenerationForm,
    value: boolean | 'indeterminate',
): void {
    updateField(key, value === true);
}

function updateNamedSlicesEnabled(value: boolean): void {
    const hasSlices =
        Array.isArray(form.value.named_slices) &&
        form.value.named_slices.length > 0;

    form.value = {
        ...form.value,
        named_slices_enabled: value,
        named_slices:
            value && !hasSlices
                ? [
                      {
                          id: 'slice_1',
                          amount: form.value.amount || '',
                          description: 'Whole amount',
                          tag: '',
                          claim_on: '',
                          claim_by: '',
                      },
                  ]
                : form.value.named_slices,
    };
}

function updateNamedSlices(value: PayCodeNamedSlice[]): void {
    updateField('named_slices', value);
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base text-emerald-700"
                >Advanced Options</CardTitle
            >
            <CardDescription>
                Optional controls for code generation, timing, splash screen,
                and feedback.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-6">
            <!-- Code Config -->
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium">Code Configuration</p>
                    <p class="text-xs text-muted-foreground">
                        Customize generated code format.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="prefix">Prefix</Label>
                        <Input
                            id="prefix"
                            placeholder="PAY"
                            :model-value="form.prefix ?? ''"
                            @update:model-value="updateField('prefix', $event)"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="mask">Mask</Label>
                        <Input
                            id="mask"
                            placeholder="****"
                            :model-value="form.mask ?? ''"
                            @update:model-value="updateField('mask', $event)"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="code_length">Length</Label>
                        <Input
                            id="code_length"
                            type="number"
                            min="4"
                            max="32"
                            placeholder="4"
                            :model-value="form.code_length ?? ''"
                            @update:model-value="
                                updateField('code_length', $event)
                            "
                        />
                    </div>
                </div>
            </div>

            <Separator />

            <!-- Cash Validation -->
            <div class="space-y-2">
                <Label for="validation_secret">Secret / PIN</Label>
                <Input
                    id="validation_secret"
                    type="text"
                    placeholder="Optional redemption secret"
                    :model-value="form.validation_secret ?? ''"
                    @update:model-value="
                        updateField('validation_secret', $event)
                    "
                />
                <p class="text-xs text-muted-foreground">
                    Redeemer must provide this secret before the Pay Code can be
                    claimed.
                </p>
            </div>

            <div class="space-y-2">
                <Label for="validation_mobile">Allowed Mobile Number</Label>
                <Input
                    id="validation_mobile"
                    type="tel"
                    placeholder="+639171234567"
                    :model-value="form.validation_mobile ?? ''"
                    @update:model-value="
                        updateField('validation_mobile', $event)
                    "
                />
                <p class="text-xs text-muted-foreground">
                    Only this mobile number can redeem the Pay Code. OTP will be
                    required automatically.
                </p>
            </div>

            <Separator />

            <!-- Timing -->
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium">Timing</p>
                    <p class="text-xs text-muted-foreground">
                        Control when Pay Codes become claimable.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="starts_at">Starts At</Label>
                        <Input
                            id="starts_at"
                            type="datetime-local"
                            :model-value="form.starts_at ?? ''"
                            @update:model-value="
                                updateField('starts_at', $event)
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="expires_at">Expires At</Label>
                        <Input
                            id="expires_at"
                            type="datetime-local"
                            :model-value="form.expires_at ?? ''"
                            @update:model-value="
                                updateField('expires_at', $event)
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="ttl_minutes">TTL Minutes</Label>
                        <Input
                            id="ttl_minutes"
                            type="number"
                            min="1"
                            placeholder="1440"
                            :model-value="form.ttl_minutes ?? ''"
                            @update:model-value="
                                updateField('ttl_minutes', $event)
                            "
                        />
                    </div>
                </div>
            </div>

            <Separator />

            <!-- Splash -->
            <div class="space-y-4">
                <label
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div>
                        <p class="text-sm font-medium">Splash Screen</p>
                        <p class="text-xs text-muted-foreground">
                            Show a splash screen before redemption form.
                        </p>
                    </div>

                    <Checkbox
                        :checked="form.splash_enabled === true"
                        @update:model-value="
                            updateBoolean('splash_enabled', $event)
                        "
                    />
                </label>

                <div
                    v-if="form.splash_enabled"
                    class="space-y-4 rounded-lg border bg-muted/20 p-4"
                >
                    <div class="space-y-2">
                        <Label for="splash_timeout">Timeout Seconds</Label>
                        <Input
                            id="splash_timeout"
                            type="number"
                            min="0"
                            placeholder="5"
                            :model-value="form.splash_timeout ?? ''"
                            @update:model-value="
                                updateField('splash_timeout', $event)
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="splash_title">Splash Title</Label>
                        <Input
                            id="splash_title"
                            placeholder="Welcome"
                            :model-value="form.splash_title ?? ''"
                            @update:model-value="
                                updateField('splash_title', $event)
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="splash_content">Splash Content</Label>
                        <Textarea
                            id="splash_content"
                            rows="4"
                            placeholder="Please prepare your details before continuing."
                            :model-value="form.splash_content ?? ''"
                            @update:model-value="
                                updateField('splash_content', $event)
                            "
                        />
                    </div>
                </div>
            </div>

            <Separator />

            <!-- Feedback -->
            <div class="space-y-3">
                <div>
                    <p class="text-sm font-medium">Feedback</p>
                    <p class="text-xs text-muted-foreground">
                        Optional notification channels after redemption.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        class="flex items-center gap-3 rounded-lg border p-3"
                    >
                        <Checkbox
                            :checked="form.feedback_sms === true"
                            @update:model-value="
                                updateBoolean('feedback_sms', $event)
                            "
                        />
                        <span class="text-sm font-medium">SMS feedback</span>
                    </label>

                    <label
                        class="flex items-center gap-3 rounded-lg border p-3"
                    >
                        <Checkbox
                            :checked="form.feedback_email === true"
                            @update:model-value="
                                updateBoolean('feedback_email', $event)
                            "
                        />
                        <span class="text-sm font-medium">Email feedback</span>
                    </label>
                </div>
            </div>

            <Separator />

            <PayCodeNamedSlicesBuilder
                :enabled="form.named_slices_enabled === true"
                :amount="form.amount"
                :slices="form.named_slices ?? []"
                @update:enabled="updateNamedSlicesEnabled"
                @update:slices="updateNamedSlices"
            />

            <Separator />

            <!-- Metadata -->
            <div class="space-y-2">
                <Label for="metadata">Metadata JSON</Label>
                <Textarea
                    id="metadata"
                    rows="6"
                    placeholder='{"campaign": "demo"}'
                    :model-value="form.metadata ?? ''"
                    @update:model-value="updateField('metadata', $event)"
                />
                <p class="text-xs text-muted-foreground">
                    Optional JSON metadata. Validation will be handled when
                    Create.vue is wired.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
