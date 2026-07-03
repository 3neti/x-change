<script setup lang="ts">
import CockpitVoucherAuditPanel from '../components/CockpitVoucherAuditPanel.vue';
import CockpitVoucherDistributionPanel from '../components/CockpitVoucherDistributionPanel.vue';
import CockpitVoucherEvidencePanel from '../components/CockpitVoucherEvidencePanel.vue';
import CockpitVoucherOverviewPanel from '../components/CockpitVoucherOverviewPanel.vue';
import CockpitVoucherTimelinePanel from '../components/CockpitVoucherTimelinePanel.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import {
    cockpitVoucherAuditItems,
    cockpitVoucherDetailActions,
    cockpitVoucherDistributionItems,
    cockpitVoucherEvidenceItems,
    cockpitVoucherOverviewItems,
    cockpitVoucherTimelineItems,
} from '../voucherDetailDefaults';
</script>

<template>
    <CockpitLayout active-navigation="pay-codes">
        <section class="space-y-6" data-testid="cockpit-voucher-detail-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 5
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Voucher Detail Foundation
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This screen establishes a read-only single-voucher operator view. It does not mutate
                    vouchers, execute drivers, write journal entries, send feedback, call providers, or
                    move money.
                </p>
            </div>

            <CockpitVoucherOverviewPanel :items="cockpitVoucherOverviewItems" />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <CockpitVoucherTimelinePanel :items="cockpitVoucherTimelineItems" />
                <div class="space-y-6">
                    <CockpitVoucherEvidencePanel :items="cockpitVoucherEvidenceItems" />
                    <CockpitVoucherDistributionPanel :items="cockpitVoucherDistributionItems" />
                    <CockpitVoucherAuditPanel
                        :audits="cockpitVoucherAuditItems"
                        :actions="cockpitVoucherDetailActions"
                    />
                </div>
            </div>
        </section>
    </CockpitLayout>
</template>
