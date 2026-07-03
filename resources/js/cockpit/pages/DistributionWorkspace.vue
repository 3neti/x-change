<script setup lang="ts">
import CockpitDigitalDistributionPanel from '../components/CockpitDigitalDistributionPanel.vue';
import CockpitDistributionAnalyticsPanel from '../components/CockpitDistributionAnalyticsPanel.vue';
import CockpitPrintTemplatePanel from '../components/CockpitPrintTemplatePanel.vue';
import CockpitShareQrPanel from '../components/CockpitShareQrPanel.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import {
    cockpitDistributionActions,
    cockpitDistributionChannels,
    cockpitDistributionMetrics,
    cockpitPrintTemplates,
    cockpitShareAssets,
} from '../distributionWorkspaceDefaults';
</script>

<template>
    <CockpitLayout active-navigation="pay-codes">
        <section class="space-y-6" data-testid="cockpit-distribution-workspace-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 6
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Distribution Workspace Foundation
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This screen establishes read-only distribution planning placeholders. It does not
                    dispatch distribution, send feedback, create campaigns, mutate vouchers, execute
                    drivers, write journal entries, call providers, or move money.
                </p>
            </div>

            <CockpitDigitalDistributionPanel
                :channels="cockpitDistributionChannels"
                :actions="cockpitDistributionActions"
            />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                <div class="space-y-6">
                    <CockpitPrintTemplatePanel :templates="cockpitPrintTemplates" />
                    <CockpitDistributionAnalyticsPanel :metrics="cockpitDistributionMetrics" />
                </div>
                <CockpitShareQrPanel :assets="cockpitShareAssets" />
            </div>
        </section>
    </CockpitLayout>
</template>
