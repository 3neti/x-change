<script setup lang="ts">
defineProps<{
    enabled?: boolean;
    runtimeEnabled?: boolean;
}>();
</script>

<template>
    <section
        class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/30"
        data-testid="cockpit-generate-action-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
            Issuance handoff status
        </p>
        <h3 class="mt-2 text-lg font-semibold text-emerald-950 dark:text-emerald-50">
            Quick Generate uses the existing GeneratePayCode path
        </h3>
        <p class="mt-3 text-sm leading-6 text-emerald-800 dark:text-emerald-200">
            The form above is the only operator submit control on this page. It
            hands off to the existing x-change issuance action and then renders
            an operator-safe result, claim URL, preflight summaries, and
            activity status.
        </p>

        <ul class="mt-4 grid gap-2 text-xs text-emerald-800 dark:text-emerald-200 sm:grid-cols-2">
            <li>Issuance owner remains GeneratePayCode</li>
            <li>Result panel is the primary operator feedback</li>
            <li>Journal, action, and feedback handoffs remain separately gated</li>
            <li>No raw payloads, wallet internals, or provider payloads are rendered</li>
        </ul>
    </section>
</template>
