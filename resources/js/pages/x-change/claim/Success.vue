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
import { stageIsInPhase } from '@/components/x-rider/useRiderStagePhase';
import { useClaimSuccessRedirect } from './useClaimSuccessRedirect';

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

const riderStages = computed<RawRiderStage[]>(() => {
    const stages = props.rider?.stages?.stages;

    return Array.isArray(stages)
        ? stages as RawRiderStage[]
        : [];
});

function isRedirectStage(stage: RawRiderStage): boolean {
    return stage.type === 'redirect'
        || stageIsInPhase(stage, 'redirect');
}

const successVisualStages = computed<RawRiderStage[]>(() => {
    const stages = riderStages.value.filter((stage) =>
            stage.enabled !== false
            && !isRedirectStage(stage)
            && (
                stageIsInPhase(stage, 'success')
                || stageIsInPhase(stage, 'post_claim')
            )
    );

    const explicit = stages.filter((stage) =>
        stage.key !== 'legacy-message'
    );

    return explicit.length > 0
        ? explicit
        : stages.slice(0, 1);
});

const redirectRuntimeStages = computed<RawRiderStage[]>(() => {
    const stages = riderStages.value.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'redirect')
    );

    const explicit = stages.filter((stage) =>
        stage.key !== 'legacy-redirect'
    );

    return explicit.length > 0
        ? explicit
        : stages.slice(0, 1);
});

const hasSuccessVisualStages = computed(() =>
    successVisualStages.value.length > 0
);

const hasRedirectRuntimeStages = computed(() =>
    redirectRuntimeStages.value.length > 0
);

const hasRiderMessage = computed(() =>
    Boolean(riderContent.value?.enabled && riderContent.value?.content)
);

const hasAnyRiderContent = computed(() =>
    hasSuccessVisualStages.value || hasRiderMessage.value
);

const {
    countdownRedirect,
    hasRedirect,
} = useClaimSuccessRedirect(
    riderRedirect,
    toRef(props, 'redirect'),
    toRef(props, 'redirectEndpoint'),
);

const numericAmount = computed(() => Number(props.voucher.amount ?? 0));

const hasNonZeroAmount = computed(() => numericAmount.value > 0);

const formattedAmount = computed(() =>
    props.voucher.formatted_amount
    ?? props.voucher.formattedAmount
    ?? (hasNonZeroAmount.value
        ? `${props.voucher.currency ?? ''} ${numericAmount.value.toLocaleString()}`
        : '')
);

const isPending = computed(() =>
    props.claimOutcome === 'accepted_pending'
    || props.rider?.state === 'accepted_pending'
);

const fallbackTitle = computed(() => {
    if (isPending.value) {
        return 'Your claim is being processed';
    }

    return hasNonZeroAmount.value
        ? 'Disbursed to your account'
        : 'Pay Code claimed';
});

const shouldRenderFallback = computed(() =>
    !hasSuccessVisualStages.value
    && !hasRiderMessage.value
    && !hasRedirect.value
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
                        v-if="!hasAnyRiderContent"
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
                    v-if="hasRedirect"
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
