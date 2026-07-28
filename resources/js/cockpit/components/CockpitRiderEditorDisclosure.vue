<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        description: string;
        status?: string;
        summary?: string;
        defaultOpen?: boolean;
    }>(),
    {
        status: 'Empty',
        summary: '',
        defaultOpen: false,
    },
);
</script>

<template>
    <details
        class="group rounded-xl border border-slate-200 bg-slate-50/80 p-3 transition-colors open:bg-white dark:border-slate-800 dark:bg-slate-900/50 dark:open:bg-slate-950"
        :open="defaultOpen"
    >
        <summary
            class="flex cursor-pointer list-none items-start justify-between gap-3"
        >
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h5
                        class="text-xs font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ title }}
                    </h5>
                    <span
                        class="rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ status }}
                    </span>
                </div>
                <p
                    class="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                >
                    {{ summary || description }}
                </p>
            </div>
            <span
                aria-hidden="true"
                class="mt-0.5 text-sm text-slate-400 transition-transform group-open:rotate-180"
            >
                ⌄
            </span>
        </summary>

        <div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <slot />
        </div>
    </details>
</template>
