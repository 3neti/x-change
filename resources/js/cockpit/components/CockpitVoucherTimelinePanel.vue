<script setup lang="ts">
import type { CockpitVoucherTimelineItem } from '../types';

defineProps<{
    items: CockpitVoucherTimelineItem[];
}>();
</script>

<template>
    <details
        class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-timeline-panel"
    >
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <div>
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    Timeline
                </p>
                <h3 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                    Lifecycle timeline
                </h3>
            </div>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                {{ items.length }} events
            </span>
        </summary>

        <ol class="mt-3 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800">
            <li
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                data-testid="cockpit-voucher-timeline-item"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-950 dark:text-slate-50">
                            {{ item.label }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">
                            {{ item.description }}
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-semibold capitalize text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ item.source }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ item.timestamp }}
                </p>
            </li>
        </ol>
    </details>
</template>
