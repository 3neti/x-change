<script setup lang="ts">
import CockpitGenerateActionPanel from '../components/CockpitGenerateActionPanel.vue';
import CockpitPricingFundingSummary from '../components/CockpitPricingFundingSummary.vue';
import CockpitRuntimeInputPanel from '../components/CockpitRuntimeInputPanel.vue';
import CockpitTemplateSelector from '../components/CockpitTemplateSelector.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import {
    cockpitPricingFundingSummary,
    cockpitQuickGenerateTemplates,
    cockpitRuntimeInputs,
} from '../quickGenerateDefaults';
</script>

<template>
    <CockpitLayout active-navigation="quick-generate">
        <section class="space-y-6" data-testid="cockpit-quick-generate-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 3
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Quick Generate Foundation
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This screen establishes the template-first issuance workspace only.
                    It does not generate vouchers, calculate pricing, reserve funds, call providers,
                    write journal entries, send feedback, or move money.
                </p>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <CockpitTemplateSelector
                    :templates="cockpitQuickGenerateTemplates"
                    selected-key="money-changer"
                />

                <CockpitRuntimeInputPanel :inputs="cockpitRuntimeInputs" />

                <div class="space-y-4">
                    <CockpitPricingFundingSummary :summaries="cockpitPricingFundingSummary" />
                    <CockpitGenerateActionPanel :enabled="false" />
                </div>
            </div>
        </section>
    </CockpitLayout>
</template>

