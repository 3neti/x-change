<script setup lang="ts">
import type { CockpitQuickGenerateTemplate } from '../types';

defineProps<{
    templates: CockpitQuickGenerateTemplate[];
    selectedKey?: string;
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-template-selector"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Template Selector
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Start from institutional products
        </h3>

        <div class="mt-5 grid gap-3">
            <article
                v-for="template in templates"
                :key="template.key"
                :class="[
                    'rounded-lg border px-4 py-3',
                    template.key === selectedKey
                        ? 'border-slate-950 bg-slate-950 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-950'
                        : 'border-slate-200 bg-white text-slate-950 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50',
                    template.disabled ? 'opacity-60' : '',
                ]"
                data-testid="cockpit-template-option"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold">{{ template.name }}</p>
                        <p class="mt-1 text-sm opacity-75">{{ template.description }}</p>
                    </div>
                    <span class="rounded-full border px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide">
                        {{ template.profile }}
                    </span>
                </div>
                <p class="mt-3 text-xs opacity-70">
                    {{ template.estimatedTime }}
                </p>
            </article>
        </div>
    </section>
</template>

