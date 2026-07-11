<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitQuickGenerateAuthorization,
    CockpitQuickGenerateAuthorizationGate,
} from '../types';

const props = defineProps<{
    authorization?: CockpitQuickGenerateAuthorization;
}>();

type DisplayGate = {
    key: string;
    label: string;
    status: string;
    reason: string;
};

const gates = computed<DisplayGate[]>(() => {
    if (!Array.isArray(props.authorization?.gates)) {
        return [];
    }

    return props.authorization.gates
        .map((gate): DisplayGate | null => normalizeGate(gate))
        .filter((gate): gate is DisplayGate => gate !== null);
});

const status = computed<string>(() => {
    return displayValue(props.authorization?.status, 'not_wired');
});

const redaction = computed<string>(() => {
    return displayValue(props.authorization?.redactions?.payloads, 'authorization-gates-only');
});

function normalizeGate(gate: CockpitQuickGenerateAuthorizationGate): DisplayGate | null {
    const key = displayValue(gate.key, '');
    const label = displayValue(gate.label, '');

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: displayValue(gate.status, 'unknown'),
        reason: displayValue(gate.reason, 'No authorization diagnostic is available.'),
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
        class="rounded-xl border border-violet-200 bg-violet-50 p-5 shadow-sm dark:border-violet-900/70 dark:bg-violet-950/30"
        data-testid="cockpit-quick-generate-authorization-gate-panel"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-700 dark:text-violet-300">
                    Authorization Runtime Diagnostics
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Quick Generate is authorized only through the existing issuance handoff
                </h3>
            </div>
            <span class="rounded-full border border-violet-200 bg-white px-3 py-1 text-xs font-semibold text-violet-700 dark:border-violet-900 dark:bg-slate-950 dark:text-violet-300">
                {{ status }}
            </span>
        </div>

        <ul class="mt-4 grid gap-3">
            <li
                v-for="gate in gates"
                :key="gate.key"
                class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50"
                data-testid="cockpit-quick-generate-authorization-gate"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ gate.label }}
                        </p>
                        <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">
                            {{ gate.key }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="gate.status === 'passed'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'"
                    >
                        {{ gate.status }}
                    </span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ gate.reason }}
                </p>
            </li>
        </ul>

        <p class="mt-4 text-xs text-slate-600 dark:text-slate-300">
            Redaction policy: <span class="font-semibold">{{ redaction }}</span>
        </p>
        <p class="mt-2 text-xs font-medium text-violet-800 dark:text-violet-200">
            Provider and money movement authority remain separately gated outside the Cockpit shell.
        </p>
    </section>
</template>
