<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import VoucherInstructionsDisplay from '@/components/x-change/VoucherInstructionsDisplay.vue';
import VoucherMetadataDisplay from '@/components/x-change/VoucherMetadataDisplay.vue';
import VoucherStatusStamp from '@/components/x-change/VoucherStatusStamp.vue';
import { AlertCircle } from 'lucide-vue-next';
import { useVoucherPreview } from '@/composables/useVoucherPreview';
import { initializeTheme } from '@/composables/useTheme';
import RiderRuntimeSequencer from '@/components/x-rider/RiderRuntimeSequencer.vue';
import type { RawRiderStage } from '@/components/x-rider/types';
import FormFlowRenderer from '@/components/x-change/FormFlowRenderer.vue';
import {
    normalizeCompiledFormFlowPhase,
    resolveCompiledFormFlowPhase,
} from '@/components/x-change/compiledFormFlow';
import { activeClaimExperiencePhase } from '@/components/x-change/claimExperiencePhases';
import {
    isVisualPreviewStage,
    resolveCompiledRedirectStages,
    resolveCompiledRiderIntroStages,
    resolveCompiledRuntimeStages,
    resolveLegacyPreClaimVisualStages,
    resolveLegacyRedirectStages,
    resolveLegacyRuntimeStages,
    preferCompiledStages,
} from '@/components/x-change/claimWidgetStages';
import {
    isCompiledFormValid as validateCompiledForm,
    missingRequiredCompiledFormFields as resolveMissingRequiredCompiledFormFields,
} from '@/components/x-change/compiledFormValidation';
import { resolveCompiledFormSubmitState } from '@/components/x-change/compiledFormSubmitState';
import { buildCompiledFormPayload } from '@/components/x-change/compiledFormPayload';
import { resolveCompiledFormSubmitIntent } from '@/components/x-change/compiledFormSubmit';

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

const hasValidCode = computed(() => code.value.trim().length > 0);

onMounted(() => {
    if (props.initialCode && submitButton.value) {
        const buttonEl = submitButton.value.$el as HTMLElement;
        buttonEl?.focus();
    }
});

const voucherInput = ref<HTMLInputElement | null>(null);
const submitButton = ref<HTMLButtonElement | null>(null);
const currentFormValues = ref<Record<string, unknown>>({});

const isNonActive = computed(() => {
    const s = voucherData.value?.status;
    return s === 'redeemed' || s === 'expired';
});

const statusDate = computed(() => {
    if (!voucherData.value) return null;
    if (voucherData.value.status === 'redeemed') return voucherData.value.redeemed_at;
    if (voucherData.value.status === 'expired') return voucherData.value.expired_at;
    return null;
});

const isReturningRedeemer = computed(() => {
    try {
        const raw = localStorage.getItem('form_flow_persist_wallet_info');
        if (!raw) return false;
        const saved = JSON.parse(raw);
        return !!saved.mobile;
    } catch {
        return false;
    }
});

function extractStages(value: unknown): RawRiderStage[] {
    if (Array.isArray(value)) {
        return value as RawRiderStage[];
    }

    if (
        value
        && typeof value === 'object'
        && Array.isArray((value as { stages?: unknown }).stages)
    ) {
        return (value as { stages: RawRiderStage[] }).stages;
    }

    return [];
}

function compiledPhase(key: string): Record<string, any> | null {
    return activeClaimExperiencePhase(props.claimExperience, key);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}

function instructionSplashStage(data: Record<string, any>): RawRiderStage | null {
    const rider = data.instructions?.rider;

    if (!isRecord(rider) || typeof rider.splash !== 'string' || rider.splash.trim() === '') {
        return null;
    }

    const splashMeta = isRecord(rider.splash_meta)
        ? rider.splash_meta
        : {};

    return {
        type: 'splash',
        key: 'legacy-splash',
        enabled: true,
        phase: 'pre_claim',
        presentation: 'fullscreen',
        content: rider.splash,
        content_type: 'html',
        payload: {
            content: rider.splash,
            content_type: 'html',
            timeout: rider.splash_timeout ?? null,
            presentation: 'fullscreen',
            meta: splashMeta,
        },
        meta: splashMeta,
    };
}

