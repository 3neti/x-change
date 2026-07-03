<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitQuickGenerateValidationRedactionGate,
    CockpitQuickGenerateValidationRedactionGateCheck,
} from '../types';

const props = defineProps<{
    validationRedactionGate?: CockpitQuickGenerateValidationRedactionGate;
}>();

type DisplayCheck = {
    key: string;
    label: string;
    status: string;
    reason: string;
};

const checks = computed<DisplayCheck[]>(() => {
    if (!Array.isArray(props.validationRedactionGate?.checks)) {
        return [];
    }

    return props.validationRedactionGate.checks
        .map((check): DisplayCheck | null => normalizeCheck(check))
        .filter((check): check is DisplayCheck => check !== null);
});

const status = computed<string>(() => {
    return displayValue(props.validationRedactionGate?.status, 'not_wired');
});

const redaction = computed<string>(() => {
    return displayValue(props.validationRedactionGate?.redactions?.payloads, 'validation-redaction-gates-only');
});

function normalizeCheck(check: CockpitQuickGenerateValidationRedactionGateCheck): DisplayCheck | null {
    const key = displayValue(check.key, '');
    const label = displayValue(check.label, '');

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: displayValue(check.status, 'unknown'),
        reason: displayValue(check.reason, 'No validation or redaction diagnostic is available.'),
    };
}

function displayValue(value: unknown, fallback: string): string {
    if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
        return fallback;
    }

    const normalized = String(value).trim();

    return normalized === '' ? fallback : normalized;
}
</script>

<template>
    <section
        class="rounded-xl border border-rose-200 bg-rose-50 p-5 shadow-sm dark:border-rose-900/70 dark:bg-rose-950/30"
        data-testid="cockpit-quick-generate-validation-redaction-gate-panel"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-700 dark:text-rose-300">
                    Validation and Redaction Gate Baseline
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Request validation and payload redaction stay blocked until mutation wiring is explicitly approved
                </h3>
            </div>
            <span class="rounded-full border border-rose-200 bg-white px-3 py-1 text-xs font-semibold text-rose-700 dark:border-rose-900 dark:bg-slate-950 dark:text-rose-300">
                {{ status }}
            </span>
        </div>

        <ul class="mt-4 grid gap-3">
            <li
                v-for="check in checks"
                :key="check.key"
                class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50"
                data-testid="cockpit-quick-generate-validation-redaction-gate-check"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ check.label }}
                        </p>
                        <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">
                            {{ check.key }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="check.status === 'passed'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'"
                    >
                        {{ check.status }}
                    </span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ check.reason }}
                </p>
            </li>
        </ul>

        <p class="mt-4 text-xs text-slate-600 dark:text-slate-300">
            Redaction policy: <span class="font-semibold">{{ redaction }}</span>
        </p>
        <p class="mt-2 text-xs font-medium text-rose-800 dark:text-rose-200">
            Validation and redaction gates are read-only facts in Slice 23.
        </p>
    </section>
</template>
