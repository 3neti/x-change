<script setup lang="ts">
import type { CockpitActivityItem } from '../types';

defineProps<{
    items: CockpitActivityItem[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-recent-activity-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Recent Activity
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Redaction-aware activity placeholder
        </h3>

        <div class="mt-5 space-y-3">
            <article
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-slate-100 px-3 py-3 dark:border-slate-800"
                data-testid="cockpit-activity-item"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ item.label }}
                    </p>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ item.source }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ item.description }}
                </p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ item.timestamp }}
                </p>
            </article>
        </div>
    </section>
</template>

