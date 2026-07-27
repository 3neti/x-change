<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type {
    CockpitFundingActivityItem,
    CockpitFundingActivityReadModel,
} from '../types';

const props = withDefaults(
    defineProps<{
        activity: CockpitFundingActivityReadModel;
        initialFilter?: CockpitFundingActivityReadModel['filters'][number]['key'];
        processingKey?: string | null;
        copiedKey?: string | null;
    }>(),
    {
        initialFilter: 'all',
        processingKey: null,
        copiedKey: null,
    },
);

const emit = defineEmits<{
    viewInstructions: [item: CockpitFundingActivityItem];
    checkProvider: [item: CockpitFundingActivityItem];
    copyPayCode: [item: CockpitFundingActivityItem];
    approveReceipt: [item: CockpitFundingActivityItem];
}>();

const selectedFilter = ref(props.initialFilter);
const filteredItems = computed(() =>
    selectedFilter.value === 'all'
        ? props.activity.items
        : props.activity.items.filter(
              (item) => item.method === selectedFilter.value,
          ),
);

watch(
    () => props.initialFilter,
    (filter) => {
        selectedFilter.value = filter;
    },
);

function statusTone(status: CockpitFundingActivityItem['status']): string {
    return {
        awaiting_payment:
            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
        checking_provider:
            'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
        under_review:
            'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
        pay_code_ready:
            'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200',
        processing: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
        recognized:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        needs_attention:
            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
        declined:
            'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        expired:
            'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        cancelled:
            'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        reversed:
            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
    }[status];
}

function displayTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function actionLabel(
    item: CockpitFundingActivityItem,
    action: CockpitFundingActivityItem['action_keys'][number],
): string {
    if (props.processingKey === item.key) {
        return action === 'check_provider' ? 'Checking…' : 'Working…';
    }

    if (action === 'copy_pay_code' && props.copiedKey === item.key) {
        return 'Copied';
    }

    return {
        view_instructions: 'Instructions',
        check_provider: 'Check NetBank',
        copy_pay_code: 'Copy Pay Code',
        approve_receipt: 'Review',
    }[action];
}

function performAction(
    item: CockpitFundingActivityItem,
    action: CockpitFundingActivityItem['action_keys'][number],
): void {
    if (action === 'view_instructions') {
        emit('viewInstructions', item);
    } else if (action === 'check_provider') {
        emit('checkProvider', item);
    } else if (action === 'copy_pay_code') {
        emit('copyPayCode', item);
    } else {
        emit('approveReceipt', item);
    }
}
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="funding-activity"
    >
        <header
            class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
        >
            <div>
                <h2 class="text-base font-semibold">Funding Activity</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Requests, provider observations, and recognized funds.
                </p>
            </div>
            <span class="text-xs font-semibold text-slate-500">
                {{ filteredItems.length }}
            </span>
        </header>

        <nav
            class="flex gap-2 overflow-x-auto border-b border-slate-200 px-4 py-3 dark:border-slate-800"
            aria-label="Filter Funding Activity"
        >
            <button
                v-for="filter in activity.filters"
                :key="filter.key"
                type="button"
                class="h-8 shrink-0 rounded-full border px-3 text-xs font-semibold transition"
                :class="
                    selectedFilter === filter.key
                        ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-950'
                        : 'border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'
                "
                :aria-pressed="selectedFilter === filter.key"
                :data-testid="`funding-activity-filter-${filter.key}`"
                @click="selectedFilter = filter.key"
            >
                {{ filter.label }}
            </button>
        </nav>

        <div v-if="filteredItems.length">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <caption class="sr-only">
                        Account Funding requests, observations, and recognition
                        status
                    </caption>
                    <thead
                        class="bg-slate-50 text-xs font-semibold text-slate-500 dark:bg-slate-950 dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-4 py-2.5">Method</th>
                            <th class="px-4 py-2.5">Reference</th>
                            <th class="px-4 py-2.5">Amount</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Updated</th>
                            <th class="px-4 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in filteredItems"
                            :key="item.key"
                            class="border-t border-slate-100 align-top dark:border-slate-800"
                            :data-testid="`funding-activity-row-${item.key}`"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ item.method_label }}
                            </td>
                            <td class="px-4 py-3">
                                <code
                                    class="text-xs font-semibold tracking-wide"
                                >
                                    {{ item.display_reference }}
                                </code>
                                <p
                                    v-if="
                                        item.summary &&
                                        item.summary !== item.display_reference
                                    "
                                    class="mt-1 max-w-56 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.summary }}
                                </p>
                            </td>
                            <td class="px-4 py-3 font-semibold">
                                {{ item.amount }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-[0.65rem] font-semibold uppercase"
                                    :class="statusTone(item.status)"
                                >
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td
                                class="px-4 py-3 text-slate-600 dark:text-slate-300"
                            >
                                {{ displayTime(item.updated_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        v-for="action in item.action_keys"
                                        :key="action"
                                        type="button"
                                        class="h-8 rounded-lg border border-slate-300 px-3 text-xs font-semibold transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800"
                                        :disabled="processingKey !== null"
                                        :data-testid="`funding-activity-action-${action}-${item.key}`"
                                        @click="performAction(item, action)"
                                    >
                                        {{ actionLabel(item, action) }}
                                    </button>
                                    <span
                                        v-if="item.action_keys.length === 0"
                                        class="text-xs text-slate-400"
                                    >
                                        —
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul
                class="divide-y divide-slate-100 md:hidden dark:divide-slate-800"
                data-testid="funding-activity-mobile-list"
            >
                <li
                    v-for="item in filteredItems"
                    :key="item.key"
                    class="grid gap-3 p-4"
                    :data-testid="`funding-activity-card-${item.key}`"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-500">
                                {{ item.method_label }}
                            </p>
                            <code
                                class="mt-1 block truncate text-xs font-semibold tracking-wide"
                            >
                                {{ item.display_reference }}
                            </code>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-[0.65rem] font-semibold uppercase"
                            :class="statusTone(item.status)"
                        >
                            {{ item.status_label }}
                        </span>
                    </div>
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ item.amount }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ displayTime(item.updated_at) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button
                                v-for="action in item.action_keys"
                                :key="action"
                                type="button"
                                class="h-9 rounded-lg border border-slate-300 px-3 text-xs font-semibold disabled:opacity-50 dark:border-slate-700"
                                :disabled="processingKey !== null"
                                @click="performAction(item, action)"
                            >
                                {{ actionLabel(item, action) }}
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <p v-else class="px-4 py-8 text-center text-sm text-slate-500">
            No Funding Activity yet.
        </p>
    </section>
</template>
