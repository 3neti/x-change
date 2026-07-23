<script setup lang="ts">
import CockpitDashboardMetricCard from './CockpitDashboardMetricCard.vue';
import type { CockpitDashboardMetric } from '../types';

const props = defineProps<{
    metrics: CockpitDashboardMetric[];
}>();

const fundingSemantics = [
    {
        label: 'Accounting',
        value: 'Internal Balance',
        helper: 'Current wallet balance visible to Cockpit.',
    },
    {
        label: 'Liability',
        value: 'Outstanding Pay Codes',
        helper: 'Active unredeemed Pay Code estimate.',
    },
    {
        label: 'Estimate',
        value: 'Issuance Capacity',
        helper: 'Internal Balance capped by provider liquidity after Outstanding Pay Codes.',
    },
    {
        label: 'External',
        value: 'Provider Liquidity',
        helper: 'Cached provider summary; dashboard page loads do not call the provider.',
    },
];

function metricCount(): number {
    return props.metrics.length;
}
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
                    Read-only funding posture for issuance planning. Issuance Capacity is capped by
                    provider liquidity and accounts for Outstanding Pay Codes. Cockpit does not
                    reserve, release, capture, refund, or move money here.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 lg:justify-end">
                <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    Bridge estimates
                </span>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                    Treasury facts deferred
                </span>
            </div>
        </div>

        <div
            class="mt-5 grid gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/60 sm:grid-cols-3"
            data-testid="cockpit-funding-density-summary"
        >
            <div>
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    Funding Facts
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ metricCount() }}
                </p>
            </div>
            <div>
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    Semantics
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ fundingSemantics.length }}
                </p>
            </div>
            <div>
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    Money Movement
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                    Disabled
                </p>
            </div>
        </div>

        <details
            class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/60"
            data-testid="cockpit-funding-semantics"
        >
            <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">
                Funding semantics details
            </summary>
            <div
                class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                <article
                    v-for="semantic in fundingSemantics"
                    :key="semantic.label"
                    class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
                >
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                        {{ semantic.label }}
                    </p>
                    <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ semantic.value }}
                    </p>
                    <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">
                        {{ semantic.helper }}
                    </p>
                </article>
            </div>
        </details>

        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <CockpitDashboardMetricCard
                v-for="metric in metrics"
                :key="metric.key"
                :metric="metric"
            />
        </div>
    </section>
</template>
