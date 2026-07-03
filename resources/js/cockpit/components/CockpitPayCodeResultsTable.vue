<script setup lang="ts">
import type {
    CockpitPayCodeExplorerRecord,
    CockpitPayCodeRowAction,
} from '../types';

defineProps<{
    records: CockpitPayCodeExplorerRecord[];
    actions: CockpitPayCodeRowAction[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-results-table"
    >
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                Results
            </p>
            <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                Pay Code read-model placeholder
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Pay Code</th>
                        <th class="px-5 py-3">Template</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Owner</th>
                        <th class="px-5 py-3">Last Activity</th>
                        <th class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="record in records"
                        :key="record.code"
                        data-testid="cockpit-pay-code-row"
                    >
                        <td class="px-5 py-4 font-mono text-slate-950 dark:text-slate-50">
                            {{ record.code }}
                        </td>
                        <td class="px-5 py-4 text-slate-700 dark:text-slate-200">
                            {{ record.template }}
                        </td>
                        <td class="px-5 py-4 text-slate-700 dark:text-slate-200">
                            {{ record.amount }}
                        </td>
                        <td class="px-5 py-4">
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ record.status }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-700 dark:text-slate-200">
                            {{ record.owner }}
                        </td>
                        <td class="px-5 py-4 text-slate-500 dark:text-slate-400">
                            {{ record.lastActivity }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="action in actions"
                                    :key="action.key"
                                    :disabled="action.disabled"
                                    :title="action.reason"
                                    type="button"
                                    class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-300"
                                    data-testid="cockpit-pay-code-row-action"
                                >
                                    {{ action.label }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

