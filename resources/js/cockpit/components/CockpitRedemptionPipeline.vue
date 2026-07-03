<script setup lang="ts">
import type { CockpitPipelineStage } from '../types';

defineProps<{
    stages: CockpitPipelineStage[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-redemption-pipeline"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Redemption Pipeline
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Lifecycle visibility placeholder
                </h3>
            </div>
            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                No execution
            </span>
        </div>

        <div class="mt-5 grid gap-2">
            <div
                v-for="stage in stages"
                :key="stage.key"
                class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-800"
                data-testid="cockpit-pipeline-stage"
            >
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    {{ stage.label }}
                </span>
                <span class="font-mono text-sm text-slate-500 dark:text-slate-400">
                    {{ stage.value }}
                </span>
            </div>
        </div>
    </section>
</template>

