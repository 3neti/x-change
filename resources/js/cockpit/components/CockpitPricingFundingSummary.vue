<script setup lang="ts">
import type { CockpitPricingFundingSummary } from '../types';

defineProps<{
    summaries: CockpitPricingFundingSummary[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pricing-funding-summary"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Pricing and Funding
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Preflight summary
        </h3>

        <div class="mt-5 space-y-3">
            <article
                v-for="summary in summaries"
                :key="summary.key"
                class="rounded-lg border border-slate-100 px-4 py-3 dark:border-slate-800"
                data-testid="cockpit-pricing-summary-item"
            >
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ summary.label }}
                    </p>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
                        {{ summary.value }}
                    </p>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ summary.helper }}
                </p>
            </article>
        </div>
    </section>
</template>
