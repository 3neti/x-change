<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
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
import { useCompletionStatusPoll } from './useCompletionStatusPoll';
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
import ClaimPendingReceipt from '@/components/x-change/ClaimPendingReceipt.vue';
import type { PayoutDestinationSnapshot } from '@/components/x-change/support/payoutDestinations';
import { settlementRailLabel } from '@/components/x-change/support/payoutDestinations';

defineOptions({ layout: null });

interface VoucherProps {
    code: string;
    amount?: number | string | null;
    formatted_amount?: string | null;
    formattedAmount?: string | null;
    currency?: string | null;
}

interface Props {
    paired_payment?: boolean;
    voucher: VoucherProps;
    claimOutcome?: string;
    claimWorkflowKey?: string | null;
    sender_content_source?: 'message' | 'none' | null;
    rider?: RiderExperience | null;
    redirectEndpoint?: string | null;
    handoff_retry_url?: string | null;
    claim_experience?: Record<string, any> | null;
    redirect?: {
        show_countdown?: boolean;
        owner?: string | null;
        delay_seconds?: number | null;
    } | null;
    compiled_claim_result?: CompiledClaimResultPayload;
    destination?: PayoutDestinationSnapshot | null;
    success_presentation?: {
        state?: string | null;
        suppress_legacy_rider?: boolean;
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
        intent?: string | null;
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

const completionProcessing = computed(() =>
    props.claimWorkflowKey === 'campaign.coverage-completion.v1'
    && props.success_presentation?.suppress_legacy_rider === true
    && props.success_presentation?.state === 'processing',
);
const completionPoll = useCompletionStatusPoll(completionProcessing);

const suppressLegacyRider = computed(() => props.success_presentation?.suppress_legacy_rider === true);
const useDefaultThankYou = computed(() =>
    props.sender_content_source === 'none' && !suppressLegacyRider.value && !props.success_presentation,
);
const hasPaymentHandoff = computed(() =>
    props.success_action?.key === 'x-change.claim-success.continue-to-payment'
    && props.success_action.enabled !== false
    && Boolean(props.success_action.label?.trim())
    && Boolean(props.success_action.target?.url?.trim()),
);
const suppressAutomaticRedirects = computed(() =>
    props.paired_payment || hasPaymentHandoff.value || suppressLegacyRider.value,
);

const riderContent = computed(() => suppressLegacyRider.value || useDefaultThankYou.value ? null : props.rider?.success ?? null);
const riderRedirect = computed(() =>
    suppressAutomaticRedirects.value ? null : props.rider?.redirect ?? null,
);
const effectiveRedirect = computed(() =>
    suppressAutomaticRedirects.value ? null : props.redirect ?? null,
);
const effectiveRedirectEndpoint = computed(() =>
    suppressAutomaticRedirects.value ? null : props.redirectEndpoint ?? null,
);

const riderMessageState = computed(() => props.sender_content_source === 'message'
    ? { claimOutcome: null, riderState: null }
    : { claimOutcome: props.claimOutcome, riderState: props.rider?.state });

const displayedRiderContent = computed(() =>
    resolveSuccessRiderMessage(riderContent.value, riderMessageState.value),
);

const successVisualStages = computed<RawRiderStage[]>(() =>
    suppressLegacyRider.value || useDefaultThankYou.value ? [] : resolveSuccessVisualStages(props.claim_experience, props.rider, riderMessageState.value),
);

const redirectRuntimeStages = computed<RawRiderStage[]>(() =>
    suppressAutomaticRedirects.value
        ? []
        : resolveRedirectRuntimeStages(props.rider, props.claim_experience),
);

const hasRiderMessage = computed(() =>
    shouldRenderSuccessRiderMessage(displayedRiderContent.value),
);
const showSenderMessageLabel = computed(() => {
    if (useDefaultThankYou.value || props.success_presentation?.title?.trim()) return false;
    if (props.sender_content_source === 'message') return successVisualStages.value.length > 0 || hasRiderMessage.value;
    if (successVisualStages.value.length) {
        return successVisualStages.value.some((stage) =>
            (stage.payload?.content ?? stage.content) !== 'Your claim is being processed.',
        );
    }
    return hasRiderMessage.value && displayedRiderContent.value?.content !== 'Your claim is being processed.';
});

const { countdownRedirect, hasRedirect } = useClaimSuccessRedirect(
    riderRedirect,
    effectiveRedirect,
    effectiveRedirectEndpoint,
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
    () => Boolean(effectiveRedirectEndpoint.value)
        && successViewModel.value.hasRedirectRuntimeStages,
);

const shouldShowVoucherCodeBadge = computed(
    () => successViewModel.value.shouldShowVoucherCodeBadge,
);

const redirectOwnership = computed(() =>
    resolveSuccessRedirectOwnershipViewModel(effectiveRedirect.value),
);

const countdownViewModel = computed(() =>
    resolveSuccessCountdownViewModel({
        countdownRedirect: countdownRedirect.value,
        redirectEndpoint: effectiveRedirectEndpoint.value,
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

const receiptAmount = computed(() => {
    const amount = props.destination?.claim_amount_minor;
    const currency = props.destination?.claim_currency;
    if (typeof amount !== 'number' || !currency) return null;
    try {
        return new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(amount / 100);
    } catch {
        return null;
    }
});

const fallbackTitle = computed(() =>
    resolveSuccessFallbackTitle(props.voucher, {
        claimOutcome: props.claimOutcome,
        claimWorkflowKey: props.claimWorkflowKey,
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
        claimWorkflowKey: props.claimWorkflowKey,
        riderState: props.rider?.state,
        successPresentationState: props.success_presentation?.state,
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
    if (suppressLegacyRider.value && action?.intent !== 'demo_policy_summary') {
        return null;
    }
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
        :tone="pageTone.isWarning ? 'warning' : 'success'"
        :brand-placement="successPresentation ? 'center' : 'top_left'"
        brand-size="display"
        :show-theme-picker="false"
        :width="successPresentation ? 'lg' : 'md'"
    >
        <div class="space-y-8">
            <div class="space-y-4 pt-4 text-center">
                <component
                    v-if="!pageTone.isPending"
                    :is="pageTone.isAttention ? AlertTriangle : CheckCircle2"
                    class="mx-auto h-12 w-12"
                    :class="pageTone.iconClass"
                />

                <ClaimPendingReceipt
                    v-if="pageTone.isPending"
                    :amount="receiptAmount"
                    :bank-label="destination?.bank_code ? destination.bank_label || destination.bank_name : null"
                    :account="destination?.account_number_full || destination?.account_number_masked"
                    :rail="destination?.bank_code ? settlementRailLabel(destination.settlement_rail || 'INSTAPAY') : null"
                />

                <p v-if="showSenderMessageLabel" class="text-left text-xs font-semibold text-muted-foreground">Message from the sender</p>

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

                <p v-else-if="useDefaultThankYou" data-testid="claim-default-thank-you" class="text-sm text-foreground">Thank you.</p>

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
                    v-else-if="shouldRenderFallback && !pageTone.isPending"
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

                <a
                    v-if="successAction"
                    :href="successAction.url"
                    referrerpolicy="no-referrer"
                    data-testid="claim-success-primary-action"
                    class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    {{ successAction.label }}
                </a>

                <div v-if="completionProcessing" data-testid="completion-status-check" class="space-y-3">
                    <p role="status" aria-live="polite" class="text-sm text-muted-foreground">
                        {{ completionPoll.message.value }}
                    </p>
                    <button
                        v-if="completionPoll.stopped.value"
                        type="button"
                        :disabled="completionPoll.checking.value"
                        class="inline-flex min-h-11 items-center justify-center rounded-md border px-6 py-2 text-sm font-semibold disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-ring"
                        @click="completionPoll.check"
                    >
                        {{ completionPoll.checking.value ? 'Checking…' : 'Check status' }}
                    </button>
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
                    || {{ voucher.code }} ||
                </div>

                <PayoutRouteDisplay
                    v-if="destination?.bank_code && !pageTone.isPending"
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
                :redirect-endpoint="effectiveRedirectEndpoint"
            />

            <a
                v-if="handoff_retry_url"
                :href="handoff_retry_url"
                class="mx-auto block w-fit text-sm font-medium text-primary underline underline-offset-4"
            >
                Continue to destination
            </a>

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
