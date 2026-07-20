<script setup lang="ts">
import { computed } from 'vue';
import type { CockpitPayCodeExplorerFilter } from '../types';

const props = defineProps<{
    query?: string;
    statusFilter?: string | null;
    filters?: CockpitPayCodeExplorerFilter[];
    hiddenFields?: Array<{
        name: string;
        value: string;
    }>;
    clearHref?: string;
}>();

const statusOptions = computed(() => {
    const options = (props.filters ?? []).filter((filter) => filter.key === 'status');

    if (options.length > 0) {
        return options;
    }

    return [
        { key: 'status', label: 'All', value: 'all', active: true, read_only: true },
    ];
});

const activeSummary = computed(() => {
    const pieces = [];

    if (props.query && props.query.trim() !== '') {
        pieces.push(`search “${props.query.trim()}”`);
    }

    if (props.statusFilter && props.statusFilter !== 'all') {
        pieces.push(`status ${props.statusFilter}`);
    }

    return pieces.length > 0 ? `Filters: ${pieces.join(' · ')}` : 'Filters: all Pay Codes';
});
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-search-bar"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Search
                </p>
                <h3 class="mt-1 text-base font-semibold text-slate-950 dark:text-slate-50">
                    Find Pay Codes
                </h3>
            </div>
            <span
                class="inline-flex w-fit items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                data-testid="cockpit-pay-code-active-filter-summary"
            >
                {{ activeSummary }}
            </span>
        </div>
        <form action="/x/cockpit/pay-codes" class="mt-4 grid gap-2 lg:grid-cols-[minmax(0,1fr)_12rem_auto_auto] lg:items-center" method="get">
            <input
                v-for="field in hiddenFields ?? []"
                :key="field.name"
                :name="field.name"
                :value="field.value"
                type="hidden"
                data-testid="cockpit-pay-code-search-context-input"
            />
            <label class="block">
                <span class="sr-only">Search Pay Codes</span>
                <input
                    :value="query ?? ''"
                    name="search"
                    type="search"
                    placeholder="Search by Pay Code, recipient, template, status, or amount"
                    class="h-10 w-full rounded-full border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:focus:border-emerald-500 dark:focus:ring-emerald-900/60"
                    data-testid="cockpit-pay-code-search-input"
                />
            </label>
            <label class="block">
                <span class="sr-only">Filter by status</span>
                <select
                    :value="statusFilter ?? 'all'"
                    name="status"
                    class="h-10 w-full rounded-full border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:focus:border-emerald-500 dark:focus:ring-emerald-900/60"
                    data-testid="cockpit-pay-code-status-filter"
                >
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :selected="option.value === (statusFilter ?? 'all')"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
            <button
                type="submit"
                class="inline-flex h-10 items-center justify-center rounded-full border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                data-testid="cockpit-pay-code-filter-submit"
            >
                Apply
            </button>
            <a
                v-if="query || statusFilter"
                :href="clearHref ?? '/x/cockpit/pay-codes'"
                class="inline-flex h-10 items-center justify-center rounded-full border border-slate-200 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                data-testid="cockpit-pay-code-clear-filters"
            >
                Clear
            </a>
        </form>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Filters use read-only GET navigation. They do not mutate vouchers, call providers, or move money.
        </p>
    </section>
</template>
