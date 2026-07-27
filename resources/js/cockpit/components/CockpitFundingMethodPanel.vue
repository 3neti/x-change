<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
    testId: string;
}>();
</script>

<template>
    <article
        class="min-h-[18.5rem] overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-sm sm:min-h-[21.5rem] dark:border-sky-950 dark:bg-slate-900"
        :data-testid="testId"
    >
        <header
            class="grid min-h-28 grid-cols-[minmax(0,1fr)_auto] grid-rows-[auto_1fr] gap-x-3 gap-y-1 border-b border-slate-200 px-4 py-4 sm:min-h-24 sm:px-5 dark:border-slate-800"
            data-testid="funding-method-panel-header"
        >
            <h2
                class="min-w-0 self-center text-base font-semibold text-slate-950 dark:text-white"
            >
                {{ title }}
            </h2>
            <div
                class="col-start-2 row-start-1 flex min-h-9 flex-wrap items-center justify-end gap-2"
                data-testid="funding-method-panel-action"
            >
                <slot name="action" />
            </div>
            <p
                class="col-span-2 row-start-2 min-h-10 text-sm leading-5 text-slate-500 dark:text-slate-400"
            >
                {{ description }}
            </p>
        </header>

        <slot />
    </article>
</template>
