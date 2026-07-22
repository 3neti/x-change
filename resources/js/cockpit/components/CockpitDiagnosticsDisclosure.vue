<script setup lang="ts">
defineProps<{
    title: string;
    summary: string;
    eyebrow?: string;
    actionLabel?: string;
    compact?: boolean;
}>();
</script>

<template>
    <details
        :class="[
            'border border-dashed border-slate-300 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-950/40',
            compact ? 'rounded-xl px-4 py-3' : 'rounded-2xl p-4',
        ]"
        data-testid="cockpit-diagnostics-disclosure"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p :class="compact ? 'text-[0.65rem] tracking-[0.18em]' : 'text-xs tracking-[0.22em]'" class="font-semibold uppercase text-slate-500 dark:text-slate-400">
                        {{ eyebrow ?? 'Engineering history' }}
                    </p>
                    <h3 :class="compact ? 'mt-0.5' : 'mt-1'" class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ title }}
                    </h3>
                    <p :class="compact ? 'mt-0.5 leading-4' : 'mt-1 leading-5'" class="text-xs text-slate-600 dark:text-slate-400">
                        {{ summary }}
                    </p>
                </div>

                <span :class="compact ? 'px-2 py-0.5 text-[0.7rem]' : 'px-3 py-1 text-xs'" class="rounded-full border border-slate-300 font-medium text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    {{ actionLabel ?? 'Show diagnostic history' }}
                </span>
            </div>
        </summary>

        <div :class="compact ? 'mt-3 space-y-3 pt-3' : 'mt-4 space-y-4 pt-4'" class="border-t border-slate-200 dark:border-slate-800">
            <slot />
        </div>
    </details>
</template>
