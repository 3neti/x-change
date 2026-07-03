<script setup lang="ts">
import { computed } from 'vue';
import CockpitGenerateActionPanel from '../components/CockpitGenerateActionPanel.vue';
import CockpitIssuanceBoundaryPanel from '../components/CockpitIssuanceBoundaryPanel.vue';
import CockpitPricingFundingSummary from '../components/CockpitPricingFundingSummary.vue';
import CockpitQuickGenerateAuthorizationGatePanel from '../components/CockpitQuickGenerateAuthorizationGatePanel.vue';
import CockpitQuickGenerateDraftContractPanel from '../components/CockpitQuickGenerateDraftContractPanel.vue';
import CockpitQuickGeneratePricingGatePanel from '../components/CockpitQuickGeneratePricingGatePanel.vue';
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
    CockpitQuickGenerateAuthorization,
    CockpitQuickGenerateAuthorizationGate,
    CockpitQuickGenerateDraftContract,
    CockpitQuickGeneratePricingGate,
    CockpitQuickGeneratePricingGateCheck,
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

const draftContract = computed<CockpitQuickGenerateDraftContract>(() => {
    const draft = props.quick_generate_read_model?.draft_contract;

    if (!readModelAvailable.value || typeof draft !== 'object' || draft === null) {
        return defaultDraftContract();
    }

    return {
        schema: stringValue(draft.schema) ?? 'x-change.cockpit.quick-generate-draft.v1',
        status: stringValue(draft.status) ?? 'draft_only',
        template_key: stringValue(draft.template_key),
        amount: stringValue(draft.amount),
        currency: stringValue(draft.currency),
        recipient_reference: stringValue(draft.recipient_reference),
        purpose: stringValue(draft.purpose),
        idempotency_key: stringValue(draft.idempotency_key),
        redactions: {
            payloads: stringValue(draft.redactions?.payloads) ?? 'draft-shape-only',
        },
    };
});

const pricingGate = computed<CockpitQuickGeneratePricingGate>(() => {
    const pricingGateReadModel = props.quick_generate_read_model?.pricing_gate;

    if (!readModelAvailable.value || typeof pricingGateReadModel !== 'object' || pricingGateReadModel === null) {
        return defaultPricingGate();
    }

    const checks = Array.isArray(pricingGateReadModel.checks)
        ? pricingGateReadModel.checks
            .map((check): CockpitQuickGeneratePricingGateCheck | null => sanitizePricingGateCheck(check))
            .filter((check): check is CockpitQuickGeneratePricingGateCheck => check !== null)
        : [];

    return {
        status: stringValue(pricingGateReadModel.status) ?? 'blocked',
        checks: checks.length > 0 ? checks : defaultPricingGate().checks,
        redactions: {
            payloads: stringValue(pricingGateReadModel.redactions?.payloads) ?? 'pricing-gates-only',
        },
    };
});

const authorization = computed<CockpitQuickGenerateAuthorization>(() => {
    const authorizationReadModel = props.quick_generate_read_model?.authorization;

    if (!readModelAvailable.value || typeof authorizationReadModel !== 'object' || authorizationReadModel === null) {
        return defaultAuthorization();
    }

    const gates = Array.isArray(authorizationReadModel.gates)
        ? authorizationReadModel.gates
            .map((gate): CockpitQuickGenerateAuthorizationGate | null => sanitizeAuthorizationGate(gate))
            .filter((gate): gate is CockpitQuickGenerateAuthorizationGate => gate !== null)
        : [];

    return {
        status: stringValue(authorizationReadModel.status) ?? 'blocked',
        gates: gates.length > 0 ? gates : defaultAuthorization().gates,
        redactions: {
            payloads: stringValue(authorizationReadModel.redactions?.payloads) ?? 'authorization-gates-only',
        },
    };
});

