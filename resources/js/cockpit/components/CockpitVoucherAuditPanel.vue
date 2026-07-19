<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitVoucherAuditItem,
    CockpitVoucherDetailAction,
} from '../types';

const props = defineProps<{
    audits: CockpitVoucherAuditItem[];
    actions: CockpitVoucherDetailAction[];
}>();

const disabledActionCount = computed(() => props.actions.filter((action) => action.disabled !== false).length);
const availableActionCount = computed(() => props.actions.length - disabledActionCount.value);
const connectedAuditCount = computed(() => props.audits.filter((audit) => audit.status === 'available' || audit.status === 'Available').length);
</script>

<template>
    <details
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-audit-panel"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Follow-up status
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        Audit and follow-up details
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        Open this panel to inspect journal evidence and disabled follow-up guidance. This page still does not execute actions or write audit entries.
                    </p>
                </div>
                <dl
                    class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2 text-center text-sm dark:bg-slate-950"
                    data-testid="cockpit-voucher-audit-density-summary"
                >
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Evidence
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ audits.length }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Connected
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ connectedAuditCount }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800">
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Follow-Ups
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ disabledActionCount }}
                        </dd>
                    </div>
                </dl>
            </div>
        </summary>

        <div class="mt-5 grid gap-3">
            <article
                v-for="audit in audits"
                :key="audit.id"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-voucher-audit-item"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        {{ audit.label }}
                    </p>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ audit.status }}
                    </span>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ audit.helper }}
                </p>
            </article>
        </div>

        <details
            class="mt-6 rounded-lg border border-dashed border-slate-300 p-4 dark:border-slate-700"
            data-testid="cockpit-voucher-disabled-actions-disclosure"
        >
            <summary class="cursor-pointer text-sm font-semibold text-slate-950 dark:text-slate-50">
                Follow-up actions are disabled from this page.
            </summary>
            <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                {{ availableActionCount }} executable follow-ups are available here. {{ disabledActionCount }} follow-ups are shown as read-only guidance.
            </p>
            <div class="mt-3 grid gap-2">
                <div
                    v-for="action in actions"
                    :key="action.key"
                    class="rounded-md border border-slate-200 p-3 dark:border-slate-700"
                >
                    <button
                        :disabled="action.disabled"
                        :title="action.reason"
                        type="button"
                        class="rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-300"
                        data-testid="cockpit-voucher-detail-action"
                    >
                        {{ action.label }}
                    </button>
                    <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                        {{ action.reason }}
                    </p>
                </div>
            </div>
        </details>
    </details>
</template>
