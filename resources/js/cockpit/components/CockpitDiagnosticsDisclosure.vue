<script setup lang="ts">
defineProps<{
    title: string;
    summary: string;
}>();
</script>

<template>
    <details
        class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-950/40"
        data-testid="cockpit-diagnostics-disclosure"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                        Engineering history
                    </p>
                    <h3 class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ title }}
                    </h3>
                    <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-400">
                        {{ summary }}
                    </p>
                </div>

                <span class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    Show diagnostic history
                </span>
            </div>
        </summary>

        <div class="mt-4 space-y-4 border-t border-slate-200 pt-4 dark:border-slate-800">
            <slot />
        </div>
    </details>
</template>