function defaultDraftContract(): CockpitQuickGenerateDraftContract {
    return {
        schema: 'x-change.cockpit.quick-generate-draft.v1',
        status: 'draft_only',
        template_key: 'money-changer',
        amount: null,
        currency: 'PHP',
        recipient_reference: null,
        purpose: null,
        idempotency_key: null,
        redactions: {
            payloads: 'draft-shape-only',
        },
    };
}

function defaultPricingGate(): CockpitQuickGeneratePricingGate {
    return {
        status: 'blocked',
        checks: [
            {
                key: 'template-selected',
                label: 'Template Selected',
                status: 'passed',
                reason: 'The default Quick Generate template is visible as a read-only fact.',
            },
            {
                key: 'amount-input-present',
                label: 'Amount Input Present',
                status: 'blocked',
                reason: 'No operator amount input is accepted by Cockpit in Slice 20.',
            },
            {
                key: 'pricing-service-wired',
                label: 'Pricing Service Wired',
                status: 'blocked',
                reason: 'Cockpit does not call pricing services in Slice 20.',
            },
            {
                key: 'funding-source-selected',
                label: 'Funding Source Selected',
                status: 'blocked',
                reason: 'No wallet or funding source lookup is performed.',
            },
            {
                key: 'funds-reservation',
                label: 'Funds Reservation',
                status: 'blocked',
                reason: 'Cockpit does not reserve, debit, or hold funds.',
            },
            {
                key: 'provider-fee-quote',
                label: 'Provider Fee Quote',
                status: 'blocked',
                reason: 'No provider quote or fee call is performed.',
            },
        ],
        redactions: {
            payloads: 'pricing-gates-only',
        },
    };
}

function defaultAuthorization(): CockpitQuickGenerateAuthorization {
    return {
        status: 'blocked',
        gates: [
            {
                key: 'operator-authenticated',
                label: 'Operator Authenticated',
                status: 'passed',
                reason: 'Authenticated Cockpit GET route resolved.',
            },
            {
                key: 'can-view-cockpit',
                label: 'Can View Cockpit',
                status: 'passed',
                reason: 'Read-only Cockpit access is available.',
            },
            {
                key: 'can-generate-pay-code',
                label: 'Can Generate Pay Code',
                status: 'blocked',
                reason: 'No Cockpit mutation route is registered.',
            },
            {
                key: 'can-call-providers',
                label: 'Can Call Providers',
                status: 'blocked',
                reason: 'Provider calls are outside the Slice 19 boundary.',
            },
            {
                key: 'can-move-money',
                label: 'Can Move Money',
                status: 'blocked',
                reason: 'Money movement remains disabled in Cockpit.',
            },
        ],
        redactions: {
            payloads: 'authorization-gates-only',
        },
    };
}

function sanitizePricingGateCheck(check: CockpitQuickGeneratePricingGateCheck): CockpitQuickGeneratePricingGateCheck | null {
    const key = stringValue(check.key);
    const label = stringValue(check.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: stringValue(check.status) ?? 'unknown',
        reason: stringValue(check.reason) ?? 'No pricing diagnostic is available.',
    };
}

function sanitizeAuthorizationGate(gate: CockpitQuickGenerateAuthorizationGate): CockpitQuickGenerateAuthorizationGate | null {
    const key = stringValue(gate.key);
    const label = stringValue(gate.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: stringValue(gate.status) ?? 'unknown',
        reason: stringValue(gate.reason) ?? 'No authorization diagnostic is available.',
    };
}

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
                    <CockpitQuickGeneratePricingGatePanel :pricing-gate="pricingGate" />
                    <CockpitGenerateActionPanel :enabled="false" />
                    <CockpitQuickGenerateAuthorizationGatePanel :authorization="authorization" />
                    <CockpitQuickGenerateDraftContractPanel :draft-contract="draftContract" />
                    <CockpitIssuanceBoundaryPanel />
                </div>
            </div>
        </section>
    </CockpitLayout>
</template>
