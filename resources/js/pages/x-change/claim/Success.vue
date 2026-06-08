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
    resolveSuccessFallbackTitle,
    shouldRenderSuccessRiderMessage,
} from '@/components/x-change/successFallback';
import { resolveSuccessViewModel } from '@/components/x-change/successViewModel';
import {
    resolveSuccessCompiledClaimResultViewModel,
    type CompiledClaimResultPayload,
} from '@/components/x-change/successCompiledClaimResult';
import { resolveSuccessPageTone } from '@/components/x-change/successPageTone';

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
    compiled_claim_result?: CompiledClaimResultPayload;
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

const {
    countdownRedirect,
    hasRedirect,
} = useClaimSuccessRedirect(
    riderRedirect,
    toRef(props, 'redirect'),
    toRef(props, 'redirectEndpoint'),
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

const shouldShowRedirectCountdown = computed(() =>
    shouldRenderSuccessRedirectCountdown(props.redirect)
);

const hasNonZeroAmount = computed(() =>
    hasNonZeroVoucherAmount(props.voucher)
);

const formattedAmount = computed(() =>
    formatSuccessVoucherAmount(props.voucher)
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

const compiledClaimResult = computed(() =>
    resolveSuccessCompiledClaimResultViewModel(props.compiled_claim_result ?? null)
);

const pageTone = computed(() =>
    resolveSuccessPageTone({
        compiledClaimStatus: compiledClaimResult.value.status,
        claimOutcome: props.claimOutcome,
        riderState: props.rider?.state,
    })
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
                        :class="pageTone.iconClass"
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
                        v-if="compiledClaimResult.visible"
                        data-testid="compiled-claim-result-region"
                        class="rounded-lg border border-primary/10 bg-primary/5 p-4 text-left"
                    >
                        <p
                            data-testid="compiled-claim-result-title"
                            class="text-sm font-semibold text-foreground"
                        >
                            {{ compiledClaimResult.title }}
                        </p>

                        <p
                            v-if="compiledClaimResult.status"
                            data-testid="compiled-claim-result-status"
                            class="mt-1 text-xs uppercase tracking-wide text-muted-foreground"
                        >
                            {{ compiledClaimResult.status }}
                        </p>

                        <p
                            v-if="compiledClaimResult.amountText"
                            data-testid="compiled-claim-result-amount"
                            class="mt-3 text-lg font-semibold text-foreground"
                        >
                            {{ compiledClaimResult.amountText }}
                        </p>

                        <ul
                            v-if="compiledClaimResult.messages.length > 0"
                            data-testid="compiled-claim-result-messages"
                            class="mt-3 list-disc space-y-1 pl-5 text-sm text-muted-foreground"
                        >
                            <li
                                v-for="message in compiledClaimResult.messages"
                                :key="message"
                            >
                                {{ message }}
                            </li>
                        </ul>
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