function hydrateInstructionSplashMeta(
    stage: RawRiderStage,
    data: Record<string, any>
): RawRiderStage {
    if (stage.key !== 'legacy-splash') {
        return stage;
    }

    const rider = data.instructions?.rider;
    const splashMeta = isRecord(rider?.splash_meta)
        ? rider.splash_meta
        : {};

    return {
        ...stage,
        presentation: stage.presentation ?? 'fullscreen',
        content_type: stage.content_type ?? stage.payload?.content_type ?? 'html',
        payload: {
            ...(stage.payload ?? {}),
            content_type: stage.payload?.content_type ?? stage.content_type ?? 'html',
            presentation: stage.payload?.presentation ?? stage.presentation ?? 'fullscreen',
            meta: {
                ...(stage.payload?.meta ?? {}),
                ...splashMeta,
            },
        },
        meta: {
            ...(stage.meta ?? {}),
            ...splashMeta,
        },
    };
}

function mergeStageWithRaw(
    stage: RawRiderStage,
    rawStages: RawRiderStage[]
): RawRiderStage {
    if (!stage.key) {
        return stage;
    }

    const raw = rawStages.find((candidate) => candidate.key === stage.key);

    if (!raw) {
        return stage;
    }

    return {
        ...raw,
        ...stage,
        payload: {
            ...(raw.payload ?? {}),
            ...(stage.payload ?? {}),
            meta: {
                ...(raw.payload?.meta ?? {}),
                ...(stage.payload?.meta ?? {}),
            },
        },
        meta: {
            ...(raw.meta ?? {}),
            ...(stage.meta ?? {}),
        },
        phase: stage.phase ?? raw.phase,
        presentation: stage.presentation ?? raw.presentation,
        content: stage.content ?? raw.content,
        content_type: stage.content_type ?? raw.content_type,
    };
}

function uniqueStages(stages: RawRiderStage[]): RawRiderStage[] {
    const seen = new Set<string>();

    return stages.filter((stage, index) => {
        const key = stage.key ?? `${stage.type}-${index}`;

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);
        return true;
    });
}

const riderStages = computed<RawRiderStage[]>(() => {
    const data = voucherData.value as Record<string, any> | null | undefined;

    if (!data) {
        return [];
    }

    const resolved = extractStages(data.rider?.stages);
    const raw = extractStages(data.instructions?.rider?.stages);
    const instructionSplash = instructionSplashStage(data);

    const mergedResolved = resolved.map((stage) =>
        hydrateInstructionSplashMeta(
            mergeStageWithRaw(stage, raw),
            data
        )
    );

    const missingRaw = raw.filter((rawStage, index) => {
        const rawKey = rawStage.key ?? `${rawStage.type}-${index}`;

        return !mergedResolved.some((stage, stageIndex) => {
            const key = stage.key ?? `${stage.type}-${stageIndex}`;

            return key === rawKey;
        });
    });

    const stages = uniqueStages([
        ...mergedResolved,
        ...missingRaw.map((stage) => hydrateInstructionSplashMeta(stage, data)),
        ...(instructionSplash ? [instructionSplash] : []),
    ]);

    return stages.map((stage) =>
        hydrateInstructionSplashMeta(stage, data)
    );
});

const compiledPreClaimVisualStages = computed<RawRiderStage[]>(() =>
    resolveCompiledRiderIntroStages(compiledPhase('rider_intro'))
        .filter(isVisualPreviewStage)
);

const legacyPreClaimVisualStages = computed<RawRiderStage[]>(() =>
    resolveLegacyPreClaimVisualStages(riderStages.value)
);

const preClaimVisualStages = computed<RawRiderStage[]>(() =>
    preferCompiledStages(
        compiledPreClaimVisualStages.value,
        legacyPreClaimVisualStages.value,
    )
);

const hasPreClaimContent = computed(() =>
    preClaimVisualStages.value.length > 0
);

function submit() {
    const entered = code.value || form.code;
    form.code = (entered || '').trim().toUpperCase();
    form.get('/x/claim', {
        preserveState: (page) => {
            const hasErrors = Object.keys(page.props.errors || {}).length > 0;
            return !hasErrors;
        },
        preserveScroll: true,
    });
}

