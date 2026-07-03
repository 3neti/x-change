<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateReadModelData extends Data
{
    /**
     * @param  array<int, CockpitQuickGenerateTemplateData>  $templates
     * @param  array<int, CockpitQuickGenerateRuntimeInputData>  $runtime_inputs
     * @param  array<int, CockpitQuickGeneratePricingSummaryData>  $pricing_summaries
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status,
        public readonly bool $authorized = false,
        public readonly array $templates = [],
        public readonly array $runtime_inputs = [],
        public readonly array $pricing_summaries = [],
        public readonly CockpitQuickGeneratePricingGateData $pricing_gate = new CockpitQuickGeneratePricingGateData,
        public readonly CockpitQuickGenerateFundingGateData $funding_gate = new CockpitQuickGenerateFundingGateData,
        public readonly CockpitQuickGenerateIdempotencyGateData $idempotency_gate = new CockpitQuickGenerateIdempotencyGateData,
        public readonly CockpitQuickGenerateValidationRedactionGateData $validation_redaction_gate = new CockpitQuickGenerateValidationRedactionGateData,
        public readonly CockpitQuickGenerateMutationHandoffPlanData $mutation_handoff_plan = new CockpitQuickGenerateMutationHandoffPlanData,
        public readonly CockpitQuickGenerateMutationPreconditionsReviewData $mutation_preconditions_review = new CockpitQuickGenerateMutationPreconditionsReviewData,
        public readonly CockpitQuickGenerateMutationAuthorizationDecisionData $mutation_authorization_decision = new CockpitQuickGenerateMutationAuthorizationDecisionData,
        public readonly CockpitQuickGenerateDraftContractData $draft_contract = new CockpitQuickGenerateDraftContractData,
        public readonly CockpitQuickGenerateAuthorizationData $authorization = new CockpitQuickGenerateAuthorizationData,
        public readonly CockpitQuickGenerateActionData $action = new CockpitQuickGenerateActionData,
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
