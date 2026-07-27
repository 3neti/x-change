<script setup lang="ts">
import CockpitPayCodeExplorerPageController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodeExplorerPageController';
import { router } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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

const searchInput = ref<HTMLInputElement | null>(null);
const isFilteringStatus = ref(false);
const searchForm = CockpitPayCodeExplorerPageController.form();

const statusOptions = computed(() => {
    const options = (props.filters ?? []).filter(
        (filter) => filter.key === 'status',
    );

    if (options.length > 0) {
        return options;
    }

    return [
        {
            key: 'status',
            label: 'All',
            value: 'all',
            active: true,
            read_only: true,
        },
    ];
});

const activeFilters = computed(() => {
    const filters: Array<{ key: string; label: string }> = [];

    if (props.query && props.query.trim() !== '') {
        filters.push({
            key: 'search',
            label: `Search: ${props.query.trim()}`,
        });
    }

    if (props.statusFilter && props.statusFilter !== 'all') {
        filters.push({
            key: 'status',
            label: `Status: ${props.statusFilter}`,
        });
    }

    return filters;
});

function applyStatusFilter(event: Event): void {
    const select = event.target;

    if (!(select instanceof HTMLSelectElement)) {
        return;
    }

    const data: Record<string, string> = {};
    const search = (searchInput.value?.value ?? props.query ?? '').trim();

    if (search !== '') {
        data.search = search;
    }

    if (select.value !== 'all') {
        data.status = select.value;
    }

    for (const field of props.hiddenFields ?? []) {
        const name = field.name.trim();
        const value = field.value.trim();

        if (name !== '' && value !== '') {
            data[name] = value;
        }
    }

    router.get(CockpitPayCodeExplorerPageController.url(), data, {
        only: ['pay_codes_read_model'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onStart: () => {
            isFilteringStatus.value = true;
        },
        onFinish: () => {
            isFilteringStatus.value = false;
        },
    });
}
</script>

<template>
    <section
        class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800"
        data-testid="cockpit-pay-code-search-bar"
    >
        <h2 class="sr-only">Search Pay Codes</h2>
        <form
            :action="searchForm.action"
            class="grid gap-2 lg:grid-cols-[minmax(0,1fr)_12rem_auto_auto] lg:items-center"
            :method="searchForm.method"
        >
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
                    ref="searchInput"
                    :value="query ?? ''"
                    name="search"
                    type="search"
                    placeholder="Search by code, recipient, amount, campaign, or status..."
                    class="h-9 w-full rounded-full border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 transition outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:focus:border-emerald-500 dark:focus:ring-emerald-900/60"
                    data-testid="cockpit-pay-code-search-input"
                />
            </label>
            <label class="block">
                <span class="sr-only">Filter by status</span>
                <select
                    :value="statusFilter ?? 'all'"
                    :aria-busy="isFilteringStatus"
                    :disabled="isFilteringStatus"
                    name="status"
                    class="h-9 w-full rounded-full border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 transition outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 disabled:cursor-wait disabled:opacity-70 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:focus:border-emerald-500 dark:focus:ring-emerald-900/60"
                    data-testid="cockpit-pay-code-status-filter"
                    @change="applyStatusFilter"
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
                <span class="sr-only" aria-live="polite">
                    {{
                        isFilteringStatus
                            ? 'Applying status filter'
                            : 'Status filter ready'
                    }}
                </span>
            </label>
            <button
                type="submit"
                class="inline-flex h-9 items-center justify-center rounded-full border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                data-testid="cockpit-pay-code-filter-submit"
            >
                <Search aria-hidden="true" class="size-4" />
                Search
            </button>
            <a
                v-if="query || statusFilter"
                :href="clearHref ?? '/x/cockpit/pay-codes'"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-full border border-slate-200 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                data-testid="cockpit-pay-code-clear-filters"
            >
                <X aria-hidden="true" class="size-4" />
                Clear
            </a>
        </form>
        <div
            v-if="activeFilters.length > 0"
            class="mt-2 flex flex-wrap gap-2"
            data-testid="cockpit-pay-code-active-filter-summary"
        >
            <span
                v-for="filter in activeFilters"
                :key="filter.key"
                class="inline-flex max-w-full items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                data-testid="cockpit-pay-code-active-filter-chip"
            >
                <span class="truncate">{{ filter.label }}</span>
            </span>
        </div>
    </section>
</template>
