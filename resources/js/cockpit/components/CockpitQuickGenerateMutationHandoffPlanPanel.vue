<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitQuickGenerateMutationHandoffPlan,
    CockpitQuickGenerateMutationHandoffPlanStep,
} from '../types';

const props = defineProps<{
    mutationHandoffPlan?: CockpitQuickGenerateMutationHandoffPlan;
}>();

type DisplayStep = {
    key: string;
    label: string;
    status: string;
    reason: string;
};

const steps = computed<DisplayStep[]>(() => {
    if (!Array.isArray(props.mutationHandoffPlan?.steps)) {
        return [];
    }

    return props.mutationHandoffPlan.steps
        .map((step): DisplayStep | null => normalizeStep(step))
        .filter((step): step is DisplayStep => step !== null);
});

const status = computed<string>(() => {
    return displayValue(props.mutationHandoffPlan?.status, 'not_wired');
});

const redaction = computed<string>(() => {
    return displayValue(props.mutationHandoffPlan?.redactions?.payloads, 'mutation-handoff-plan-only');
});

function normalizeStep(step: CockpitQuickGenerateMutationHandoffPlanStep): DisplayStep | null {
    const key = displayValue(step.key, '');
    const label = displayValue(step.label, '');

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: displayValue(step.status, 'unknown'),
        reason: displayValue(step.reason, 'No mutation handoff diagnostic is available.'),
    };
}

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
        class="rounded-xl border border-violet-200 bg-violet-50 p-5 shadow-sm dark:border-violet-900/70 dark:bg-violet-950/30"
        data-testid="cockpit-quick-generate-mutation-handoff-plan-panel"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-700 dark:text-violet-300">
                    Mutation Handoff Diagnostics
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Quick Generate hands off to existing x-change issuance paths
                </h3>
            </div>
            <span class="rounded-full border border-violet-200 bg-white px-3 py-1 text-xs font-semibold text-violet-700 dark:border-violet-900 dark:bg-slate-950 dark:text-violet-300">
                {{ status }}
            </span>
        </div>

        <ul class="mt-4 grid gap-3">
            <li
                v-for="step in steps"
                :key="step.key"
                class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50"
                data-testid="cockpit-quick-generate-mutation-handoff-plan-step"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ step.label }}
                        </p>
                        <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">
                            {{ step.key }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="step.status === 'passed'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'"
                    >
                        {{ step.status }}
                    </span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ step.reason }}
                </p>
            </li>
        </ul>

        <p class="mt-4 text-xs text-slate-600 dark:text-slate-300">
            Redaction policy: <span class="font-semibold">{{ redaction }}</span>
        </p>
        <p class="mt-2 text-xs font-medium text-violet-800 dark:text-violet-200">
            Handoff diagnostics remain operator-safe and exclude raw request, wallet, provider, journal, action, and feedback payloads.
        </p>
    </section>
</template>
