<script setup lang="ts">
import { computed } from 'vue';
import type { CockpitQuickGenerateMutationAuthorizationDecision } from '../types';

const props = defineProps<{
    mutationAuthorizationDecision?: CockpitQuickGenerateMutationAuthorizationDecision;
}>();

const status = computed<string>(() => {
    return displayValue(props.mutationAuthorizationDecision?.status, 'not_wired');
});

const decision = computed<string>(() => {
    return displayValue(props.mutationAuthorizationDecision?.decision, 'not-loaded');
});

const requiredApproval = computed<string>(() => {
    return displayValue(props.mutationAuthorizationDecision?.required_approval, 'not-loaded');
});

const rationale = computed<string>(() => {
    return displayValue(props.mutationAuthorizationDecision?.rationale, 'No mutation authorization decision is available.');
});

const nextStep = computed<string>(() => {
    return displayValue(props.mutationAuthorizationDecision?.next_step, 'not-loaded');
});

const redaction = computed<string>(() => {
    return displayValue(props.mutationAuthorizationDecision?.redactions?.payloads, 'mutation-authorization-decision-only');
});

function displayValue(value: unknown, fallback: string): string {
    if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
        return fallback;
    }

    const normalized = String(value).trim();

    return normalized === '' ? fallback : normalized;
}
</script>

<template>
    <section
        class="rounded-xl border border-rose-200 bg-rose-50 p-5 shadow-sm dark:border-rose-900/70 dark:bg-rose-950/30"
        data-testid="cockpit-quick-generate-mutation-authorization-decision-panel"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-700 dark:text-rose-300">
                    Mutation Authorization Decision Point
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Quick Generate mutation remains explicitly unauthorized
                </h3>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This decision point records whether Cockpit may graduate from read-only readiness facts to mutation-route scaffolding.
                </p>
            </div>
            <span class="rounded-full border border-rose-200 bg-white px-3 py-1 text-xs font-semibold text-rose-700 dark:border-rose-900 dark:bg-slate-950 dark:text-rose-300">
                {{ status }}
            </span>
        </div>

        <dl class="mt-4 grid gap-3">
            <div class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50">
                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                    Decision
                </dt>
                <dd class="mt-1 font-mono text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ decision }}
                </dd>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50">
                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                    Required Approval
                </dt>
                <dd class="mt-1 font-mono text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ requiredApproval }}
                </dd>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50">
                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                    Rationale
                </dt>
                <dd class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ rationale }}
                </dd>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50">
                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                    Next Step
                </dt>
                <dd class="mt-1 font-mono text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ nextStep }}
                </dd>
            </div>
        </dl>

        <p class="mt-4 text-xs text-slate-600 dark:text-slate-300">
            Redaction policy: <span class="font-semibold">{{ redaction }}</span>
        </p>
        <p class="mt-2 text-xs font-medium text-rose-800 dark:text-rose-200">
            No Cockpit mutation route is authorized in Slice 26.
        </p>
    </section>
</template>
