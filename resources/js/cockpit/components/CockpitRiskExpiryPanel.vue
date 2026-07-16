<script setup lang="ts">
import type { CockpitRiskSignal } from '../types';

defineProps<{
    signals: CockpitRiskSignal[];
}>();

const severityClass = (severity: CockpitRiskSignal['severity']): string => {
    return {
        watch: 'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100',
        warning: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
        critical: 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100',
    }[severity];
};
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-risk-expiry-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Risk and Expiry
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Risk signals
        </h3>

        <div class="mt-5 space-y-3">
            <article
                v-for="signal in signals"
                :key="signal.key"
                :class="[
                    'rounded-lg border px-3 py-3',
                    severityClass(signal.severity),
                ]"
                data-testid="cockpit-risk-signal"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold">{{ signal.label }}</p>
                    <span class="text-[0.65rem] font-semibold uppercase tracking-wide">
                        {{ signal.severity }}
                    </span>
                </div>
                <p class="mt-1 text-sm opacity-80">
                    {{ signal.value }}
                </p>
            </article>
        </div>
    </section>
</template>
