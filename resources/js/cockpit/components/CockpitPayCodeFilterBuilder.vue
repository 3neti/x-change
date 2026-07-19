<script setup lang="ts">
import { computed } from 'vue';
import type { CockpitPayCodeExplorerFilter } from '../types';

const props = defineProps<{
    filters: CockpitPayCodeExplorerFilter[];
}>();

const activeFilterCount = computed(() => props.filters.filter((filter) => filter.active === true).length);
const contextFilterCount = computed(() => props.filters.filter((filter) => filter.key.startsWith('campaign_')).length);
const visibleFilterCount = computed(() => props.filters.length);
</script>

<template>
    <details
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-filter-builder"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Filter Details
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        Read-only query criteria
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        Open this panel to inspect all filter metadata. Filtering uses normal GET navigation and only changes what the operator sees.
                    </p>
                </div>
                <dl
                    class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2 text-center dark:bg-slate-950"
                    data-testid="cockpit-pay-code-filter-density-summary"
                >
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Active
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ activeFilterCount }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Context
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ contextFilterCount }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Total
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ visibleFilterCount }}
                        </dd>
                    </div>
                </dl>
            </div>
        </summary>

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
    </details>
</template>
