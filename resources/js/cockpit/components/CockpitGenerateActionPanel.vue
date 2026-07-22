<script setup lang="ts">
defineProps<{
    enabled?: boolean;
    runtimeEnabled?: boolean;
}>();
</script>

<template>
    <details
        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/30"
        data-testid="cockpit-generate-action-panel"
    >
        <summary class="flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                    Issuance handoff status
                </p>
                <h3 class="mt-0.5 text-base font-semibold text-emerald-950 dark:text-emerald-50">
                    Quick Generate uses the existing GeneratePayCode path
                </h3>
            </div>
            <span class="w-fit rounded-full bg-white/80 px-2 py-0.5 text-[0.7rem] font-semibold text-emerald-700 dark:bg-slate-950/60 dark:text-emerald-200">
                4 safeguards
            </span>
        </summary>
        <div class="mt-3 border-t border-emerald-200 pt-3 dark:border-emerald-900/70">
            <p class="text-xs leading-5 text-emerald-800 dark:text-emerald-200">
            The form above is the only operator submit control on this page. It
            hands off to the existing x-change issuance action and then renders
            an operator-safe result, claim URL, preflight summaries, and
            activity status.
            </p>

            <ul class="mt-2 grid gap-1.5 text-xs text-emerald-800 dark:text-emerald-200 sm:grid-cols-2">
                <li>Issuance owner remains GeneratePayCode</li>
                <li>Result panel is the primary operator feedback</li>
                <li>Journal, action, and feedback handoffs remain separately gated</li>
                <li>No raw payloads, wallet internals, or provider payloads are rendered</li>
            </ul>
        </div>
    </details>
</template>
