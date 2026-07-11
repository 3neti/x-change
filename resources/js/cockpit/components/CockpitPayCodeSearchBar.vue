<script setup lang="ts">
import { computed } from 'vue';
import type { CockpitPayCodeExplorerFilter } from '../types';

const props = defineProps<{
    query?: string;
    statusFilter?: string | null;
    filters?: CockpitPayCodeExplorerFilter[];
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
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-search-bar"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Search
        </p>
        <form action="/x/cockpit/pay-codes" class="mt-3 grid gap-3 lg:grid-cols-[1fr_220px_auto]" method="get">
            <label class="block">
                <span class="sr-only">Search Pay Codes</span>
                <input
                    :value="query ?? ''"
                    name="search"
                    type="search"
                    placeholder="Search by Pay Code, recipient, template, status, or amount"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                    data-testid="cockpit-pay-code-search-input"
                />
            </label>
            <label class="block">
                <span class="sr-only">Filter by status</span>
                <select
                    :value="statusFilter ?? 'all'"
                    name="status"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
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
                class="rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                data-testid="cockpit-pay-code-filter-submit"
            >
                Apply filters
            </button>
        </form>
        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span data-testid="cockpit-pay-code-active-filter-summary">
                {{ activeSummary }}
            </span>
            <a
                v-if="query || statusFilter"
                href="/x/cockpit/pay-codes"
                class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-slate-950 dark:text-slate-200 dark:decoration-slate-600 dark:hover:text-white"
                data-testid="cockpit-pay-code-clear-filters"
            >
                Clear filters
            </a>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Filters use read-only GET navigation. They do not mutate vouchers, call providers, or move money.
        </p>
    </section>
</template>
