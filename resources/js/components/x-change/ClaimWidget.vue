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
import { stageIsInPhase } from '@/components/x-rider/useRiderStagePhase';

initializeTheme();

interface Props {
    initialCode?: string | null;
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

    const stages = resolved.length > 0
        ? resolved.map((stage) => mergeStageWithRaw(stage, raw))
        : raw;

    return uniqueStages(stages);
});

function isVisualPreviewStage(stage: RawRiderStage): boolean {
    return ['splash', 'message', 'image', 'link', 'cta'].indexOf(stage.type) >= 0;
}

function isPreClaimStage(stage: RawRiderStage): boolean {
    return stageIsInPhase(stage, 'pre_claim');
}

const preClaimVisualStages = computed<RawRiderStage[]>(() =>
    riderStages.value.filter((stage) =>
        stage.enabled !== false
        && isPreClaimStage(stage)
        && isVisualPreviewStage(stage)
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

const runtimeStages = computed<RawRiderStage[]>(() =>
    riderStages.value.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'runtime')
        && isVisualPreviewStage(stage)
    )
);
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
        <form v-if="!isNonActive" @submit.prevent="submit" class="space-y-6">
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
                :disabled="form.processing || !hasValidCode"
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
                <!-- Rider pre-claim content from splash stage -->
                <Card v-if="hasPreClaimContent" class="mb-4 border-primary/10 bg-primary/5">
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
        <RiderRuntimeSequencer
            v-if="runtimeStages.length > 0"
            :stages="runtimeStages"
        />
    </div>
</template>
