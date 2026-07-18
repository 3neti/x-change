<script setup lang="ts">
import type { CockpitRiskSignal } from '../types';

const props = defineProps<{
    signals: CockpitRiskSignal[];
}>();

function signalCount(): number {
    return props.signals.length;
}

function highestSeverityLabel(): string {
    if (props.signals.some((signal) => signal.severity === 'critical')) {
        return 'Critical';
    }

    if (props.signals.some((signal) => signal.severity === 'warning')) {
        return 'Warning';
    }

    if (props.signals.some((signal) => signal.severity === 'watch')) {
        return 'Watch';
    }

    return 'None';
}

const severityClass = (severity: CockpitRiskSignal['severity']): string => {
    return {
        watch: 'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100',
        warning: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
        critical: 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100',
    }[severity];
};

const severityLabel = (severity: CockpitRiskSignal['severity']): string => {
    return {
        watch: 'Watch',
        warning: 'Warning',
        critical: 'Critical',
    }[severity];
};
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-risk-expiry-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Review Queue
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Items that may need attention
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
            Read-only signals for operator triage. Review actions remain outside this dashboard.
        </p>

        <div
            class="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-2"
            data-testid="cockpit-risk-density-summary"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Signals
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ signalCount() }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Highest Severity
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ highestSeverityLabel() }}
                </p>
            </div>
        </div>

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
                        {{ severityLabel(signal.severity) }}
                    </span>
                </div>
                <p class="mt-1 text-sm opacity-80">
                    {{ signal.value }}
                </p>
            </article>
        </div>
    </section>
</template>
