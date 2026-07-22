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
        class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-print-template-panel"
    >
        <summary class="flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    Print Templates
                </p>
                <h3 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                    Printable handout options
                </h3>
            </div>
            <dl
                class="flex flex-wrap gap-1.5 text-[0.7rem]"
                data-testid="cockpit-print-template-density-summary"
            >
                <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <dt>Templates</dt>
                    <dd class="font-semibold">{{ templates.length }}</dd>
                </div>
                <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <dt>Formats</dt>
                    <dd class="font-semibold">{{ formatSummary() }}</dd>
                </div>
            </dl>
        </summary>

        <div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <p class="max-w-2xl text-xs leading-5 text-slate-600 dark:text-slate-300">
                These are future handout ideas only. Cockpit does not generate PDFs, create files, or talk to printers from this workspace.
            </p>
        </div>

        <div class="mt-3 grid gap-2 md:grid-cols-3">
            <article
                v-for="template in templates"
                :key="template.key"
                class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                data-testid="cockpit-print-template"
            >
                <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ template.label }}
                </p>
                <p class="mt-1 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ template.format }}
                </p>
                <details
                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
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