const compiledRuntimeStages = computed<RawRiderStage[]>(() =>
    resolveCompiledRuntimeStages(compiledPhase('runtime'))
        .filter(isVisualPreviewStage)
);

const legacyRuntimeStages = computed<RawRiderStage[]>(() =>
    resolveLegacyRuntimeStages(riderStages.value)
);

const runtimeStages = computed<RawRiderStage[]>(() =>
    preferCompiledStages(
        compiledRuntimeStages.value,
        legacyRuntimeStages.value,
    )
);

const compiledRedirectStages = computed<RawRiderStage[]>(() =>
    resolveCompiledRedirectStages(compiledPhase('redirect'))
        .filter(isVisualPreviewStage)
);

const legacyRedirectStages = computed<RawRiderStage[]>(() =>
    resolveLegacyRedirectStages(riderStages.value)
);

const redirectStages = computed<RawRiderStage[]>(() =>
    preferCompiledStages(
        compiledRedirectStages.value,
        legacyRedirectStages.value,
    )
);

const compiledFormFlowPhase = computed<Record<string, any> | null>(() =>
    resolveCompiledFormFlowPhase(props.claimExperience)
);

type FormFlowBoundaryMode = 'compiled' | 'legacy';

const formFlowBoundary = computed<{
    mode: FormFlowBoundaryMode;
    phase: Record<string, any> | null;
}>(() => {
    if (compiledFormFlowPhase.value !== null) {
        return {
            mode: 'compiled',
            phase: compiledFormFlowPhase.value,
        };
    }

    return {
        mode: 'legacy',
        phase: null,
    };
});

const usesCompiledFormFlow = computed(() =>
    formFlowBoundary.value.mode === 'compiled'
);

const usesLegacyFormFlow = computed(() =>
    formFlowBoundary.value.mode === 'legacy'
);

const normalizedCompiledFormFlow = computed(() =>
    formFlowBoundary.value.mode === 'compiled'
        ? normalizeCompiledFormFlowPhase(formFlowBoundary.value.phase)
        : null
);

function updateCurrentFormValues(values: Record<string, unknown>): void {
    currentFormValues.value = values;
    emit('update:compiled-form-values', values);
}

const claimFormPayload = computed(() =>
    buildCompiledFormPayload(
        props.initialCode,
        currentFormValues.value,
    )
);

const missingRequiredCompiledFormFields = computed(() =>
    resolveMissingRequiredCompiledFormFields(
        normalizedCompiledFormFlow.value?.fields,
        currentFormValues.value,
    )
);

const isCompiledFormValid = computed(() =>
    validateCompiledForm(
        normalizedCompiledFormFlow.value?.fields,
        currentFormValues.value,
    )
);

const emit = defineEmits<{
    'submit:compiled-form': [payload: {
        code: string;
        values: Record<string, unknown>;
    }];
    'update:compiled-form-values': [values: Record<string, unknown>];
}>();

const isSubmittingCompiledForm = ref(false);

function submitCompiledForm(): void {
    if (resolveCompiledFormSubmitIntent(
        normalizedCompiledFormFlow.value !== null,
        isCompiledFormValid.value,
    ) === 'blocked') {
        return;
    }

    isSubmittingCompiledForm.value = true;

    emit('submit:compiled-form', claimFormPayload.value);
}

function submitClaim(): void {
    if (normalizedCompiledFormFlow.value) {
        submitCompiledForm();
        return;
    }

    submit();
}

const compiledFormSubmitState = computed(() =>
    resolveCompiledFormSubmitState({
        submitError: props.compiledFormSubmitError,
        submitted: props.compiledFormSubmitted,
        submitting: isSubmittingCompiledForm.value,
    })
);

const claimExperienceDebug = computed(() => {
    if (!props.claimExperience) {
        return null;
    }

    return {
        mode: props.claimExperience?.entry?.mode,
        initial_phase: props.claimExperience?.entry?.initial_phase,
        skip_consumed_splash: props.claimExperience?.options?.skip_consumed_splash,
        splash_owner: props.claimExperience?.diagnostics?.splash_owner,
        form_flow_splash_policy: props.claimExperience?.diagnostics?.form_flow_splash_policy,
        uses_compiled_rider_intro: compiledPreClaimVisualStages.value.length > 0,
        uses_compiled_runtime: compiledRuntimeStages.value.length > 0,
        uses_compiled_redirect: compiledRedirectStages.value.length > 0,
        form_flow_mode: formFlowBoundary.value.mode,
        uses_compiled_form_flow: usesCompiledFormFlow.value,
        uses_legacy_form_flow: usesLegacyFormFlow.value,
    };
});

