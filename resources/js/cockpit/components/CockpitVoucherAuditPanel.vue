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
        class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-audit-panel"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-2">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            Follow-up status
                        </p>
                        <h3 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                            Audit and follow-up details
                        </h3>
                    </div>
                    <dl
                        class="flex flex-wrap gap-1.5 text-[0.7rem]"
                        data-testid="cockpit-voucher-audit-density-summary"
                    >
                        <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <dt>Evidence</dt>
                            <dd class="font-semibold">{{ audits.length }}</dd>
                        </div>
                        <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <dt>Connected</dt>
                            <dd class="font-semibold">{{ connectedAuditCount }}</dd>
                        </div>
                        <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <dt>Disabled Follow-Ups</dt>
                            <dd class="font-semibold">{{ disabledActionCount }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                        View details
                    </p>
                    <p class="max-w-2xl text-xs leading-5 text-slate-600 dark:text-slate-300">
                        Journal evidence and disabled follow-up guidance. This page does not execute actions or write audit entries.
                    </p>
                </div>
            </div>
        </summary>

        <div class="mt-3 grid gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
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

        <details
            class="mt-3 rounded-lg border border-dashed border-slate-300 p-3 dark:border-slate-700"
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
