<script setup lang="ts">
import type { CockpitRuntimeInput } from '../types';

defineProps<{
    inputs: CockpitRuntimeInput[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-runtime-input-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Runtime Inputs
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Operator input placeholders
        </h3>

        <div class="mt-5 space-y-3">
            <label
                v-for="input in inputs"
                :key="input.key"
                class="block rounded-lg border border-dashed border-slate-300 px-4 py-3 dark:border-slate-700"
                data-testid="cockpit-runtime-input"
            >
                <span class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ input.label }}
                </span>
                <span class="mt-2 block rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                    {{ input.value }}
                </span>
                <span class="mt-2 block text-xs text-slate-500 dark:text-slate-400">
                    {{ input.helper }}
                </span>
            </label>
        </div>
    </section>
</template>

