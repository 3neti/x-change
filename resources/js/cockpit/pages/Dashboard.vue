<script setup lang="ts">
import CockpitLiquidityHero from '../components/CockpitLiquidityHero.vue';
import CockpitRecentActivityPanel from '../components/CockpitRecentActivityPanel.vue';
import CockpitRedemptionPipeline from '../components/CockpitRedemptionPipeline.vue';
import CockpitRiskExpiryPanel from '../components/CockpitRiskExpiryPanel.vue';
import {
    cockpitDashboardMetrics,
    cockpitRecentActivityItems,
    cockpitRedemptionPipelineStages,
    cockpitRiskSignals,
} from '../dashboardDefaults';
import CockpitLayout from '../layouts/CockpitLayout.vue';
</script>

<template>
    <CockpitLayout active-navigation="dashboard">
        <section class="space-y-6" data-testid="cockpit-dashboard-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 2
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Cockpit Dashboard Foundation
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This dashboard foundation composes read-only operator panels. It does not execute vouchers,
                    write journal entries, resolve workflow authority, send feedback, call providers, or move money.
                </p>
            </div>

            <CockpitLiquidityHero :metrics="cockpitDashboardMetrics" />

            <div class="grid gap-4 xl:grid-cols-3">
                <CockpitRedemptionPipeline
                    class="xl:col-span-2"
                    :stages="cockpitRedemptionPipelineStages"
                />
                <CockpitRiskExpiryPanel :signals="cockpitRiskSignals" />
            </div>

            <CockpitRecentActivityPanel :items="cockpitRecentActivityItems" />
        </section>
    </CockpitLayout>
</template>
