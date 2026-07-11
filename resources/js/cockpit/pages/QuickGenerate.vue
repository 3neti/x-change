<script setup lang="ts">
import { computed } from 'vue';
import CockpitDiagnosticsDisclosure from '../components/CockpitDiagnosticsDisclosure.vue';
import CockpitGenerateActionPanel from '../components/CockpitGenerateActionPanel.vue';
import CockpitIssuanceBoundaryPanel from '../components/CockpitIssuanceBoundaryPanel.vue';
import CockpitPricingFundingSummary from '../components/CockpitPricingFundingSummary.vue';
import CockpitQuickGenerateAuthorizationGatePanel from '../components/CockpitQuickGenerateAuthorizationGatePanel.vue';
import CockpitQuickGenerateDraftContractPanel from '../components/CockpitQuickGenerateDraftContractPanel.vue';
import CockpitQuickGenerateFundingGatePanel from '../components/CockpitQuickGenerateFundingGatePanel.vue';
import CockpitQuickGenerateIdempotencyGatePanel from '../components/CockpitQuickGenerateIdempotencyGatePanel.vue';
import CockpitQuickGenerateMutationAuthorizationDecisionPanel from '../components/CockpitQuickGenerateMutationAuthorizationDecisionPanel.vue';
import CockpitQuickGenerateMutationHandoffPlanPanel from '../components/CockpitQuickGenerateMutationHandoffPlanPanel.vue';
import CockpitQuickGenerateMutationPreconditionsReviewPanel from '../components/CockpitQuickGenerateMutationPreconditionsReviewPanel.vue';
import CockpitQuickGeneratePricingGatePanel from '../components/CockpitQuickGeneratePricingGatePanel.vue';
import CockpitQuickGenerateSubmitPanel from '../components/CockpitQuickGenerateSubmitPanel.vue';
import CockpitQuickGenerateValidationRedactionGatePanel from '../components/CockpitQuickGenerateValidationRedactionGatePanel.vue';
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
    CockpitQuickGenerateFundingGate,
    CockpitQuickGenerateFundingGateCheck,
    CockpitQuickGenerateIdempotencyGate,
    CockpitQuickGenerateIdempotencyGateCheck,
    CockpitQuickGenerateMutationAuthorizationDecision,
    CockpitQuickGenerateMutationContract,
    CockpitQuickGenerateMutationHandoffPlan,
    CockpitQuickGenerateMutationHandoffPlanStep,
    CockpitQuickGenerateMutationPreconditionsReview,
    CockpitQuickGenerateMutationPreconditionsReviewItem,
    CockpitQuickGeneratePricingGate,
    CockpitQuickGeneratePricingGateCheck,
    CockpitQuickGenerateValidationRedactionGate,
    CockpitQuickGenerateValidationRedactionGateCheck,
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

const fundingGate = computed<CockpitQuickGenerateFundingGate>(() => {
    const fundingGateReadModel = props.quick_generate_read_model?.funding_gate;

    if (!readModelAvailable.value || typeof fundingGateReadModel !== 'object' || fundingGateReadModel === null) {
        return defaultFundingGate();
    }

    const checks = Array.isArray(fundingGateReadModel.checks)
        ? fundingGateReadModel.checks
            .map((check): CockpitQuickGenerateFundingGateCheck | null => sanitizeFundingGateCheck(check))
            .filter((check): check is CockpitQuickGenerateFundingGateCheck => check !== null)
        : [];

    return {
        status: stringValue(fundingGateReadModel.status) ?? 'blocked',
        checks: checks.length > 0 ? checks : defaultFundingGate().checks,
        redactions: {
            payloads: stringValue(fundingGateReadModel.redactions?.payloads) ?? 'funding-gates-only',
        },
    };
});

