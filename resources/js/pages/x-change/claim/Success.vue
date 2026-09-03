<script setup lang="ts">
import { computed, toRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, Clock3 } from 'lucide-vue-next';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import RiderRenderer from '@/components/x-rider/RiderRenderer.vue';
import RiderCountdown from '@/components/x-rider/RiderCountdown.vue';
import RiderStagePresenter from '@/components/x-rider/RiderStagePresenter.vue';
import RiderRuntimeSequencer from '@/components/x-rider/RiderRuntimeSequencer.vue';
import type {
    RawRiderStage,
    RiderExperience,
} from '@/components/x-rider/types';
import { useClaimSuccessRedirect } from './useClaimSuccessRedirect';
import { resolveSuccessRedirectOwnershipViewModel } from '@/components/x-change/successRedirectOwnershipViewModel';
import {
    resolveRedirectRuntimeStages,
    resolveSuccessVisualStages,
} from '@/components/x-change/successRider';
import {
    formatSuccessVoucherAmount,
    hasNonZeroVoucherAmount,
    resolveSuccessRiderMessage,
    resolveSuccessFallbackTitle,
    shouldRenderSuccessRiderMessage,
} from '@/components/x-change/successFallback';
import { resolveSuccessViewModel } from '@/components/x-change/successViewModel';
import { resolveSuccessCompiledClaimResultViewModel,
    type CompiledClaimResultPayload,
} from '@/components/x-change/successCompiledClaimResult';
import { resolveSuccessPageTone } from '@/components/x-change/successPageTone';
import { resolveSuccessCountdownViewModel } from '@/components/x-change/successCountdownViewModel';
import PayoutRouteDisplay from '@/components/x-change/PayoutRouteDisplay.vue';
import type { PayoutDestinationSnapshot } from '@/components/x-change/support/payoutDestinations';

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
    destination?: PayoutDestinationSnapshot | null;
    success_presentation?: {
        intent?: string | null;
        eyebrow?: string | null;
        title?: string | null;
        account_message?: string | null;
        body?: string | null;
        message?: string | null;
        receipt_label?: string | null;
        receipt_code?: string | null;
        funds?: {
            label?: string | null;
            amount?: string | null;
            text?: string | null;
        } | null;
    } | null;
    success_action?: {
        key?: string | null;
        label?: string | null;
        enabled?: boolean | null;
        target?: {
            url?: string | null;
            method?: string | null;
            redirectable?: boolean | null;
        } | null;
    } | null;
}

const props = defineProps<Props>();

const riderContent = computed(() => props.rider?.success ?? null);
const riderRedirect = computed(() => props.rider?.redirect ?? null);

const displayedRiderContent = computed(() =>
    resolveSuccessRiderMessage(riderContent.value, {
        claimOutcome: props.claimOutcome,
        riderState: props.rider?.state,
    }),
);

const successVisualStages = computed<RawRiderStage[]>(() =>
    resolveSuccessVisualStages(props.claim_experience, props.rider, {
        claimOutcome: props.claimOutcome,
        riderState: props.rider?.state,
    }),
);

const redirectRuntimeStages = computed<RawRiderStage[]>(() =>
    resolveRedirectRuntimeStages(props.rider, props.claim_experience),
);

const hasRiderMessage = computed(() =>
    shouldRenderSuccessRiderMessage(displayedRiderContent.value),
);

const { countdownRedirect, hasRedirect } = useClaimSuccessRedirect(
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
    }),
);

const hasSuccessVisualStages = computed(
    () => successViewModel.value.hasSuccessVisualStages,
);

const hasRedirectRuntimeStages = computed(
    () => successViewModel.value.hasRedirectRuntimeStages,
);

const shouldShowVoucherCodeBadge = computed(
    () => successViewModel.value.shouldShowVoucherCodeBadge,
);

const redirectOwnership = computed(() =>
    resolveSuccessRedirectOwnershipViewModel(props.redirect ?? null),
);

const countdownViewModel = computed(() =>
    resolveSuccessCountdownViewModel({
        countdownRedirect: countdownRedirect.value,
        redirectEndpoint: props.redirectEndpoint ?? null,
        redirectOwnership: redirectOwnership.value,
    }),
);

const shouldShowRedirectCountdown = computed(
    () => countdownViewModel.value.visible,
);

const hasNonZeroAmount = computed(() => hasNonZeroVoucherAmount(props.voucher));

const formattedAmount = computed(() =>
    formatSuccessVoucherAmount(props.voucher),
);

const fallbackTitle = computed(() =>
    resolveSuccessFallbackTitle(props.voucher, {
        claimOutcome: props.claimOutcome,
        riderState: props.rider?.state,
    }),
);

const shouldRenderFallback = computed(
    () => successViewModel.value.shouldRenderFallback,
);

const compiledClaimResult = computed(() =>
    resolveSuccessCompiledClaimResultViewModel(
        props.compiled_claim_result ?? null,
    ),
);

const pageTone = computed(() =>
    resolveSuccessPageTone({
        compiledClaimStatus: compiledClaimResult.value.status,
        claimOutcome: props.claimOutcome,
        riderState: props.rider?.state,
    }),
);

