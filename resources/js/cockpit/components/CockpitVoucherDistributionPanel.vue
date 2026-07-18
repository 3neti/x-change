<script setup lang="ts">
import type { CockpitVoucherDistributionItem } from '../types';

const props = defineProps<{
    items: CockpitVoucherDistributionItem[];
}>();

function statusCount(status: string): number {
    return props.items.filter((item) => item.status === status).length;
}

function statusSummary(): string {
    const statuses = Array.from(new Set(props.items.map((item) => item.status).filter(Boolean)));

    if (statuses.length === 0) {
        return 'No statuses';
    }

    return statuses
        .map((status) => `${status}: ${statusCount(status)}`)
        .join(' · ');
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-distribution-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Distribution
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Delivery status
        </h3>

        <div
            class="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-2"
            data-testid="cockpit-voucher-distribution-density-summary"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Channels
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ items.length }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Status Summary
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ statusSummary() }}
                </p>
            </div>
        </div>

        <div class="mt-5 grid gap-3">
            <article
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-voucher-distribution-item"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        {{ item.channel }}
                    </p>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ item.status }}
                    </span>
                </div>
                <details
                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                    data-testid="cockpit-voucher-distribution-item-disclosure"
                >
                    <summary class="cursor-pointer font-medium text-slate-600 dark:text-slate-300">
                        Channel details
                    </summary>
                    <p class="mt-2 leading-5">
                        {{ item.helper }}
                    </p>
                </details>
            </article>
        </div>
    </section>
</template>