const idempotencyGate = computed<CockpitQuickGenerateIdempotencyGate>(() => {
    const idempotencyGateReadModel = props.quick_generate_read_model?.idempotency_gate;

    if (!readModelAvailable.value || typeof idempotencyGateReadModel !== 'object' || idempotencyGateReadModel === null) {
        return defaultIdempotencyGate();
    }

    const checks = Array.isArray(idempotencyGateReadModel.checks)
        ? idempotencyGateReadModel.checks
            .map((check): CockpitQuickGenerateIdempotencyGateCheck | null => sanitizeIdempotencyGateCheck(check))
            .filter((check): check is CockpitQuickGenerateIdempotencyGateCheck => check !== null)
        : [];

    return {
        status: stringValue(idempotencyGateReadModel.status) ?? 'blocked',
        checks: checks.length > 0 ? checks : defaultIdempotencyGate().checks,
        redactions: {
            payloads: stringValue(idempotencyGateReadModel.redactions?.payloads) ?? 'idempotency-gates-only',
        },
    };
});

const validationRedactionGate = computed<CockpitQuickGenerateValidationRedactionGate>(() => {
    const validationRedactionGateReadModel = props.quick_generate_read_model?.validation_redaction_gate;

    if (!readModelAvailable.value || typeof validationRedactionGateReadModel !== 'object' || validationRedactionGateReadModel === null) {
        return defaultValidationRedactionGate();
    }

    const checks = Array.isArray(validationRedactionGateReadModel.checks)
        ? validationRedactionGateReadModel.checks
            .map((check): CockpitQuickGenerateValidationRedactionGateCheck | null => sanitizeValidationRedactionGateCheck(check))
            .filter((check): check is CockpitQuickGenerateValidationRedactionGateCheck => check !== null)
        : [];

    return {
        status: stringValue(validationRedactionGateReadModel.status) ?? 'blocked',
        checks: checks.length > 0 ? checks : defaultValidationRedactionGate().checks,
        redactions: {
            payloads: stringValue(validationRedactionGateReadModel.redactions?.payloads) ?? 'validation-redaction-gates-only',
        },
    };
});

const mutationHandoffPlan = computed<CockpitQuickGenerateMutationHandoffPlan>(() => {
    const mutationHandoffPlanReadModel = props.quick_generate_read_model?.mutation_handoff_plan;

    if (!readModelAvailable.value || typeof mutationHandoffPlanReadModel !== 'object' || mutationHandoffPlanReadModel === null) {
        return defaultMutationHandoffPlan();
    }

    const steps = Array.isArray(mutationHandoffPlanReadModel.steps)
        ? mutationHandoffPlanReadModel.steps
            .map((step): CockpitQuickGenerateMutationHandoffPlanStep | null => sanitizeMutationHandoffPlanStep(step))
            .filter((step): step is CockpitQuickGenerateMutationHandoffPlanStep => step !== null)
        : [];

    return {
        status: stringValue(mutationHandoffPlanReadModel.status) ?? 'blocked',
        steps: steps.length > 0 ? steps : defaultMutationHandoffPlan().steps,
        redactions: {
            payloads: stringValue(mutationHandoffPlanReadModel.redactions?.payloads) ?? 'mutation-handoff-plan-only',
        },
    };
});

const mutationPreconditionsReview = computed<CockpitQuickGenerateMutationPreconditionsReview>(() => {
    const mutationPreconditionsReviewReadModel = props.quick_generate_read_model?.mutation_preconditions_review;

    if (!readModelAvailable.value || typeof mutationPreconditionsReviewReadModel !== 'object' || mutationPreconditionsReviewReadModel === null) {
        return defaultMutationPreconditionsReview();
    }

    const items = Array.isArray(mutationPreconditionsReviewReadModel.items)
        ? mutationPreconditionsReviewReadModel.items
            .map((item): CockpitQuickGenerateMutationPreconditionsReviewItem | null => sanitizeMutationPreconditionsReviewItem(item))
            .filter((item): item is CockpitQuickGenerateMutationPreconditionsReviewItem => item !== null)
        : [];

    return {
        status: stringValue(mutationPreconditionsReviewReadModel.status) ?? 'blocked',
        recommendation: stringValue(mutationPreconditionsReviewReadModel.recommendation) ?? 'remain-read-only',
        items: items.length > 0 ? items : defaultMutationPreconditionsReview().items,
        redactions: {
            payloads: stringValue(mutationPreconditionsReviewReadModel.redactions?.payloads) ?? 'mutation-preconditions-review-only',
        },
    };
});

