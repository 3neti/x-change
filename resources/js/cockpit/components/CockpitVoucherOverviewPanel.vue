<script setup lang="ts">
import type { CockpitVoucherOverviewItem } from '../types';

defineProps<{
    items: CockpitVoucherOverviewItem[];
}>();
</script>

<template>
    <details
        class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-overview-panel"
    >
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <div>
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    Overview
                </p>
                <h3 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                    Pay Code facts
                </h3>
            </div>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                {{ items.length }} facts
            </span>
        </summary>

        <div class="mt-3 grid gap-2 border-t border-slate-200 pt-3 dark:border-slate-800 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="item in items"
                :key="item.key"
                class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                data-testid="cockpit-voucher-overview-item"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ item.label }}
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ item.value }}
                </p>
                <p v-if="item.helper" class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                    {{ item.helper }}
                </p>
            </article>
        </div>
    </details>
</template>
