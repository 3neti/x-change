<script setup lang="ts">
import type { CockpitDashboardMetric } from '../types';

defineProps<{
    metric: CockpitDashboardMetric;
}>();

const toneClass = (tone: CockpitDashboardMetric['tone'] = 'neutral'): string => {
    return {
        neutral: 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900',
        healthy: 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950',
        warning: 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
        critical: 'border-rose-200 bg-rose-50 dark:border-rose-900 dark:bg-rose-950',
    }[tone];
};
</script>

<template>
    <article
        :class="[
            'rounded-xl border p-4 shadow-sm',
            toneClass(metric.tone),
        ]"
        data-testid="cockpit-dashboard-metric-card"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            {{ metric.label }}
        </p>
        <p class="mt-3 text-lg font-semibold text-slate-950 dark:text-slate-50">
            {{ metric.value }}
        </p>
        <p v-if="metric.helper" class="mt-2 text-xs text-slate-600 dark:text-slate-300">
            {{ metric.helper }}
        </p>
    </article>
</template>

