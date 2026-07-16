<script setup lang="ts">
import CockpitDashboardMetricCard from './CockpitDashboardMetricCard.vue';
import type { CockpitDashboardMetric } from '../types';

defineProps<{
    metrics: CockpitDashboardMetric[];
}>();
</script>

<template>
    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-liquidity-hero"
    >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Funding Status
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Funding readiness
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    Read-only funding posture for issuance planning. Wallet lookups,
                    reservations, provider calls, and money movement stay outside this dashboard.
                </p>
            </div>

            <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:border-slate-700 dark:text-slate-300">
                Balance summary not connected
            </span>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <CockpitDashboardMetricCard
                v-for="metric in metrics"
                :key="metric.key"
                :metric="metric"
            />
        </div>
    </section>
</template>
