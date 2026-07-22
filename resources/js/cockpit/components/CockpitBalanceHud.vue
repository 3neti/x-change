<script setup lang="ts">
import type { CockpitBalanceMetric } from '../types';

defineProps<{
    balances: CockpitBalanceMetric[];
}>();

const toneClass = (tone: CockpitBalanceMetric['tone'] = 'neutral'): string => {
    return {
        neutral: 'border-slate-200 bg-white text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100',
        healthy: 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
        warning: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
        critical: 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100',
    }[tone];
};
</script>

<template>
    <section
        aria-label="Cockpit balance HUD"
        class="grid gap-2 sm:grid-cols-2 xl:grid-cols-[4fr_6fr_4fr_8fr]"
        data-testid="cockpit-balance-hud"
    >
        <article
            v-for="balance in balances"
            :key="balance.key"
            :class="[
                'rounded-lg border px-3 py-2 shadow-sm',
                toneClass(balance.tone),
            ]"
            data-testid="cockpit-balance-metric"
        >
            <p
                class="whitespace-nowrap text-center text-[0.65rem] font-semibold uppercase tracking-[0.12em] opacity-70"
                data-testid="cockpit-balance-label"
            >
                {{ balance.label }}
            </p>
            <p
                class="mt-1 whitespace-nowrap text-center text-sm font-semibold tabular-nums"
                data-testid="cockpit-balance-value"
            >
                {{ balance.value }}
            </p>
        </article>
    </section>
</template>
