<script setup lang="ts">
import { computed } from 'vue';
import CockpitGenerateActionPanel from '../components/CockpitGenerateActionPanel.vue';
import CockpitIssuanceBoundaryPanel from '../components/CockpitIssuanceBoundaryPanel.vue';
import CockpitPricingFundingSummary from '../components/CockpitPricingFundingSummary.vue';
import CockpitRuntimeInputPanel from '../components/CockpitRuntimeInputPanel.vue';
import CockpitTemplateSelector from '../components/CockpitTemplateSelector.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import {
    cockpitPricingFundingSummary,
    cockpitQuickGenerateTemplates,
    cockpitRuntimeInputs,
} from '../quickGenerateDefaults';
import type {
    CockpitPricingFundingSummary as CockpitPricingFundingSummaryType,
    CockpitQuickGeneratePageProps,
    CockpitQuickGenerateReadModelPricingSummary,
    CockpitQuickGenerateReadModelRuntimeInput,
    CockpitQuickGenerateReadModelTemplate,
    CockpitQuickGenerateTemplate,
    CockpitRuntimeInput,
} from '../types';

const props = defineProps<CockpitQuickGeneratePageProps>();

const readModelAvailable = computed<boolean>(() => {
    return props.quick_generate_read_model?.status === 'available'
        && props.quick_generate_read_model?.authorized === true;
});

const templates = computed<CockpitQuickGenerateTemplate[]>(() => {
    if (!readModelAvailable.value || !Array.isArray(props.quick_generate_read_model?.templates)) {
        return cockpitQuickGenerateTemplates;
    }

    const mapped = props.quick_generate_read_model.templates
        .map((template): CockpitQuickGenerateTemplate | null => sanitizeTemplate(template))
        .filter((template): template is CockpitQuickGenerateTemplate => template !== null);

    return mapped.length > 0 ? mapped : cockpitQuickGenerateTemplates;
});

const runtimeInputs = computed<CockpitRuntimeInput[]>(() => {
    if (!readModelAvailable.value || !Array.isArray(props.quick_generate_read_model?.runtime_inputs)) {
        return cockpitRuntimeInputs;
    }

    const mapped = props.quick_generate_read_model.runtime_inputs
        .map((input): CockpitRuntimeInput | null => sanitizeRuntimeInput(input))
        .filter((input): input is CockpitRuntimeInput => input !== null);

    return mapped.length > 0 ? mapped : cockpitRuntimeInputs;
});

const pricingSummaries = computed<CockpitPricingFundingSummaryType[]>(() => {
    if (!readModelAvailable.value || !Array.isArray(props.quick_generate_read_model?.pricing_summaries)) {
        return cockpitPricingFundingSummary;
    }

    const mapped = props.quick_generate_read_model.pricing_summaries
        .map((summary): CockpitPricingFundingSummaryType | null => sanitizePricingSummary(summary))
        .filter((summary): summary is CockpitPricingFundingSummaryType => summary !== null);

    return mapped.length > 0 ? mapped : cockpitPricingFundingSummary;
});

function sanitizeTemplate(template: CockpitQuickGenerateReadModelTemplate): CockpitQuickGenerateTemplate | null {
    const key = stringValue(template.key);
    const name = stringValue(template.name);

    if (!key || !name) {
        return null;
    }

    return {
        key,
        name,
        description: stringValue(template.description) ?? 'Template description unavailable.',
        profile: stringValue(template.profile) ?? 'catalog',
        estimatedTime: stringValue(template.estimated_time) ?? 'Pending runtime inputs',
        disabled: template.disabled === true,
    };
}

function sanitizeRuntimeInput(input: CockpitQuickGenerateReadModelRuntimeInput): CockpitRuntimeInput | null {
    const key = stringValue(input.key);
    const label = stringValue(input.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        value: stringValue(input.value) ?? 'Pending operator input',
        helper: stringValue(input.helper) ?? 'Input is presentation-only in this baseline.',
    };
}

function sanitizePricingSummary(summary: CockpitQuickGenerateReadModelPricingSummary): CockpitPricingFundingSummaryType | null {
    const key = stringValue(summary.key);
    const label = stringValue(summary.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        value: stringValue(summary.value) ?? 'Not calculated',
        helper: stringValue(summary.helper) ?? 'No pricing or funding behavior is executed here.',
    };
}

function stringValue(value: unknown): string | null {
    if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
        return null;
    }

    const normalized = String(value).trim();

    return normalized === '' ? null : normalized;
}
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
                    :templates="templates"
                    :selected-key="templates[0]?.key"
                />

                <CockpitRuntimeInputPanel :inputs="runtimeInputs" />

                <div class="space-y-4">
                    <CockpitPricingFundingSummary :summaries="pricingSummaries" />
                    <CockpitGenerateActionPanel :enabled="false" />
                    <CockpitIssuanceBoundaryPanel />
                </div>
            </div>
        </section>
    </CockpitLayout>
</template>
