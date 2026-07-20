<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type {
    CockpitPayCodeExplorerRecord,
    CockpitPayCodeRowAction,
} from '../types';

const defaultVisibleRecordLimit = 25;
const visibleRecordLimitOptions = [10, 25, 50];

const props = defineProps<{
    records: CockpitPayCodeExplorerRecord[];
    actions: CockpitPayCodeRowAction[];
    visibleLimit?: number;
}>();

const selectedVisibleLimit = ref(
    props.visibleLimit && props.visibleLimit > 0
        ? props.visibleLimit
        : defaultVisibleRecordLimit,
);

const currentPage = ref(1);

const totalPages = computed(() =>
    Math.max(Math.ceil(props.records.length / selectedVisibleLimit.value), 1),
);

const firstVisibleRecordNumber = computed(() =>
    props.records.length === 0
        ? 0
        : (currentPage.value - 1) * selectedVisibleLimit.value + 1,
);

const lastVisibleRecordNumber = computed(() =>
    Math.min(currentPage.value * selectedVisibleLimit.value, props.records.length),
);

const visibleRecords = computed(() =>
    props.records.slice(firstVisibleRecordNumber.value - 1, lastVisibleRecordNumber.value),
);

const hiddenRecordCount = computed(() =>
    Math.max(props.records.length - visibleRecords.value.length, 0),
);

const isResultLimited = computed(() => hiddenRecordCount.value > 0);

const hasPreviousPage = computed(() => currentPage.value > 1);

const hasNextPage = computed(() => currentPage.value < totalPages.value);

watch(
    () => [props.records.length, selectedVisibleLimit.value],
    () => {
        currentPage.value = 1;
    },
);

function goToPreviousPage(): void {
    if (hasPreviousPage.value) {
        currentPage.value -= 1;
    }
}

function goToNextPage(): void {
    if (hasNextPage.value) {
        currentPage.value += 1;
    }
}

const scanFields = [
    {
        label: 'Identify',
        value: 'Pay Code',
        helper: 'Open the detail page before taking external action.',
    },
    {
        label: 'Assess',
        value: 'Status and amount',
        helper: 'Use sanitized list facts only.',
    },
    {
        label: 'Navigate',
        value: 'Detail or distribution',
        helper: 'Links only; no delivery or lifecycle mutation.',
    },
];

function rowActions(record: CockpitPayCodeExplorerRecord, fallbackActions: CockpitPayCodeRowAction[]): CockpitPayCodeRowAction[] {
    return record.actions && record.actions.length > 0 ? record.actions : fallbackActions;
}

function isEnabledAction(action: CockpitPayCodeRowAction): boolean {
    return action.enabled === true && typeof action.href === 'string' && action.href.trim() !== '';
}

function enabledActions(record: CockpitPayCodeExplorerRecord): CockpitPayCodeRowAction[] {
    return rowActions(record, props.actions).filter((action) => isEnabledAction(action));
}

function disabledActions(record: CockpitPayCodeExplorerRecord): CockpitPayCodeRowAction[] {
    return rowActions(record, props.actions).filter((action) => !isEnabledAction(action));
}

function enabledActionCount(record: CockpitPayCodeExplorerRecord): number {
    return enabledActions(record).length;
}

function disabledActionCount(record: CockpitPayCodeExplorerRecord): number {
    return disabledActions(record).length;
}

function totalEnabledActionCount(): number {
    return props.records.reduce((count, record) => count + enabledActionCount(record), 0);
}

function totalDisabledActionCount(): number {
    return props.records.reduce((count, record) => count + disabledActionCount(record), 0);
}