const successPresentation = computed(() => {
    const presentation = props.success_presentation;

    if (!presentation?.title?.trim()) {
        return null;
    }

    return {
        eyebrow: presentation.eyebrow?.trim() || null,
        title: presentation.title.trim(),
        accountMessage: presentation.account_message?.trim() || null,
        body: presentation.body?.trim() || presentation.message?.trim() || null,
        receiptLabel: presentation.receipt_label?.trim() || null,
        receiptCode: presentation.receipt_code?.trim() || props.voucher.code,
        funds: presentation.funds?.text?.trim()
            ? {
                label: presentation.funds.label?.trim() || 'Client Funds',
                text: presentation.funds.text.trim(),
            }
            : null,
    };
});

const successAction = computed(() => {
    const action = props.success_action;
    const url = action?.target?.url?.trim();

    if (!action?.label?.trim() || !url || action.enabled === false) {
        return null;
    }

    return {
        key: action.key?.trim() || 'success-action',
        label: action.label.trim(),
        url,
    };
});
</script>

<template>
    <Head :title="successPresentation?.title ?? 'Claim Successful'" />

    <ClaimStepShell
        :tone="pageTone.isPending ? 'warning' : 'success'"
        :brand-placement="successPresentation ? 'center' : 'top_left'"
        :brand-size="successPresentation ? 'brand' : 'header'"
        :show-theme-picker="!successPresentation"
        :width="successPresentation ? 'lg' : 'md'"
    >
        <div class="space-y-8">
            <div class="space-y-4 pt-4 text-center">
                <component
                    :is="pageTone.isPending ? Clock3 : CheckCircle2"
                    class="mx-auto h-12 w-12"
                    :class="pageTone.iconClass"
                />

                <div
                    v-if="pageTone.isPending"
                    data-testid="provider-payout-pending-region"
                    role="status"
                    class="rounded-lg border border-amber-500/25 bg-amber-500/10 p-4 text-left"
                >
                    <p class="text-sm font-semibold text-foreground">
                        Claim accepted · payout pending
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        The Pay Code has been claimed. Funds are released only
                        after the payment provider confirms the transfer.
                    </p>
                </div>

                <div
                    v-if="successPresentation"
                    data-testid="claim-success-presentation"
                    class="space-y-3"
                >
                    <p
                        v-if="successPresentation.eyebrow"
                        class="text-xs font-semibold tracking-[0.22em] text-muted-foreground uppercase"
                    >
                        {{ successPresentation.eyebrow }}
                    </p>

                    <h1
                        data-testid="claim-success-title"
                        class="text-3xl font-semibold tracking-tight text-foreground"
                    >
                        {{ successPresentation.title }}
                    </h1>

                    <p
                        v-if="successPresentation.accountMessage"
                        data-testid="claim-success-account-message"
                        class="text-lg font-medium text-foreground"
                    >
                        {{ successPresentation.accountMessage }}
                    </p>

                    <div
                        v-if="successPresentation.funds"
                        data-testid="claim-success-funds"
                        class="mx-auto max-w-sm rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-5 py-4"
                    >
                        <p class="text-xl font-semibold text-foreground">
                            {{ successPresentation.funds.text }}
                        </p>
                        <p
                            class="mt-1 text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            {{ successPresentation.funds.label }}
                        </p>
                    </div>

                    <p
                        v-if="successPresentation.body"
                        data-testid="claim-success-message"
                        class="text-sm leading-6 text-muted-foreground"
                    >
                        {{ successPresentation.body }}
                    </p>

                    <a
                        v-if="successAction"
                        :href="successAction.url"
                        data-testid="claim-success-primary-action"
                        class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        {{ successAction.label }}
                    </a>

                    <p
                        v-if="successPresentation.receiptLabel"
                        data-testid="claim-success-receipt"
                        class="pt-2 font-mono text-[11px] tracking-wide text-muted-foreground"
                    >
                        {{ successPresentation.receiptLabel }}
                        <span v-if="successPresentation.receiptCode">
                            · {{ successPresentation.receiptCode }}
                        </span>
                    </p>
                </div>

                <div
                    v-else-if="hasSuccessVisualStages"
                    data-testid="success-stage-region"
                    class="space-y-4"
                >
                    <RiderStagePresenter
                        v-for="stage in successVisualStages"
                        :key="
                            stage.key ??
                            `${stage.type}-${successVisualStages.indexOf(stage)}`
                        "
                        :stage="stage"
                    />
                </div>

                <RiderRenderer
                    v-else-if="hasRiderMessage"
                    :content="displayedRiderContent"
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
                        class="mt-1 text-xs tracking-wide text-muted-foreground uppercase"
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

                <PayoutRouteDisplay
                    v-if="destination?.bank_code"
                    class="text-left"
                    :amount="formattedAmount"
                    :bank-code="destination.bank_code"
                    :account-number="destination.account_number_masked"
                    :settlement-rail="destination.settlement_rail || 'INSTAPAY'"
                />
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
                    :redirect="countdownViewModel.redirect"
                    :redirect-endpoint="countdownViewModel.endpoint"
                />
            </div>
        </div>
    </ClaimStepShell>
</template>
