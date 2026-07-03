<script setup lang="ts">
import type { CockpitPrintTemplate } from '../types';

defineProps<{
    templates: CockpitPrintTemplate[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-print-template-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Print Templates
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Print template placeholder
        </h3>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            <article
                v-for="template in templates"
                :key="template.key"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-print-template"
            >
                <p class="font-semibold text-slate-950 dark:text-slate-50">
                    {{ template.label }}
                </p>
                <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ template.format }}
                </p>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ template.helper }}
                </p>
            </article>
        </div>
    </section>
</template>
