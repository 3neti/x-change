<script setup lang="ts">
import type { CockpitVoucherDistributionItem } from '../types';

defineProps<{
    items: CockpitVoucherDistributionItem[];
}>();
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
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ item.helper }}
                </p>
            </article>
        </div>
    </section>
</template>
