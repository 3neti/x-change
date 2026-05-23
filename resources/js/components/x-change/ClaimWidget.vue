<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import VoucherInstructionsDisplay from '@/components/x-change/VoucherInstructionsDisplay.vue';
import VoucherMetadataDisplay from '@/components/x-change/VoucherMetadataDisplay.vue';
import VoucherStatusStamp from '@/components/x-change/VoucherStatusStamp.vue';
import { AlertCircle } from 'lucide-vue-next';
import { useVoucherPreview } from '@/composables/useVoucherPreview';
import { initializeTheme } from '@/composables/useTheme';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import RiderStagePresenter from '@/components/x-rider/RiderStagePresenter.vue';
import type { RawRiderStage } from '@/components/x-rider/types';

initializeTheme();

interface Props {
    initialCode?: string | null;
}

const props = defineProps<Props>();

const page = usePage();
const appName = (page.props as any).xchange?.branding?.name || (page.props.name as string) || 'X-Change';
const errors = computed(() => page.props.errors as Record<string, string>);

const form = useForm({
    code: props.initialCode || '',
});

const {
    code,
    loading,
    error,
    voucherData,
    showPreview,
    reset: resetPreview,
    hidePreview,
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

const renderedSplash = computed(() => {
    const splash = voucherData.value?.instructions?.rider?.splash;
    if (!splash) return null;
    if (splash.trim().startsWith('<svg') || splash.trim().startsWith('<SVG')) {
        return DOMPurify.sanitize(splash);
    }
    if (splash.trim().startsWith('<')) {
        return DOMPurify.sanitize(splash);
    }
    return DOMPurify.sanitize(marked.parse(splash) as string);
});

const riderStages = computed<RawRiderStage[]>(() => {
    const resolvedStages = voucherData.value?.rider?.stages?.stages;

    if (Array.isArray(resolvedStages)) {
        return resolvedStages as RawRiderStage[];
    }

    const rawStages = voucherData.value?.instructions?.rider?.stages;

    return Array.isArray(rawStages) ? rawStages : [];
});

const preClaimStage = computed<RawRiderStage | null>(() => {
    const stages = riderStages.value.filter((stage) =>
        stage.type === 'splash'
        && stage.enabled !== false
    );

    return stages.length > 0
        ? stages[stages.length - 1]
        : null;
});

const hasPreClaimContent = computed(() =>
    Boolean(preClaimStage.value)
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

                    <!-- Rider Splash -->
                    <Card v-if="renderedSplash">
                        <CardContent class="pt-3 pb-3">
                            <div
                                v-html="renderedSplash"
                                class="prose prose-sm max-w-none dark:prose-invert"
                            />
                        </CardContent>
                    </Card>

                </template>
            </div>

            <!-- Active State: Tabbed preview -->
            <div v-else-if="voucherData">
                <!-- Rider pre-claim content from splash stage -->
                <Card v-if="hasPreClaimContent" class="mb-4 border-primary/10 bg-primary/5">
                    <CardContent class="pt-4 pb-4">
                        <RiderStagePresenter :stage="preClaimStage" />
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
    </div>
</template>