function displayStatus(status: string): string {
    return status
        .split(/[_\s-]+/)
        .filter((part) => part.trim() !== '')
        .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1).toLowerCase()}`)
        .join(' ');
}

function statusBadgeClass(status: string): string {
    const normalizedStatus = status.toLowerCase().replaceAll('_', '-');

    if (['active', 'issued', 'ready', 'redeemed', 'completed'].includes(normalizedStatus)) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800';
    }

    if (['awaiting-approval', 'pending', 'review'].includes(normalizedStatus)) {
        return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800';
    }

    if (['expired', 'failed', 'cancelled', 'canceled'].includes(normalizedStatus)) {
        return 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950 dark:text-rose-200 dark:ring-rose-800';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700';
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-results-table"
    >
        <div class="border-b border-slate-200 p-4 dark:border-slate-800">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Results
                    </p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        Pay Code results
                    </h3>
                </div>
                <dl
                    class="grid w-full grid-cols-2 gap-1.5 rounded-full bg-slate-50 p-1.5 text-center sm:w-[30rem] sm:grid-cols-4 dark:bg-slate-950"
                    data-testid="cockpit-pay-code-results-density-summary"
                >
                    <div class="min-w-0 rounded-full bg-white px-3 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Showing
                        </dt>
                        <dd class="mt-1 whitespace-nowrap font-mono text-sm font-semibold tabular-nums text-slate-950 dark:text-slate-50">
                            {{ firstVisibleRecordNumber }}–{{ lastVisibleRecordNumber }} of {{ records.length }}
                        </dd>
                    </div>
                    <div class="min-w-0 rounded-full bg-white px-3 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Total Rows
                        </dt>
                        <dd class="mt-1 whitespace-nowrap font-mono text-sm font-semibold tabular-nums text-slate-950 dark:text-slate-50">
                            {{ records.length }}
                        </dd>
                    </div>
                    <div class="min-w-0 rounded-full bg-white px-3 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Links
                        </dt>
                        <dd class="mt-1 whitespace-nowrap font-mono text-sm font-semibold tabular-nums text-slate-950 dark:text-slate-50">
                            {{ totalEnabledActionCount() }}
                        </dd>
                    </div>
                    <div class="min-w-0 rounded-full bg-white px-3 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Disabled
                        </dt>
                        <dd class="mt-1 whitespace-nowrap font-mono text-sm font-semibold tabular-nums text-slate-950 dark:text-slate-50">
                            {{ totalDisabledActionCount() }}
                        </dd>
                    </div>
                </dl>
            </div>

            <details
                class="mt-4 rounded-xl bg-slate-50 p-3 dark:bg-slate-950"
                data-testid="cockpit-pay-code-results-scan-guide"
            >
                <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    How to scan these rows
                </summary>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <article
                        v-for="field in scanFields"
                        :key="field.label"
                        class="rounded-lg bg-white p-3 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
                    >
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            {{ field.label }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ field.value }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">
                            {{ field.helper }}
                        </p>
                    </article>
                </div>
            </details>

            <div
                v-if="isResultLimited"
                class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                data-testid="cockpit-pay-code-result-limit-notice"
            >
                Showing {{ firstVisibleRecordNumber }}–{{ lastVisibleRecordNumber }} of {{ records.length }} Pay Codes.
                Use search or status filters to narrow the list; pagination changes only the browser view.
            </div>

            <nav
                v-if="isResultLimited"
                aria-label="Pay Code result pages"
                class="mt-3 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-2.5 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pay-code-result-pagination"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        Page {{ currentPage }} of {{ totalPages }}
                    </p>
                    <label
                        class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                        for="cockpit-pay-code-page-size"
                    >
                        Rows
                        <select
                            id="cockpit-pay-code-page-size"
                            v-model.number="selectedVisibleLimit"
                            class="h-9 rounded-full border border-slate-200 bg-white px-3 text-sm font-semibold normal-case tracking-normal text-slate-700 shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-emerald-500 dark:focus:ring-emerald-900/60"
                            data-testid="cockpit-pay-code-result-page-size"
                        >
                            <option
                                v-for="option in visibleRecordLimitOptions"
                                :key="option"
                                :value="option"
                            >
                                {{ option }} per page
                            </option>
                        </select>
                    </label>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        :disabled="!hasPreviousPage"
                        type="button"
                        class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                        data-testid="cockpit-pay-code-result-pagination-previous"
                        @click="goToPreviousPage"
                    >
                        Previous
                    </button>
                    <button
                        :disabled="!hasNextPage"
                        type="button"
                        class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                        data-testid="cockpit-pay-code-result-pagination-next"
                        @click="goToNextPage"
                    >
                        Next
                    </button>
                </div>
            </nav>
        </div>

        <div
            class="divide-y divide-slate-100 md:hidden dark:divide-slate-800"
            data-testid="cockpit-pay-code-mobile-results"
        >
            <article
                v-for="record in visibleRecords"
                :key="`mobile-${record.code}`"
                class="space-y-4 p-4"
                data-testid="cockpit-pay-code-mobile-row"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-base font-semibold text-slate-950 dark:text-slate-50">
                            {{ record.code }}
                        </p>
                        <p class="mt-1 truncate text-sm text-slate-600 dark:text-slate-300">
                            {{ record.template }}
                        </p>
                    </div>
                    <span
                        :class="statusBadgeClass(record.status)"
                        class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                        data-testid="cockpit-pay-code-mobile-status-badge"
                    >
                        {{ displayStatus(record.status) }}
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Amount
                        </dt>
                        <dd
                            class="mt-1 font-mono font-semibold tabular-nums text-slate-950 dark:text-slate-50"
                            data-testid="cockpit-pay-code-mobile-amount"
                        >
                            {{ record.amount }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Type / Template
                        </dt>
                        <dd class="mt-1 truncate font-semibold text-slate-950 dark:text-slate-50">
                            {{ record.template }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Created
                        </dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-200">
                            {{ record.createdAt ?? '—' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Expires
                        </dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-200">
                            {{ record.expiresAt ?? '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="grid gap-2 sm:grid-cols-2">
                    <Link
                        v-for="action in enabledActions(record)"
                        :key="action.key"
                        :href="action.href ?? '#'"
                        :title="action.reason ?? undefined"
                        class="inline-flex min-h-9 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-center text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:border-emerald-700"
                        data-testid="cockpit-pay-code-mobile-row-action-link"
                    >
                        {{ action.label }}
                    </Link>
                    <details
                        v-if="disabledActions(record).length > 0"
                        class="group sm:col-span-2"
                        data-testid="cockpit-pay-code-mobile-row-unavailable-actions"
                    >
                        <summary
                            class="inline-flex min-h-9 w-full cursor-pointer items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-center text-xs font-medium text-slate-500 transition hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                            data-testid="cockpit-pay-code-mobile-row-disabled-summary"
                        >
                            More
                            <span class="sr-only">
                                — {{ disabledActions(record).length }} unavailable actions
                            </span>
                        </summary>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <dl
                                class="grid w-full gap-2 rounded-lg bg-slate-50 p-2 text-left text-xs dark:bg-slate-900"
                                data-testid="cockpit-pay-code-mobile-row-secondary-facts"
                            >
                                <div>
                                    <dt class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                        Owner
                                    </dt>
                                    <dd class="mt-0.5 text-slate-700 dark:text-slate-200">
                                        {{ record.owner }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                        Last Activity
                                    </dt>
                                    <dd class="mt-0.5 text-slate-700 dark:text-slate-200">
                                        {{ record.lastActivity }}
                                    </dd>
                                </div>
                            </dl>
                            <button
                                v-for="action in disabledActions(record)"
                                :key="action.key"
                                :disabled="action.disabled !== false"
                                :title="action.reason ?? undefined"
                                type="button"
                                class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-300"
                                data-testid="cockpit-pay-code-mobile-row-action-disabled"
                            >
                                {{ action.label }}
                            </button>
                        </div>
                    </details>
                </div>
            </article>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Pay Code</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3">Type / Template</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Created</th>
                        <th class="px-5 py-3">Expires</th>
                        <th class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="record in visibleRecords"
                        :key="record.code"
                        data-testid="cockpit-pay-code-row"
                    >
                        <td class="px-5 py-4 font-mono text-slate-950 dark:text-slate-50">
                            {{ record.code }}
                        </td>
                        <td
                            class="px-5 py-4 text-right font-mono tabular-nums text-slate-700 dark:text-slate-200"
                            data-testid="cockpit-pay-code-amount"
                        >
                            {{ record.amount }}
                        </td>
                        <td class="px-5 py-4 text-slate-700 dark:text-slate-200">
                            {{ record.template }}
                        </td>
                        <td class="px-5 py-4">
                            <span
                                :class="statusBadgeClass(record.status)"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                                data-testid="cockpit-pay-code-status-badge"
                            >
                                {{ displayStatus(record.status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-700 dark:text-slate-200">
                            {{ record.createdAt ?? '—' }}
                        </td>
                        <td class="px-5 py-4 text-slate-500 dark:text-slate-400">
                            {{ record.expiresAt ?? '—' }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex w-52 flex-col gap-2">
                                <div class="grid grid-cols-1 gap-2">
                                    <Link
                                        v-for="action in enabledActions(record)"
                                        :key="action.key"
                                        :href="action.href ?? '#'"
                                        :title="action.reason ?? undefined"
                                        class="inline-flex min-h-8 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-center text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:border-emerald-700"
                                        data-testid="cockpit-pay-code-row-action-link"
                                    >
                                        {{ action.label }}
                                    </Link>
                                </div>
                                <details
                                    v-if="disabledActions(record).length > 0"
                                    class="group text-xs text-slate-500 dark:text-slate-400"
                                    data-testid="cockpit-pay-code-row-unavailable-actions"
                                >
                                    <summary class="flex min-h-8 cursor-pointer items-center justify-center rounded-full bg-slate-100 px-3 py-1 text-center font-medium text-slate-500 transition hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:text-slate-200">
                                        More
                                        <span class="sr-only">
                                            — {{ disabledActions(record).length }} unavailable actions
                                        </span>
                                    </summary>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <dl
                                            class="grid w-full gap-2 rounded-lg bg-slate-50 p-2 text-left text-xs dark:bg-slate-900"
                                            data-testid="cockpit-pay-code-row-secondary-facts"
                                        >
                                            <div>
                                                <dt class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                                    Owner
                                                </dt>
                                                <dd class="mt-0.5 text-slate-700 dark:text-slate-200">
                                                    {{ record.owner }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                                    Last Activity
                                                </dt>
                                                <dd class="mt-0.5 text-slate-700 dark:text-slate-200">
                                                    {{ record.lastActivity }}
                                                </dd>
                                            </div>
                                        </dl>
                                        <button
                                            v-for="action in disabledActions(record)"
                                            :key="action.key"
                                            :disabled="action.disabled !== false"
                                            :title="action.reason ?? undefined"
                                            type="button"
                                            class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-300"
                                            data-testid="cockpit-pay-code-row-action-disabled"
                                        >
                                            {{ action.label }}
                                        </button>
                                    </div>
                                </details>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="isResultLimited"
            aria-label="Pay Code result pages footer"
            class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950"
            data-testid="cockpit-pay-code-result-pagination-footer"
        >
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Showing {{ firstVisibleRecordNumber }}–{{ lastVisibleRecordNumber }} of {{ records.length }}
            </p>
            <div class="flex flex-wrap gap-2">
                <button
                    :disabled="!hasPreviousPage"
                    type="button"
                    class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                    data-testid="cockpit-pay-code-result-pagination-footer-previous"
                    @click="goToPreviousPage"
                >
                    Previous
                </button>
                <button
                    :disabled="!hasNextPage"
                    type="button"
                    class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                    data-testid="cockpit-pay-code-result-pagination-footer-next"
                    @click="goToNextPage"
                >
                    Next
                </button>
            </div>
        </nav>

        <div
            v-if="records.length === 0"
            class="border-t border-slate-200 p-5 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400"
            data-testid="cockpit-pay-code-empty-state"
        >
            No Pay Codes available in the sanitized read model.
        </div>
    </section>
</template>
