<script setup lang="ts">
defineProps<{
    enabled?: boolean;
    runtimeEnabled?: boolean;
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
            Existing issuance handoff
        </h3>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
            Quick Generate now submits through the approved Cockpit mutation route, which hands
            off to the existing x-change GeneratePayCode action. This panel is informational;
            use the form above to generate a Pay Code.
        </p>

        <button
            type="button"
            :disabled="!enabled"
            class="mt-5 w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 dark:bg-slate-100 dark:text-slate-950 dark:disabled:bg-slate-800 dark:disabled:text-slate-400"
            data-testid="cockpit-generate-button"
        >
            {{ runtimeEnabled ? 'Use Quick Generate form above' : 'Generate Pay Code' }}
        </button>

        <ul class="mt-4 space-y-2 text-xs text-slate-500 dark:text-slate-400">
            <li>Issuance owner remains GeneratePayCode</li>
            <li>Pricing and funding preflights are informational</li>
            <li>Journal, action, and feedback handoffs remain separately gated</li>
            <li>No raw payloads, wallet internals, or provider payloads are rendered</li>
        </ul>
    </section>
</template>
