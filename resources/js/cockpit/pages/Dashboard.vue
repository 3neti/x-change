<script setup lang="ts">
import CockpitLayout from '../layouts/CockpitLayout.vue';

const placeholders = [
    {
        title: 'Liquidity Center',
        body: 'Read-only balance and funding runway surface. Real wallet/provider data remains deferred to host read models.',
    },
    {
        title: 'Redemption Pipeline',
        body: 'Issued, shared, opened, claimed, redeemed, disbursed, and reconciled states will be consumed from existing services.',
    },
    {
        title: 'Risk and Expiry',
        body: 'Expiry, stuck settlement, and funding risk indicators remain presentation-only in this shell slice.',
    },
    {
        title: 'Recent Activity',
        body: 'Journal, action, feedback, and execution facts require redaction-aware read models before broad exposure.',
    },
];
</script>

<template>
    <CockpitLayout active-navigation="dashboard">
        <section class="space-y-6" data-testid="cockpit-dashboard-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 1
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Cockpit Dashboard Shell
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This shell establishes the operator workspace only. It does not execute vouchers,
                    write journal entries, resolve workflow authority, send feedback, or move money.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="placeholder in placeholders"
                    :key="placeholder.title"
                    class="rounded-xl border border-dashed border-slate-300 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/80"
                    data-testid="cockpit-dashboard-placeholder"
                >
                    <h3 class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ placeholder.title }}
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        {{ placeholder.body }}
                    </p>
                </article>
            </div>
        </section>
    </CockpitLayout>
</template>

