<script setup lang="ts">
import type { CockpitPayCodeExplorerFilter } from '../types';

defineProps<{
    filters: CockpitPayCodeExplorerFilter[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-filter-builder"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Filter summary
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Current query criteria
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
            These cards show the filters applied to the current list. Filters use
            normal GET navigation and only change what the operator sees.
        </p>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            <article
                v-for="filter in filters"
                :key="filter.key"
                class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/60"
                data-testid="cockpit-pay-code-filter"
            >
                <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ filter.label }}
                </p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ filter.value }}
                </p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ filter.helper }}
                </p>
            </article>
        </div>
    </section>
</template>
