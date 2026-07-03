<script setup lang="ts">
defineProps<{
    query?: string;
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-search-bar"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Search
        </p>
        <label class="mt-3 block">
            <span class="sr-only">Search Pay Codes</span>
            <input
                :value="query ?? ''"
                readonly
                type="search"
                placeholder="Search by Pay Code, recipient, template, status, or correlation ID"
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                data-testid="cockpit-pay-code-search-input"
            />
        </label>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Search UI is read-only until a host query endpoint and redaction policy are wired.
        </p>
    </section>
</template>

