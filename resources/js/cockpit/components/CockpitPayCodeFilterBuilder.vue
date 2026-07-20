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
        class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-filter-builder"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        Filter Details
                    </h3>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Read-only query criteria.
                    </p>
                </div>
                <dl
                    class="grid w-full grid-cols-3 gap-1.5 rounded-full bg-slate-50 p-1.5 text-center sm:w-72 dark:bg-slate-950"
                    data-testid="cockpit-pay-code-filter-density-summary"
                >
                    <div class="rounded-full bg-white px-3 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Active
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ activeFilterCount }}
                        </dd>
                    </div>
                    <div class="rounded-full bg-white px-3 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Context
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ contextFilterCount }}
                        </dd>
                    </div>
                    <div class="rounded-full bg-white px-3 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
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

        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
            Filtering uses normal GET navigation and only changes what the operator sees.
        </p>

        <div class="mt-3 grid gap-2 md:grid-cols-3">
            <article
                v-for="filter in filters"
                :key="filter.key"
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-950/60"
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
