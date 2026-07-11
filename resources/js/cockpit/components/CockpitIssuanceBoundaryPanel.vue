<script setup lang="ts">
const requiredGates = [
    'Authorization',
    'Pricing',
    'Funding',
    'Idempotency',
    'Redaction',
];
</script>

<template>
    <section
        class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/70 dark:bg-amber-950/30"
        data-testid="cockpit-issuance-boundary-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700 dark:text-amber-300">
            Issuance Boundary Plan
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Generation hands off to the existing issuance owner
        </h3>
        <p class="mt-3 text-sm leading-6 text-slate-700 dark:text-slate-300">
            Existing issuance action: <span class="font-semibold">GeneratePayCode</span>.
            Cockpit uses the approved API/controller handoff and must not create a
            parallel issuance runtime or bypass established gates.
        </p>

        <div class="mt-4 rounded-lg border border-amber-200 bg-white/70 p-4 dark:border-amber-900/60 dark:bg-slate-950/50">
            <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                Gates that remain owned outside Cockpit
            </p>
            <ul class="mt-3 grid gap-2 text-sm text-slate-600 dark:text-slate-300 sm:grid-cols-2">
                <li
                    v-for="gate in requiredGates"
                    :key="gate"
                    class="rounded-md border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-950"
                >
                    {{ gate }}
                </li>
            </ul>
        </div>

        <p class="mt-4 text-xs font-medium text-amber-800 dark:text-amber-200">
            Historical Slice 17 note: no route was registered then; current Quick Generate uses the approved handoff route.
        </p>
    </section>
</template>
