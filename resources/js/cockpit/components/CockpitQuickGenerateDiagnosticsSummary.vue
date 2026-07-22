<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitQuickGenerateAuthorization,
    CockpitQuickGenerateFundingGate,
    CockpitQuickGenerateIdempotencyGate,
    CockpitQuickGenerateMutationAuthorizationDecision,
    CockpitQuickGenerateMutationContract,
    CockpitQuickGenerateMutationHandoffPlan,
    CockpitQuickGenerateMutationPreconditionsReview,
    CockpitQuickGeneratePricingGate,
    CockpitQuickGenerateValidationRedactionGate,
} from '../types';

const props = defineProps<{
    mutationContract?: CockpitQuickGenerateMutationContract;
    pricingGate?: CockpitQuickGeneratePricingGate;
    fundingGate?: CockpitQuickGenerateFundingGate;
    idempotencyGate?: CockpitQuickGenerateIdempotencyGate;
    validationRedactionGate?: CockpitQuickGenerateValidationRedactionGate;
    mutationHandoffPlan?: CockpitQuickGenerateMutationHandoffPlan;
    mutationPreconditionsReview?: CockpitQuickGenerateMutationPreconditionsReview;
    mutationAuthorizationDecision?: CockpitQuickGenerateMutationAuthorizationDecision;
    authorization?: CockpitQuickGenerateAuthorization;
}>();

type DiagnosticSummaryItem = {
    key: string;
    label: string;
    status: string;
    helper: string;
};

const items = computed<DiagnosticSummaryItem[]>(() => [
    {
        key: 'operator-submit',
        label: 'Operator Submit',
        status:
            props.mutationContract?.runtime_enabled === true
                ? 'Ready'
                : displayStatus(props.mutationContract?.status),
        helper: 'Quick Generate submits through the approved GeneratePayCode handoff.',
    },
    {
        key: 'pricing',
        label: 'Pricing',
        status: displayStatus(props.pricingGate?.status),
        helper: 'Shown as an operator-safe preflight after submit.',
    },
    {
        key: 'funding',
        label: 'Funding',
        status: displayStatus(props.fundingGate?.status),
        helper: 'Shown as a redacted preflight; Cockpit does not expose wallet internals.',
    },
    {
        key: 'validation',
        label: 'Validation',
        status: displayStatus(props.validationRedactionGate?.status),
        helper: 'Uses the existing request path and redacted operator response.',
    },
    {
        key: 'idempotency',
        label: 'Idempotency',
        status: displayStatus(props.idempotencyGate?.status),
        helper: 'Handled by the existing x-change idempotency service.',
    },
    {
        key: 'issuance-owner',
        label: 'Issuance Owner',
        status: displayStatus(props.mutationHandoffPlan?.status),
        helper: 'Generation remains owned by the existing x-change issuance action.',
    },
    {
        key: 'approval',
        label: 'Approval Boundary',
        status: displayStatus(props.mutationAuthorizationDecision?.status),
        helper: 'Only the existing handoff is authorized; providers and integrations stay gated.',
    },
    {
        key: 'external-effects',
        label: 'External Effects',
        status: 'Separately gated',
        helper: 'Journal, action, feedback, provider, and campaign mutations are not triggered here.',
    },
]);

const recommendation = computed<string>(() => {
    return (
        displayStatus(props.mutationPreconditionsReview?.recommendation) ||
        'Use existing issuance handoff'
    );
});

function displayStatus(value?: string | number | null): string {
    if (value === null || value === undefined || value === '') {
        return 'Not connected';
    }

    return String(value)
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-950"
        data-testid="cockpit-quick-generate-diagnostics-summary"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p
                    class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                >
                    Readiness summary
                </p>
                <h3
                    class="mt-0.5 text-sm font-semibold text-slate-950 dark:text-slate-50"
                >
                    Quick Generate handoff status
                </h3>
                <p
                    class="mt-0.5 max-w-2xl text-xs leading-4 text-slate-600 dark:text-slate-400"
                >
                    This summary replaces the old wall of gate panels for
                    normal review. The full architecture history remains
                    available below for debugging.
                </p>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <span class="inline-flex w-fit rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-semibold text-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    {{ items.length }} checks
                </span>
                <span
                    class="inline-flex w-fit rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[0.7rem] font-semibold text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300"
                >
                    {{ recommendation }}
                </span>
            </div>
        </div>

        <div
            class="mt-3 grid gap-2 border-t border-slate-200 pt-3 md:grid-cols-2 xl:grid-cols-4 dark:border-slate-800"
            data-testid="cockpit-quick-generate-diagnostics-summary-grid"
        >
            <article
                v-for="item in items"
                :key="item.key"
                class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-900/60"
                data-testid="cockpit-quick-generate-diagnostics-summary-item"
            >
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ item.label }}
                </p>
                <p
                    class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50"
                >
                    {{ item.status }}
                </p>
                <p class="mt-1 text-xs leading-4 text-slate-600 dark:text-slate-400">
                    {{ item.helper }}
                </p>
            </article>
        </div>
    </section>
</template>