const mutationAuthorizationDecision = computed<CockpitQuickGenerateMutationAuthorizationDecision>(() => {
    const mutationAuthorizationDecisionReadModel = props.quick_generate_read_model?.mutation_authorization_decision;

    if (!readModelAvailable.value || typeof mutationAuthorizationDecisionReadModel !== 'object' || mutationAuthorizationDecisionReadModel === null) {
        return defaultMutationAuthorizationDecision();
    }

    return {
        status: stringValue(mutationAuthorizationDecisionReadModel.status) ?? 'blocked',
        decision: stringValue(mutationAuthorizationDecisionReadModel.decision) ?? 'not_authorized',
        required_approval: stringValue(mutationAuthorizationDecisionReadModel.required_approval) ?? 'human-approval-required-before-route-scaffold',
        rationale: stringValue(mutationAuthorizationDecisionReadModel.rationale) ?? 'Mutation preconditions remain blocked; Cockpit must not register a write route until explicit human approval and a smaller mutation contract exist.',
        next_step: stringValue(mutationAuthorizationDecisionReadModel.next_step) ?? 'request-explicit-approval-or-continue-read-only-hardening',
        redactions: {
            payloads: stringValue(mutationAuthorizationDecisionReadModel.redactions?.payloads) ?? 'mutation-authorization-decision-only',
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

const mutationContract = computed<CockpitQuickGenerateMutationContract>(() => {
    const contract = props.quick_generate_read_model?.mutation_contract;

    if (!readModelAvailable.value || typeof contract !== 'object' || contract === null) {
        return defaultMutationContract();
    }

    return {
        schema: stringValue(contract.schema) ?? 'x-change.cockpit.quick-generate-mutation.v1',
        status: stringValue(contract.status) ?? 'not_wired',
        authorization: stringValue(contract.authorization) ?? 'not-loaded',
        route: stringValue(contract.route) ?? 'not-loaded',
        route_url: stringValue(contract.route_url),
        request_adapter: stringValue(contract.request_adapter) ?? 'not-loaded',
        issuance_owner: stringValue(contract.issuance_owner) ?? 'not-loaded',
        idempotency: stringValue(contract.idempotency) ?? 'not-loaded',
        response_contract: stringValue(contract.response_contract) ?? 'not-loaded',
        runtime_enabled: contract.runtime_enabled === true,
        allowed_methods: Array.isArray(contract.allowed_methods)
            ? contract.allowed_methods.map((method) => String(method))
            : ['GET'],
        redactions: {
            payloads: stringValue(contract.redactions?.payloads) ?? 'mutation-contract-only',
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

function defaultFundingGate(): CockpitQuickGenerateFundingGate {
    return {
        status: 'blocked',
        checks: [
            {
                key: 'funding-policy-known',
                label: 'Funding Policy Known',
                status: 'passed',
                reason: 'Funding policy is represented as a read-only Cockpit readiness fact.',
            },
            {
                key: 'issuer-wallet-identified',
                label: 'Issuer Wallet Identified',
                status: 'blocked',
                reason: 'Cockpit does not resolve issuer wallets in Slice 21.',
            },
            {
                key: 'wallet-balance-available',
                label: 'Wallet Balance Available',
                status: 'blocked',
                reason: 'Cockpit does not read wallet balances in Slice 21.',
            },
            {
                key: 'sufficient-funds',
                label: 'Sufficient Funds',
                status: 'blocked',
                reason: 'Cockpit does not evaluate spendable funds in Slice 21.',
            },
            {
                key: 'funds-reservation-ready',
                label: 'Funds Reservation Ready',
                status: 'blocked',
                reason: 'Cockpit does not reserve, hold, debit, or transfer funds.',
            },
            {
                key: 'provider-funding-ready',
                label: 'Provider Funding Ready',
                status: 'blocked',
                reason: 'Cockpit does not call provider funding or account-readiness services.',
            },
        ],
        redactions: {
            payloads: 'funding-gates-only',
        },
    };
}

function defaultIdempotencyGate(): CockpitQuickGenerateIdempotencyGate {
    return {
        status: 'blocked',
        checks: [
            {
                key: 'idempotency-policy-known',
                label: 'Idempotency Policy Known',
                status: 'passed',
                reason: 'Idempotency is represented as a read-only Cockpit readiness fact.',
            },
            {
                key: 'idempotency-key-source-defined',
                label: 'Idempotency Key Source Defined',
                status: 'blocked',
                reason: 'Cockpit does not generate, accept, or persist idempotency keys in Slice 22.',
            },
            {
                key: 'payload-fingerprint-defined',
                label: 'Payload Fingerprint Defined',
                status: 'blocked',
                reason: 'Cockpit does not hash or fingerprint Quick Generate payloads in Slice 22.',
            },
            {
                key: 'replay-lookup-ready',
                label: 'Replay Lookup Ready',
                status: 'blocked',
                reason: 'Cockpit does not query idempotency stores or replay records in Slice 22.',
            },
            {
                key: 'conflict-response-ready',
                label: 'Conflict Response Ready',
                status: 'blocked',
                reason: 'Cockpit does not evaluate idempotency conflicts in Slice 22.',
            },
            {
                key: 'ttl-policy-ready',
                label: 'TTL Policy Ready',
                status: 'blocked',
                reason: 'Cockpit does not read or enforce idempotency TTL policy in Slice 22.',
            },
        ],
        redactions: {
            payloads: 'idempotency-gates-only',
        },
    };
}

function defaultValidationRedactionGate(): CockpitQuickGenerateValidationRedactionGate {
    return {
        status: 'blocked',
        checks: [
            {
                key: 'request-schema-known',
                label: 'Request Schema Known',
                status: 'passed',
                reason: 'The Quick Generate draft contract schema is represented as a read-only Cockpit readiness fact.',
            },
            {
                key: 'required-fields-defined',
                label: 'Required Fields Defined',
                status: 'blocked',
                reason: 'Cockpit does not execute request validation or enforce required fields in Slice 23.',
            },
            {
                key: 'validation-rules-wired',
                label: 'Validation Rules Wired',
                status: 'blocked',
                reason: 'Cockpit does not invoke GeneratePayCodeRequest validation in Slice 23.',
            },
            {
                key: 'sensitive-fields-redacted',
                label: 'Sensitive Fields Redacted',
                status: 'blocked',
                reason: 'Cockpit does not accept, persist, or redact submitted payloads in Slice 23.',
            },
            {
                key: 'sanitized-preview-ready',
                label: 'Sanitized Preview Ready',
                status: 'blocked',
                reason: 'Cockpit does not build sanitized request previews in Slice 23.',
            },
            {
                key: 'validation-error-contract-ready',
                label: 'Validation Error Contract Ready',
                status: 'blocked',
                reason: 'Cockpit does not expose validation error response contracts in Slice 23.',
            },
        ],
        redactions: {
            payloads: 'validation-redaction-gates-only',
        },
    };
}

function defaultMutationHandoffPlan(): CockpitQuickGenerateMutationHandoffPlan {
    return {
        status: 'blocked',
        steps: [
            {
                key: 'existing-issuance-owner-identified',
                label: 'Existing Issuance Owner Identified',
                status: 'passed',
                reason: 'Quick Generate must hand off to the existing x-change issuance owner instead of inventing Cockpit generation behavior.',
            },
            {
                key: 'generate-pay-code-action-handoff',
                label: 'GeneratePayCode Action Handoff',
                status: 'blocked',
                reason: 'Cockpit does not call GeneratePayCode in Slice 24.',
            },
            {
                key: 'generate-pay-code-controller-handoff',
                label: 'GeneratePayCodeController Handoff',
                status: 'blocked',
                reason: 'Cockpit does not register a mutation route or controller handoff in Slice 24.',
            },
            {
                key: 'preconditions-green',
                label: 'Preconditions Green',
                status: 'blocked',
                reason: 'Authorization, pricing, funding, idempotency, validation, and redaction gates remain blocked.',
            },
            {
                key: 'side-effect-boundary-confirmed',
                label: 'Side Effect Boundary Confirmed',
                status: 'passed',
                reason: 'Quick Generate uses the existing GeneratePayCode issuance handoff; wallet movement, provider calls, journal writes, action runs, and feedback delivery remain separately gated.',
            },
            {
                key: 'operator-response-contract-ready',
                label: 'Operator Response Contract Ready',
                status: 'blocked',
                reason: 'Cockpit does not define a mutation success, failure, or validation response contract in Slice 24.',
            },
        ],
        redactions: {
            payloads: 'mutation-handoff-plan-only',
        },
    };
}

function defaultMutationPreconditionsReview(): CockpitQuickGenerateMutationPreconditionsReview {
    return {
        status: 'blocked',
        recommendation: 'remain-read-only',
        items: [
            {
                key: 'authorization-ready',
                label: 'Authorization Ready',
                status: 'blocked',
                reason: 'Generation, provider, and money movement authorization gates remain blocked.',
            },
            {
                key: 'pricing-ready',
                label: 'Pricing Ready',
                status: 'blocked',
                reason: 'Amount input, pricing service wiring, funding source selection, reservation, and provider fee quote gates remain blocked.',
            },
            {
                key: 'funding-ready',
                label: 'Funding Ready',
                status: 'blocked',
                reason: 'Issuer wallet, balance, sufficiency, reservation, and provider funding readiness remain blocked.',
            },
            {
                key: 'idempotency-ready',
                label: 'Idempotency Ready',
                status: 'blocked',
                reason: 'Idempotency key source, payload fingerprinting, replay lookup, conflict response, and TTL policy remain blocked.',
            },
            {
                key: 'validation-redaction-ready',
                label: 'Validation and Redaction Ready',
                status: 'blocked',
                reason: 'Required fields, validation rules, submitted-payload redaction, sanitized previews, and validation error contracts remain blocked.',
            },
            {
                key: 'handoff-ready',
                label: 'Handoff Ready',
                status: 'blocked',
                reason: 'GeneratePayCode action handoff and GeneratePayCodeController handoff remain blocked.',
            },
            {
                key: 'operator-response-ready',
                label: 'Operator Response Ready',
                status: 'blocked',
                reason: 'Cockpit has no mutation success, failure, validation, rollback, or retry response contract.',
            },
        ],
        redactions: {
            payloads: 'mutation-preconditions-review-only',
        },
    };
}

function defaultMutationAuthorizationDecision(): CockpitQuickGenerateMutationAuthorizationDecision {
    return {
        status: 'blocked',
        decision: 'not_authorized',
        required_approval: 'human-approval-required-before-route-scaffold',
        rationale: 'Mutation preconditions remain blocked; Cockpit must not register a write route until explicit human approval and a smaller mutation contract exist.',
        next_step: 'request-explicit-approval-or-continue-read-only-hardening',
        redactions: {
            payloads: 'mutation-authorization-decision-only',
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

function defaultMutationContract(): CockpitQuickGenerateMutationContract {
    return {
        schema: 'x-change.cockpit.quick-generate-mutation.v1',
        status: 'not_wired',
        authorization: 'not-loaded',
        route: 'not-loaded',
        route_url: null,
        request_adapter: 'not-loaded',
        issuance_owner: 'not-loaded',
        idempotency: 'not-loaded',
        response_contract: 'not-loaded',
        runtime_enabled: false,
        allowed_methods: ['GET'],
        redactions: {
            payloads: 'not-loaded',
        },
    };
}

function sanitizeFundingGateCheck(check: CockpitQuickGenerateFundingGateCheck): CockpitQuickGenerateFundingGateCheck | null {
    const key = stringValue(check.key);
    const label = stringValue(check.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: stringValue(check.status) ?? 'unknown',
        reason: stringValue(check.reason) ?? 'No funding diagnostic is available.',
    };
}

function sanitizeIdempotencyGateCheck(check: CockpitQuickGenerateIdempotencyGateCheck): CockpitQuickGenerateIdempotencyGateCheck | null {
    const key = stringValue(check.key);
    const label = stringValue(check.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: stringValue(check.status) ?? 'unknown',
        reason: stringValue(check.reason) ?? 'No idempotency diagnostic is available.',
    };
}

function sanitizeValidationRedactionGateCheck(check: CockpitQuickGenerateValidationRedactionGateCheck): CockpitQuickGenerateValidationRedactionGateCheck | null {
    const key = stringValue(check.key);
    const label = stringValue(check.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: stringValue(check.status) ?? 'unknown',
        reason: stringValue(check.reason) ?? 'No validation or redaction diagnostic is available.',
    };
}

function sanitizeMutationHandoffPlanStep(step: CockpitQuickGenerateMutationHandoffPlanStep): CockpitQuickGenerateMutationHandoffPlanStep | null {
    const key = stringValue(step.key);
    const label = stringValue(step.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: stringValue(step.status) ?? 'unknown',
        reason: stringValue(step.reason) ?? 'No mutation handoff diagnostic is available.',
    };
}

function sanitizeMutationPreconditionsReviewItem(item: CockpitQuickGenerateMutationPreconditionsReviewItem): CockpitQuickGenerateMutationPreconditionsReviewItem | null {
    const key = stringValue(item.key);
    const label = stringValue(item.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: stringValue(item.status) ?? 'unknown',
        reason: stringValue(item.reason) ?? 'No mutation precondition diagnostic is available.',
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
                    Wave 12 · Functional parity bridge
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Quick Generate Runtime
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This screen now uses the template-first draft/compiler path and hands off to the
                    existing x-change GeneratePayCode action. Pricing and funding preflights are
                    informational, while journal, action, feedback, provider, and campaign mutations
                    remain separately gated.
                </p>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <CockpitTemplateSelector
                    :templates="templates"
                    :selected-key="templates[0]?.key"
                />

                <CockpitRuntimeInputPanel :inputs="runtimeInputs" />

                <div class="space-y-4">
                    <CockpitQuickGenerateSubmitPanel
                        :mutation-contract="mutationContract"
                        :draft-contract="draftContract"
                        :templates="templates"
                    />
                    <CockpitGenerateActionPanel :enabled="false" :runtime-enabled="true" />
                    <CockpitDiagnosticsDisclosure
                        title="Architecture history and gate diagnostics"
                        summary="Older baseline panels remain available for engineering diagnostics. They are no longer the primary operator guidance after the Quick Generate runtime handoff."
                    >
                        <CockpitPricingFundingSummary :summaries="pricingSummaries" />
                        <CockpitQuickGeneratePricingGatePanel :pricing-gate="pricingGate" />
                        <CockpitQuickGenerateFundingGatePanel :funding-gate="fundingGate" />
                        <CockpitQuickGenerateIdempotencyGatePanel :idempotency-gate="idempotencyGate" />
                        <CockpitQuickGenerateValidationRedactionGatePanel :validation-redaction-gate="validationRedactionGate" />
                        <CockpitQuickGenerateMutationHandoffPlanPanel :mutation-handoff-plan="mutationHandoffPlan" />
                        <CockpitQuickGenerateMutationPreconditionsReviewPanel :mutation-preconditions-review="mutationPreconditionsReview" />
                        <CockpitQuickGenerateMutationAuthorizationDecisionPanel :mutation-authorization-decision="mutationAuthorizationDecision" />
                        <CockpitQuickGenerateAuthorizationGatePanel :authorization="authorization" />
                        <CockpitQuickGenerateDraftContractPanel :draft-contract="draftContract" />
                        <CockpitIssuanceBoundaryPanel />
                    </CockpitDiagnosticsDisclosure>
                </div>
            </div>
        </section>
    </CockpitLayout>
</template>
