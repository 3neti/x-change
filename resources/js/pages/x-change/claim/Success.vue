<script setup lang="ts">
import { computed, toRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { CheckCircle2 } from 'lucide-vue-next';
import RiderRenderer from '@/components/x-rider/RiderRenderer.vue';
import RiderCountdown from '@/components/x-rider/RiderCountdown.vue';
import RiderStagePresenter from '@/components/x-rider/RiderStagePresenter.vue';
import RiderRuntimeSequencer from '@/components/x-rider/RiderRuntimeSequencer.vue';
import type { RawRiderStage, RiderExperience } from '@/components/x-rider/types';
import { useClaimSuccessRedirect } from './useClaimSuccessRedirect';
import { shouldRenderSuccessRedirectCountdown } from '@/components/x-change/successRedirect';
import {
    resolveRedirectRuntimeStages,
    resolveSuccessVisualStages,
} from '@/components/x-change/successRider';
import {
    formatSuccessVoucherAmount,
    hasNonZeroVoucherAmount,
    isPendingClaimOutcome,
    resolveSuccessFallbackTitle,
    shouldRenderSuccessRiderMessage,
} from '@/components/x-change/successFallback';
import { resolveSuccessViewModel } from '@/components/x-change/successViewModel';

defineOptions({ layout: null });

interface VoucherProps {
    code: string;
    amount?: number | string | null;
    formatted_amount?: string | null;
    formattedAmount?: string | null;
    currency?: string | null;
}

interface Props {
    voucher: VoucherProps;
    claimOutcome?: string;
    rider?: RiderExperience | null;
    redirectEndpoint?: string | null;
    claim_experience?: Record<string, any> | null;
    redirect?: {
        show_countdown?: boolean;
        owner?: string | null;
        delay_seconds?: number | null;
    } | null;
}

const props = defineProps<Props>();

const riderContent = computed(() => props.rider?.success ?? null);
const riderRedirect = computed(() => props.rider?.redirect ?? null);

const successVisualStages = computed<RawRiderStage[]>(() =>
    resolveSuccessVisualStages(props.claim_experience, props.rider)
);

const redirectRuntimeStages = computed<RawRiderStage[]>(() =>
    resolveRedirectRuntimeStages(props.rider)
);

const hasRiderMessage = computed(() =>
    shouldRenderSuccessRiderMessage(riderContent.value)
);

const successViewModel = computed(() =>
    resolveSuccessViewModel({
        successVisualStageCount: successVisualStages.value.length,
        redirectRuntimeStageCount: redirectRuntimeStages.value.length,
        hasRiderMessage: hasRiderMessage.value,
        hasRedirect: hasRedirect.value,
    })
);

const hasSuccessVisualStages = computed(() =>
    successViewModel.value.hasSuccessVisualStages
);

const hasRedirectRuntimeStages = computed(() =>
    successViewModel.value.hasRedirectRuntimeStages
);

const shouldShowVoucherCodeBadge = computed(() =>
    successViewModel.value.shouldShowVoucherCodeBadge
);

const {
    countdownRedirect,
    hasRedirect,
} = useClaimSuccessRedirect(
    riderRedirect,
    toRef(props, 'redirect'),
    toRef(props, 'redirectEndpoint'),
);

const shouldShowRedirectCountdown = computed(() =>
    shouldRenderSuccessRedirectCountdown(props.redirect)
);

const hasNonZeroAmount = computed(() =>
    hasNonZeroVoucherAmount(props.voucher)
);

const formattedAmount = computed(() =>
    formatSuccessVoucherAmount(props.voucher)
);

const isPending = computed(() =>
    isPendingClaimOutcome({
        claimOutcome: props.claimOutcome,
        riderState: props.rider?.state,
    })
);

const fallbackTitle = computed(() =>
    resolveSuccessFallbackTitle(props.voucher, {
        claimOutcome: props.claimOutcome,
        riderState: props.rider?.state,
    })
);

const shouldRenderFallback = computed(() =>
    successViewModel.value.shouldRenderFallback
);
</script>

<template>
    <Head title="Claim Successful" />

    <div class="min-h-screen bg-gradient-to-b from-primary/5 via-background to-background px-5 py-8">
        <Card class="mx-auto max-w-md border-0 bg-card/80 shadow-sm">
            <CardContent class="space-y-8 px-6 py-8">
                <div class="space-y-4 pt-4 text-center">
                    <CheckCircle2
                        class="mx-auto h-16 w-16"
                        :class="isPending ? 'text-amber-500' : 'text-green-500'"
                    />

                    <div
                        v-if="hasSuccessVisualStages"
                        data-testid="success-stage-region"
                        class="space-y-4"
                    >
                        <RiderStagePresenter
                            v-for="stage in successVisualStages"
                            :key="stage.key ?? `${stage.type}-${successVisualStages.indexOf(stage)}`"
                            :stage="stage"
                        />
                    </div>

                    <RiderRenderer
                        v-else-if="hasRiderMessage"
                        :content="riderContent"
                    />

                    <div
                        v-else-if="shouldRenderFallback"
                        data-testid="fallback-success-region"
                        class="space-y-3"
                    >
                        <p
                            v-if="hasNonZeroAmount"
                            class="text-2xl font-bold tracking-tight text-foreground"
                        >
                            {{ formattedAmount }}
                        </p>

                        <p class="text-center text-lg font-medium text-foreground">
                            {{ fallbackTitle }}
                        </p>
                    </div>

                    <div
                        v-if="shouldShowVoucherCodeBadge"
                        class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-4 py-1 font-mono text-sm font-semibold tracking-widest text-primary"
                    >
                        {{ voucher.code }}
                    </div>
                </div>

                <RiderRuntimeSequencer
                    v-if="hasRedirectRuntimeStages"
                    :stages="redirectRuntimeStages"
                    :redirect-endpoint="redirectEndpoint"
                />

                <div
                    v-if="hasRedirect && shouldShowRedirectCountdown"
                    data-testid="redirect-countdown-region"
                    class="mt-6"
                >
                    <RiderCountdown
                        :redirect="countdownRedirect"
                        :redirect-endpoint="redirectEndpoint"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
