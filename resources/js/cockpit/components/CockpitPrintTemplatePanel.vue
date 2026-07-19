<script setup lang="ts">
import type { CockpitPrintTemplate } from '../types';

const props = defineProps<{
    templates: CockpitPrintTemplate[];
}>();

function formatSummary(): string {
    const formats = new Set(props.templates.map((template) => template.format).filter(Boolean));

    if (formats.size === 0) {
        return 'No templates';
    }

    if (formats.size === 1) {
        return `${props.templates.length} planned templates`;
    }

    return Array.from(formats).join(', ');
}
</script>

<template>
    <details
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-print-template-panel"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Print Templates
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        Printable handout options
                    </h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                        These are future handout ideas only. Cockpit does not generate PDFs, create files, or talk to printers from this workspace.
                    </p>
                </div>
                <dl
                    class="flex flex-wrap gap-2 text-xs"
                    data-testid="cockpit-print-template-density-summary"
                >
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <dt>Templates</dt>
                        <dd class="font-semibold">{{ templates.length }}</dd>
                    </div>
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <dt>Formats</dt>
                        <dd class="font-semibold">{{ formatSummary() }}</dd>
                    </div>
                </dl>
            </div>
        </summary>

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
                <details
                    class="mt-3 text-xs text-slate-500 dark:text-slate-400"
                    data-testid="cockpit-print-template-disclosure"
                >
                    <summary class="cursor-pointer font-medium text-slate-600 dark:text-slate-300">
                        Template details
                    </summary>
                    <p class="mt-2 leading-5">
                        {{ template.helper }}
                    </p>
                </details>
            </article>
        </div>
    </details>
</template>
