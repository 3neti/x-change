<script setup lang="ts">
import type { CockpitDistributionMetric } from '../types';

defineProps<{
    metrics: CockpitDistributionMetric[];
}>();
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
            Distribution analytics placeholder
        </h3>

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
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ metric.helper }}
                </p>
            </article>
        </div>
    </section>
</template>
