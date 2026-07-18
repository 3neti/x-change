<script setup lang="ts">
import type { CockpitPipelineStage } from '../types';

const props = defineProps<{
    stages: CockpitPipelineStage[];
}>();

function stageCount(): number {
    return props.stages.length;
}

function nonZeroStageCount(): number {
    return props.stages.filter((stage) => {
        const numericValue = Number(stage.value);

        return Number.isFinite(numericValue) && numericValue > 0;
    }).length;
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-redemption-pipeline"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Claim Status
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Claim lifecycle summary
                </h3>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    Sanitized claim counts only. This panel does not approve, redeem, execute, or reconcile Pay Codes.
                </p>
            </div>
            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                Read-only
            </span>
        </div>

        <div
            class="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-3"
            data-testid="cockpit-pipeline-density-summary"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Claim Facts
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ stageCount() }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Active Counts
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ nonZeroStageCount() }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Execution
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    Not run here
                </p>
            </div>
        </div>

        <div class="mt-5 grid gap-2">
            <div
                v-for="stage in stages"
                :key="stage.key"
                class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-800"
                data-testid="cockpit-pipeline-stage"
            >
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    {{ stage.label }}
                </span>
                <span class="font-mono text-sm text-slate-500 dark:text-slate-400">
                    {{ stage.value }}
                </span>
            </div>
        </div>
    </section>
</template>
