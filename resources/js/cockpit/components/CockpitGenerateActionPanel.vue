<script setup lang="ts">
defineProps<{
    enabled?: boolean;
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-generate-action-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Generate Action
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Handoff placeholder
        </h3>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
            The generate action is intentionally disabled until a later slice wires existing
            issuance services, authorization, pricing, funding, and idempotency.
        </p>

        <button
            type="button"
            :disabled="!enabled"
            class="mt-5 w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 dark:bg-slate-100 dark:text-slate-950 dark:disabled:bg-slate-800 dark:disabled:text-slate-400"
            data-testid="cockpit-generate-button"
        >
            Generate Pay Code
        </button>

        <ul class="mt-4 space-y-2 text-xs text-slate-500 dark:text-slate-400">
            <li>No voucher generation</li>
            <li>No wallet debit or reservation</li>
            <li>No provider call</li>
            <li>No journal or feedback side effect</li>
        </ul>
    </section>
</template>