if (import.meta.env.DEV && claimExperienceDebug.value) {
    console.debug('[x-change] claim experience', claimExperienceDebug.value);
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Logo and App Name -->
        <div v-if="!isNonActive" class="flex flex-col items-center gap-2">
            <AppLogoIcon class="h-20 w-auto" />
        </div>

        <!-- Title -->
        <div v-if="!isNonActive" class="space-y-2 text-center">
            <h1 class="text-xl font-medium">Claim Pay Code</h1>
        </div>

        <!-- Form -->
        <form v-if="!isNonActive" @submit.prevent="submitClaim" class="space-y-6">
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
                :disabled="normalizedCompiledFormFlow ? !isCompiledFormValid : false"
            >
                {{ form.processing ? 'Checking...' : 'Start Claim' }}
            </Button>
        </form>

        <!-- Voucher Preview -->
        <div v-if="showPreview" :class="isNonActive ? '' : 'mt-6'">
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center gap-2 py-8 text-muted-foreground">
                <Spinner class="h-5 w-5" />
                <span>Checking voucher...</span>
            </div>

            <!-- Error State -->
            <Alert v-else-if="error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ error }}
                </AlertDescription>
            </Alert>

            <!-- Preview disabled notice -->
            <Alert v-else-if="voucherData && voucherData.preview && voucherData.preview.enabled === false">
                <AlertDescription>
                    {{ voucherData.preview.message || 'Preview disabled by issuer.' }}
                </AlertDescription>
            </Alert>

            <!-- Non-Active State: Stamp + Rider Content -->
            <div v-else-if="voucherData && isNonActive" class="space-y-2.5">
                <!-- Status Stamp -->
                <VoucherStatusStamp
                    :status="voucherData.status as 'redeemed' | 'expired'"
                    :status-date="statusDate"
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
            <div v-else-if="voucherData">
                <!-- Rider pre-claim content from compiled/legacy rider intro -->
                <Card
                    v-if="hasPreClaimContent"
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
            v-if="normalizedCompiledFormFlow || usesLegacyFormFlow"
            data-testid="claim-widget-form-flow-boundary-region"
            :class="normalizedCompiledFormFlow ? 'space-y-4' : 'sr-only'"
        >
            <Card
                v-if="normalizedCompiledFormFlow"
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
                        :form-flow="normalizedCompiledFormFlow"
                        @update:values="updateCurrentFormValues"
                    />
                </CardContent>
            </Card>

            <pre
                v-if="normalizedCompiledFormFlow"
                data-testid="claim-widget-current-form-values"
            >{{ JSON.stringify(currentFormValues, null, 2) }}</pre>

            <pre
                v-if="normalizedCompiledFormFlow"
                data-testid="claim-widget-form-payload"
            >{{ JSON.stringify(claimFormPayload, null, 2) }}</pre>

            <pre
                v-if="normalizedCompiledFormFlow"
                data-testid="claim-widget-missing-required-fields"
            >{{ JSON.stringify(missingRequiredCompiledFormFields.map((field) => field.key), null, 2) }}</pre>

            <div
                v-if="normalizedCompiledFormFlow"
                data-testid="claim-widget-form-valid"
            >
                {{ isCompiledFormValid ? 'valid' : 'invalid' }}
            </div>

            <div
                v-if="usesCompiledFormFlow"
                data-testid="compiled-form-flow-boundary"
            >
                compiled form flow boundary
            </div>

            <div
                v-if="usesLegacyFormFlow"
                data-testid="legacy-form-flow-boundary"
            >
                legacy form flow boundary
            </div>

            <div
                data-testid="claim-widget-submit-state"
            >
                {{ compiledFormSubmitState }}
            </div>

            <div
                v-if="compiledFormSubmitError"
                data-testid="claim-widget-submit-error"
            >
                {{ compiledFormSubmitError }}
            </div>
        </div>
    </div>
</template>
