<script setup lang="ts">
import type { CockpitVoucherTimelineItem } from '../types';

defineProps<{
    items: CockpitVoucherTimelineItem[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-timeline-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Timeline
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Lifecycle facts placeholder
        </h3>

        <ol class="mt-5 space-y-4">
            <li
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-voucher-timeline-item"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-950 dark:text-slate-50">
                            {{ item.label }}
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            {{ item.description }}
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold capitalize text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ item.source }}
                    </span>
                </div>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ item.timestamp }}
                </p>
            </li>
        </ol>
    </section>
</template>
