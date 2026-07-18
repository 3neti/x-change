<script setup lang="ts">
import type { CockpitPrintTemplate } from '../types';

const props = defineProps<{
    templates: CockpitPrintTemplate[];
}>();

function formatSummary(): string {
    const formats = new Set(props.templates.map((template) => template.format).filter(Boolean));

    if (formats.size === 0) {
        return 'No formats';
    }

    return Array.from(formats).join(', ');
}
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
            Print asset readiness
        </h3>

        <div
            class="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-2"
            data-testid="cockpit-print-template-density-summary"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Templates
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ templates.length }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Formats
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ formatSummary() }}
                </p>
            </div>
        </div>

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
    </section>
</template>
