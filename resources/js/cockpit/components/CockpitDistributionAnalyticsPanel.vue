<script setup lang="ts">
import type { CockpitDistributionMetric } from '../types';

const props = defineProps<{
    metrics: CockpitDistributionMetric[];
}>();

function metricSummary(): string {
    return `${props.metrics.length} read-only facts`;
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-distribution-analytics-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Operational Analytics
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Distribution status summary
        </h3>

        <div
            class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-950/40"
            data-testid="cockpit-distribution-analytics-density-summary"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Analytics Facts
            </p>
            <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                {{ metricSummary() }}
            </p>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <article
                v-for="metric in metrics"
                :key="metric.key"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-distribution-metric"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ metric.label }}
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    {{ metric.value }}
                </p>
                <details
                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                    data-testid="cockpit-distribution-metric-disclosure"
                >
                    <summary class="cursor-pointer font-medium text-slate-600 dark:text-slate-300">
                        Metric details
                    </summary>
                    <p class="mt-2 leading-5">
                        {{ metric.helper }}
                    </p>
                </details>
            </article>
        </div>
    </section>
</template>
