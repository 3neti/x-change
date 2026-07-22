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
        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-audit-panel"
    >
        <summary
            class="flex cursor-pointer list-none flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between"
            data-testid="cockpit-voucher-audit-summary"
        >
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    Follow-up status
                </p>
                <h3 class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                    Audit and follow-up details
                </h3>
            </div>
            <span
                class="w-fit rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                data-testid="cockpit-voucher-audit-density-summary"
            >
                {{ audits.length }} evidence · {{ connectedAuditCount }} connected · {{ disabledActionCount }} disabled follow-ups
            </span>
        </summary>

        <div class="mt-2 border-t border-slate-200 pt-2 dark:border-slate-800">
            <p
                class="text-xs leading-5 text-slate-600 dark:text-slate-300"
                data-testid="cockpit-voucher-audit-guidance"
            >
                Journal evidence and disabled follow-up guidance. This page does not execute actions or write audit entries.
            </p>
            <div class="mt-2 grid gap-2">
                <article
                    v-for="audit in audits"
                    :key="audit.id"
                    class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                    data-testid="cockpit-voucher-audit-item"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="font-semibold text-slate-950 dark:text-slate-50">
                            {{ audit.label }}
                        </p>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ audit.status }}
                        </span>
                    </div>
                    <p class="mt-1.5 text-xs leading-5 text-slate-600 dark:text-slate-300">
                        {{ audit.helper }}
                    </p>
                </article>
            </div>
        </div>

        <details
            class="mt-2 rounded-lg border border-dashed border-slate-300 p-3 dark:border-slate-700"
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
