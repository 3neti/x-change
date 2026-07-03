<script setup lang="ts">
import type { CockpitVoucherOverviewItem } from '../types';

defineProps<{
    items: CockpitVoucherOverviewItem[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-overview-panel"
    >
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                Overview
            </p>
            <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                Voucher read-model placeholder
            </h3>
        </div>

        <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="item in items"
                :key="item.key"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-voucher-overview-item"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ item.label }}
                </p>
                <p class="mt-2 text-base font-semibold text-slate-950 dark:text-slate-50">
                    {{ item.value }}
                </p>
                <p v-if="item.helper" class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                    {{ item.helper }}
                </p>
            </article>
        </div>
    </section>
</template>
