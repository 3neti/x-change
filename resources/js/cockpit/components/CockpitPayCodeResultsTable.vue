<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type {
    CockpitPayCodeExplorerRecord,
    CockpitPayCodeRowAction,
} from '../types';

const props = defineProps<{
    records: CockpitPayCodeExplorerRecord[];
    actions: CockpitPayCodeRowAction[];
}>();

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
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-results-table"
    >
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Results
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        Pay Code results
                    </h3>
                </div>
                <dl
                    class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2 text-center dark:bg-slate-950"
                    data-testid="cockpit-pay-code-results-density-summary"
                >
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Rows
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ records.length }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Links
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ totalEnabledActionCount() }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Disabled
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
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
                            <div class="flex min-w-44 flex-col gap-2">
                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        v-for="action in enabledActions(record)"
                                        :key="action.key"
                                        :href="action.href ?? '#'"
                                        :title="action.reason ?? undefined"
                                        class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:border-emerald-700"
                                        data-testid="cockpit-pay-code-row-action-link"
                                    >
                                        {{ action.label }}
                                    </Link>
                                </div>
                                <details
                                    v-if="disabledActions(record).length > 0"
                                    class="group w-fit text-xs text-slate-500 dark:text-slate-400"
                                    data-testid="cockpit-pay-code-row-unavailable-actions"
                                >
                                    <summary class="cursor-pointer font-medium text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                                        {{ disabledActions(record).length }} unavailable
                                    </summary>
                                    <div class="mt-2 flex flex-wrap gap-2">
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

        <div
            v-if="records.length === 0"
            class="border-t border-slate-200 p-5 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400"
            data-testid="cockpit-pay-code-empty-state"
        >
            No Pay Codes available in the sanitized read model.
        </div>
    </section>
</template>
