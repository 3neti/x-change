<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import VoucherInstructionsDisplay from '@/components/x-change/VoucherInstructionsDisplay.vue';
import VoucherMetadataDisplay from '@/components/x-change/VoucherMetadataDisplay.vue';
import VoucherStatusStamp from '@/components/x-change/VoucherStatusStamp.vue';
import RiderRuntimeSequencer from '@/components/x-rider/RiderRuntimeSequencer.vue';
import type { RawRiderStage } from '@/components/x-rider/types';
import { initializeTheme } from '@/composables/useTheme';
import { useVoucherPreview } from '@/composables/useVoucherPreview';
import { useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { resolveClaimWidgetExperienceStages } from '@/components/x-change/claimWidgetExperienceStages';
import { resolveLegacyRiderStages } from '@/components/x-change/claimWidgetLegacyStages';
import { submitLegacyClaimStart } from '@/components/x-change/claimWidgetLegacySubmit';
import { resolveClaimWidgetPreviewViewModel } from '@/components/x-change/claimWidgetPreviewViewModel';
import { resolveClaimWidgetSubmitViewModel } from '@/components/x-change/claimWidgetSubmitViewModel';
import { isReturningRedeemerFromStorage } from '@/components/x-change/claimWidgetVoucherState';
import { useCompiledClaimForm } from '@/components/x-change/useCompiledClaimForm';
import FormFlowRenderer from '@/components/x-change/FormFlowRenderer.vue';
import { resolveClaimWidgetFormFlowSectionViewModel } from '@/components/x-change/claimWidgetFormFlowSectionViewModel';
import { resolveClaimWidgetPreviewMode } from '@/components/x-change/claimWidgetPreviewMode';

initializeTheme();

interface Props {
    initialCode?: string | null;
    claimExperience?: Record<string, unknown> | null;
    compiledFormSubmitted?: boolean;
    compiledFormSubmitError?: string | null;
}

const props = defineProps<Props>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const form = useForm({
    code: props.initialCode || '',
});

const {
    code,
    loading,
    error,
    voucherData,
    showPreview
} = useVoucherPreview({ debounceMs: 500, minCodeLength: 4 });

if (props.initialCode) {
    code.value = props.initialCode;
}

onMounted(() => {
    if (props.initialCode && submitButton.value) {
        const buttonEl = submitButton.value.$el as HTMLElement;
        buttonEl?.focus();
    }
});

const voucherInput = ref<HTMLInputElement | null>(null);
const submitButton = ref<HTMLButtonElement | null>(null);

const isReturningRedeemer = computed(() =>
    isReturningRedeemerFromStorage()
);

const riderStages = computed<RawRiderStage[]>(() =>
    resolveLegacyRiderStages(
        voucherData.value as Record<string, any> | null | undefined,
    )
);

function submit() {
    submitLegacyClaimStart(form, code.value);
}

const experienceStages = computed(() =>
    resolveClaimWidgetExperienceStages({
        claimExperience: props.claimExperience,
        legacyStages: riderStages.value,
    })
);

const preClaimVisualStages = computed<RawRiderStage[]>(() =>
    experienceStages.value.preClaimVisualStages
);

const previewViewModel = computed(() =>
    resolveClaimWidgetPreviewViewModel({
        voucherData: voucherData.value,
        preClaimVisualStages: preClaimVisualStages.value,
    })
);

const previewMode = computed(() =>
    resolveClaimWidgetPreviewMode({
        loading: loading.value,
        error: error.value,
        voucherData: voucherData.value,
        isNonActive: previewViewModel.value.isNonActive,
    })
);

const runtimeStages = computed<RawRiderStage[]>(() =>
    experienceStages.value.runtimeStages
);

const redirectStages = computed<RawRiderStage[]>(() =>
    experienceStages.value.redirectStages
);

const emit = defineEmits<{
    'submit:compiled-form': [payload: {
        code: string;
        values: Record<string, unknown>;
    }];
    'update:compiled-form-values': [values: Record<string, unknown>];
}>();

const compiledForm = useCompiledClaimForm({
    initialCode: props.initialCode,
    claimExperience: computed(() => props.claimExperience),
    submitted: computed(() => props.compiledFormSubmitted),
    submitError: computed(() => props.compiledFormSubmitError),
    emitSubmit: (payload) => emit('submit:compiled-form', payload),
    emitUpdateValues: (values) => emit('update:compiled-form-values', values),
});

const submitViewModel = computed(() =>
    resolveClaimWidgetSubmitViewModel({
        hasCompiledForm: Boolean(compiledForm.normalizedFlow.value),
        compiledFormValid: compiledForm.isValid.value,
        processing: form.processing,
    })
);

const formFlowSection = computed(() =>
    resolveClaimWidgetFormFlowSectionViewModel({
        hasCompiledFlow: Boolean(compiledForm.normalizedFlow.value),
        usesLegacyFlow: compiledForm.usesLegacyFlow.value,
    })
);

function submitClaim(): void {
    if (compiledForm.normalizedFlow.value) {
        compiledForm.submit();

        return;
    }

    submit();
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Logo and App Name -->
        <div v-if="!previewViewModel.isNonActive" class="flex flex-col items-center gap-2">
            <AppLogoIcon class="h-20 w-auto" />
        </div>

        <!-- Title -->
        <div v-if="!previewViewModel.isNonActive" class="space-y-2 text-center">
            <h1 class="text-xl font-medium">Claim Pay Code</h1>
        </div>

        <!-- Form -->
        <form v-if="!previewViewModel.isNonActive" @submit.prevent="submitClaim" class="space-y-6">
            <div class="flex flex-col gap-2">
                <Label for="code">Pay Code</Label>
                <Input
                    id="code"
                    v-model="code"
                    placeholder="Enter pay code"
                    required
                    autofocus
                    ref="voucherInput"
                    class="text-center text-lg tracking-wider"
                />
                <InputError :message="errors.code" class="mt-1" />
            </div>

            <Button
                ref="submitButton"
                type="submit"
                class="w-full rounded-full"
                data-testid="claim-widget-submit-button"
                :disabled="submitViewModel.disabled"
            >
                {{ submitViewModel.label }}
            </Button>
        </form>

        <!-- Voucher Preview -->
        <div v-if="showPreview" :class="previewViewModel.isNonActive ? '' : 'mt-6'">
            <!-- Loading State -->
            <div v-if="previewMode === 'loading'" class="flex items-center justify-center gap-2 py-8 text-muted-foreground">
                <Spinner class="h-5 w-5" />
                <span>Checking voucher...</span>
            </div>

            <!-- Error State -->
            <Alert v-else-if="previewMode === 'error'" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ error }}
                </AlertDescription>
            </Alert>

            <!-- Preview disabled notice -->
            <Alert v-else-if="previewMode === 'preview-disabled'">
                <AlertDescription>
                    {{ voucherData.preview.message || 'Preview disabled by issuer.' }}
                </AlertDescription>
            </Alert>

            <!-- Non-Active State: Stamp + Rider Content -->
            <div v-else-if="previewMode === 'non-active'" class="space-y-2.5">
                <!-- Status Stamp -->
                <VoucherStatusStamp
                    :status="voucherData.status as 'redeemed' | 'expired'"
                    :status-date="previewViewModel.statusDate"
                    :voucher-code="voucherData.code"
                    :formatted-amount="voucherData.instructions?.formatted_amount"
                />

                <!-- Rider Content (only for returning redeemers) -->
                <template v-if="isReturningRedeemer">
                    <!-- Rider Message -->
                    <Card v-if="voucherData.instructions?.rider?.message">
                        <CardContent class="pt-3 pb-3">
                            <p class="text-sm font-medium text-foreground leading-relaxed">
                                {{ voucherData.instructions.rider.message }}
                            </p>
                        </CardContent>
                    </Card>
                </template>
            </div>

            <!-- Active State: Tabbed preview -->
            <div v-else-if="previewMode === 'active'">
                <!-- Rider pre-claim content from compiled/legacy rider intro -->
                <Card
                    v-if="previewViewModel.hasPreClaimContent"
                    data-testid="pre-claim-rider-region"
                    class="mb-4 border-primary/10 bg-primary/5"
                >
                    <CardContent class="pt-4 pb-4">
                        <RiderRuntimeSequencer :stages="preClaimVisualStages" />
                    </CardContent>
                </Card>

                <!-- Preview Message (if provided by issuer) -->
                <Alert v-if="voucherData.preview && voucherData.preview.message" class="mb-4" variant="default">
                    <AlertDescription>
                        <strong class="font-semibold">Note from issuer:</strong> {{ voucherData.preview.message }}
                    </AlertDescription>
                </Alert>

                <Tabs default-value="instructions">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="instructions">Instructions</TabsTrigger>
                        <TabsTrigger value="system-info">System Info</TabsTrigger>
                    </TabsList>

                    <TabsContent value="instructions" class="mt-4">
                        <VoucherInstructionsDisplay
                            v-if="voucherData.instructions && typeof voucherData.instructions === 'object'"
                            :instructions="voucherData.instructions"
                            :voucher-status="voucherData.status"
                        />
                        <Alert v-else>
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                No instruction details available.
                            </AlertDescription>
                        </Alert>
                    </TabsContent>

                    <TabsContent value="system-info" class="mt-4 space-y-4">
                        <VoucherMetadataDisplay
                            :metadata="voucherData.metadata"
                            :show-all-fields="true"
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <!-- Runtime Sequencer -->
        <div
            v-if="runtimeStages.length > 0"
            data-testid="claim-widget-runtime-region"
        >
            <RiderRuntimeSequencer :stages="runtimeStages" />
        </div>

        <div
            v-if="redirectStages.length > 0"
            data-testid="claim-widget-redirect-region"
        >
            <RiderRuntimeSequencer :stages="redirectStages" />
        </div>

        <div
            v-if="formFlowSection.visible"
            data-testid="claim-widget-form-flow-boundary-region"
            :class="formFlowSection.className"
        >
            <Card
                v-if="formFlowSection.compiledVisible"
                data-testid="compiled-form-flow-visible-region"
                class="border-primary/10 bg-background"
            >
                <CardContent class="pt-4 pb-4 space-y-4">
                    <div class="space-y-1 text-center">
                        <h2 class="text-base font-medium">Claim Information</h2>
                        <p class="text-sm text-muted-foreground">
                            Please complete the required details to continue.
                        </p>
                    </div>

                    <FormFlowRenderer
                        :form-flow="compiledForm.normalizedFlow.value"
                        @update:values="compiledForm.updateValues"
                    />
                </CardContent>
            </Card>

            <div
                v-if="compiledFormSubmitError"
                data-testid="claim-widget-submit-error"
            >
                {{ compiledFormSubmitError }}
            </div>
        </div>
    </div>
</template>
