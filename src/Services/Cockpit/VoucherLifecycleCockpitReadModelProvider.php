<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Cockpit\CockpitCampaignReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardActivityData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardPipelineStageData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardRiskSignalData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateActionData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateAuthorizationData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateAuthorizationGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateDraftContractData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateFundingGateCheckData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateFundingGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateIdempotencyGateCheckData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateIdempotencyGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationAuthorizationDecisionData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationContractData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationContractGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationHandoffPlanData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationHandoffPlanStepData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationPreconditionsReviewData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationPreconditionsReviewItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGeneratePricingGateCheckData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGeneratePricingGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGeneratePricingSummaryData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateRuntimeInputData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateTemplateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateValidationRedactionGateCheckData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateValidationRedactionGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelBundleData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Data\Cockpit\CockpitVoucherReadModelData;
use LBHurtado\XChange\Exceptions\VoucherNotFound;

class VoucherLifecycleCockpitReadModelProvider implements CockpitReadModelProviderContract
{
    public function __construct(
        private readonly VoucherLifecycleServiceContract $vouchers,
        private readonly NullCockpitReadModelProvider $fallback = new NullCockpitReadModelProvider,
        private readonly ?OptionalCockpitIntegrationReadModels $integrations = null,
    ) {}

    public function forVoucher(CockpitReadModelQueryData $query): CockpitReadModelBundleData
    {
        $code = $this->normalizeCode($query->code);

        if ($code === null) {
            return $this->fallback->forVoucher($query);
        }

        try {
            $detail = $this->toArray($this->vouchers->showByCode($code));
        } catch (VoucherNotFound) {
            return $this->fallback->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                operatorId: $query->operatorId,
                include: $query->include,
                correlationId: $query->correlationId,
            ));
        }

        if ($detail === []) {
            return $this->fallback->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                operatorId: $query->operatorId,
                include: $query->include,
                correlationId: $query->correlationId,
            ));
        }

        $fallback = $this->fallback->forVoucher(new CockpitReadModelQueryData(
            code: $code,
            operatorId: $query->operatorId,
            include: $query->include,
            correlationId: $query->correlationId,
        ));

        return new CockpitReadModelBundleData(
            code: $this->summaryCode($detail, $code),
            voucher: new CockpitVoucherReadModelData(
                code: $this->summaryCode($detail, $code),
                status: $this->summaryStatus($detail),
                summary: $this->summary($detail, $code),
                redactions: [
                    'payloads' => 'sanitized-summary-only',
                    'excluded' => [
                        'id',
                        'voucher_id',
                        'issuer_id',
                        'instructions',
                        'claims',
                        'approval',
                        'provider_payload',
                        'raw_payload',
                        'wallet',
                        'provider',
                    ],
                ],
                authorized: true,
            ),
            execution: $fallback->execution,
            journal: $this->integrations?->journal($query) ?? $fallback->journal,
            actions: $this->integrations?->actions($query) ?? $fallback->actions,
            feedback: $this->integrations?->feedback($query) ?? $fallback->feedback,
        );
    }

    public function forDashboard(CockpitReadModelQueryData $query): CockpitDashboardReadModelData
    {
        $rows = collect($this->vouchers->list())
            ->map(fn (mixed $row): array => $this->toArray($row))
            ->filter(fn (array $row): bool => $this->summaryCode($row, '') !== '')
            ->values();

        $issued = $this->countStatus($rows, 'issued');
        $redeemed = $this->countStatus($rows, 'redeemed');
        $expired = $this->countStatus($rows, 'expired');
        $awaitingApproval = $rows
            ->filter(fn (array $row): bool => $this->stringValue($row['display_status'] ?? null, '') === 'awaiting_approval')
            ->count();
        $attention = $expired + $awaitingApproval;

        return new CockpitDashboardReadModelData(
            status: 'available',
            authorized: true,
            metrics: [
                new CockpitDashboardMetricData(
                    key: 'pay-codes-visible',
                    label: 'Pay Codes Visible',
                    value: (string) $rows->count(),
                    helper: 'Sanitized voucher lifecycle list rows',
                ),
                new CockpitDashboardMetricData(
                    key: 'issued-pay-codes',
                    label: 'Issued',
                    value: (string) $issued,
                    helper: 'Read-only lifecycle summary',
                    tone: 'healthy',
                ),
                new CockpitDashboardMetricData(
                    key: 'redeemed-pay-codes',
                    label: 'Redeemed',
                    value: (string) $redeemed,
                    helper: 'Includes awaiting approval display states',
                    tone: 'healthy',
                ),
                new CockpitDashboardMetricData(
                    key: 'attention-pay-codes',
                    label: 'Needs Attention',
                    value: (string) $attention,
                    helper: 'Expired or awaiting approval summaries only',
                    tone: 'warning',
                ),
            ],
            pipeline: [
                new CockpitDashboardPipelineStageData(
                    key: 'issued',
                    label: 'Issued',
                    value: (string) $issued,
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'redeemed',
                    label: 'Redeemed',
                    value: (string) $redeemed,
                    tone: 'healthy',
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'expired',
                    label: 'Expired',
                    value: (string) $expired,
                    tone: 'warning',
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'awaiting-approval',
                    label: 'Awaiting Approval',
                    value: (string) $awaitingApproval,
                    tone: 'warning',
                ),
            ],
            risk_signals: [
                new CockpitDashboardRiskSignalData(
                    key: 'expired-pay-codes',
                    label: 'Expired Pay Codes',
                    value: $expired.' sanitized summaries',
                    severity: 'warning',
                ),
                new CockpitDashboardRiskSignalData(
                    key: 'awaiting-approval',
                    label: 'Awaiting Approval',
                    value: $awaitingApproval.' sanitized summaries',
                    severity: 'watch',
                ),
            ],
            activity: $this->dashboardActivity($rows),
            redactions: [
                'payloads' => 'sanitized-dashboard-summary-only',
                'excluded' => $this->excludedPayloadKeys(),
            ],
        );
    }

    public function forQuickGenerate(CockpitReadModelQueryData $query): CockpitQuickGenerateReadModelData
    {
        return new CockpitQuickGenerateReadModelData(
            status: 'available',
            authorized: true,
            templates: [
                new CockpitQuickGenerateTemplateData(
                    key: 'money-changer',
                    name: 'Money Changer',
                    description: 'Fast cash-out Pay Code for branch counter operations.',
                    profile: 'branch',
                    estimated_time: 'Under 5 seconds',
                ),
                new CockpitQuickGenerateTemplateData(
                    key: 'ofw-remittance',
                    name: 'OFW Remittance',
                    description: 'Template-first remittance issuance with recipient details.',
                    profile: 'operations',
                    estimated_time: 'Pending runtime inputs',
                ),
                new CockpitQuickGenerateTemplateData(
                    key: 'settlement-envelope',
                    name: 'Settlement Envelope',
                    description: 'Complex settlement issuance remains deferred to later slices.',
                    profile: 'settlement',
                    estimated_time: 'Deferred',
                    disabled: true,
                ),
            ],
            runtime_inputs: [
                new CockpitQuickGenerateRuntimeInputData(
                    key: 'amount',
                    label: 'Amount',
                    value: 'Pending operator input',
                    helper: 'No pricing or funding calculation is executed in Slice 16.',
                ),
                new CockpitQuickGenerateRuntimeInputData(
                    key: 'recipient',
                    label: 'Recipient',
                    value: 'Pending recipient selection',
                    helper: 'Contact/package integration remains deferred.',
                ),
                new CockpitQuickGenerateRuntimeInputData(
                    key: 'purpose',
                    label: 'Purpose',
                    value: 'Pending purpose note',
                    helper: 'Purpose is presentation context only in this baseline.',
                ),
            ],
            pricing_summaries: [
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'pricing',
                    label: 'Pricing Estimate',
                    value: 'Not calculated',
                    helper: 'Will use existing pricing services only when explicitly wired.',
                ),
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'funding',
                    label: 'Funding Impact',
                    value: 'Not reserved',
                    helper: 'No wallet lookup, reservation, debit, or provider call occurs here.',
                ),
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'execution',
                    label: 'Execution Summary',
                    value: 'Template pending',
                    helper: 'Execution semantics stay voucher-owned and are not inferred in Cockpit.',
                ),
            ],
            pricing_gate: new CockpitQuickGeneratePricingGateData(
                status: 'blocked',
                checks: [
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'template-selected',
                        label: 'Template Selected',
                        status: 'passed',
                        reason: 'The default Quick Generate template is visible as a read-only fact.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'amount-input-present',
                        label: 'Amount Input Present',
                        status: 'blocked',
                        reason: 'No operator amount input is accepted by Cockpit in Slice 20.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'pricing-service-wired',
                        label: 'Pricing Service Wired',
                        status: 'blocked',
                        reason: 'Cockpit does not call pricing services in Slice 20.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'funding-source-selected',
                        label: 'Funding Source Selected',
                        status: 'blocked',
                        reason: 'No wallet or funding source lookup is performed.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'funds-reservation',
                        label: 'Funds Reservation',
                        status: 'blocked',
                        reason: 'Cockpit does not reserve, debit, or hold funds.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'provider-fee-quote',
                        label: 'Provider Fee Quote',
                        status: 'blocked',
                        reason: 'No provider quote or fee call is performed.',
                    ),
                ],
                redactions: [
                    'payloads' => 'pricing-gates-only',
                    'excluded' => [
                        'pricing_breakdown',
                        'funding_source',
                        'wallet',
                        'balance',
                        'account_number',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ),
            funding_gate: new CockpitQuickGenerateFundingGateData(
                status: 'blocked',
                checks: [
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'funding-policy-known',
                        label: 'Funding Policy Known',
                        status: 'passed',
                        reason: 'Funding policy is represented as a read-only Cockpit readiness fact.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'issuer-wallet-identified',
                        label: 'Issuer Wallet Identified',
                        status: 'blocked',
                        reason: 'Cockpit does not resolve issuer wallets in Slice 21.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'wallet-balance-available',
                        label: 'Wallet Balance Available',
                        status: 'blocked',
                        reason: 'Cockpit does not read wallet balances in Slice 21.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'sufficient-funds',
                        label: 'Sufficient Funds',
                        status: 'blocked',
                        reason: 'Cockpit does not evaluate spendable funds in Slice 21.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'funds-reservation-ready',
                        label: 'Funds Reservation Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not reserve, hold, debit, or transfer funds.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'provider-funding-ready',
                        label: 'Provider Funding Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not call provider funding or account-readiness services.',
                    ),
                ],
                redactions: [
                    'payloads' => 'funding-gates-only',
                    'excluded' => [
                        'funding_source',
                        'wallet',
                        'balance',
                        'available_balance',
                        'account_number',
                        'provider_wallet',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ),
            idempotency_gate: new CockpitQuickGenerateIdempotencyGateData(
                status: 'blocked',
                checks: [
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'idempotency-policy-known',
                        label: 'Idempotency Policy Known',
                        status: 'passed',
                        reason: 'Idempotency is represented as a read-only Cockpit readiness fact.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'idempotency-key-source-defined',
                        label: 'Idempotency Key Source Defined',
                        status: 'blocked',
                        reason: 'Cockpit does not generate, accept, or persist idempotency keys in Slice 22.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'payload-fingerprint-defined',
                        label: 'Payload Fingerprint Defined',
                        status: 'blocked',
                        reason: 'Cockpit does not hash or fingerprint Quick Generate payloads in Slice 22.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'replay-lookup-ready',
                        label: 'Replay Lookup Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not query idempotency stores or replay records in Slice 22.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'conflict-response-ready',
                        label: 'Conflict Response Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not evaluate idempotency conflicts in Slice 22.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'ttl-policy-ready',
                        label: 'TTL Policy Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not read or enforce idempotency TTL policy in Slice 22.',
                    ),
                ],
                redactions: [
                    'payloads' => 'idempotency-gates-only',
                    'excluded' => [
                        'idempotency_key',
                        'request_payload',
                        'payload_fingerprint',
                        'stored_response',
                        'replay_payload',
                        'cache_key',
                        'raw_payload',
                    ],
                ],
            ),
            validation_redaction_gate: new CockpitQuickGenerateValidationRedactionGateData(
                status: 'blocked',
                checks: [
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'request-schema-known',
                        label: 'Request Schema Known',
                        status: 'passed',
                        reason: 'The Quick Generate draft contract schema is represented as a read-only Cockpit readiness fact.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'required-fields-defined',
                        label: 'Required Fields Defined',
                        status: 'blocked',
                        reason: 'Cockpit does not execute request validation or enforce required fields in Slice 23.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'validation-rules-wired',
                        label: 'Validation Rules Wired',
                        status: 'blocked',
                        reason: 'Cockpit does not invoke GeneratePayCodeRequest validation in Slice 23.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'sensitive-fields-redacted',
                        label: 'Sensitive Fields Redacted',
                        status: 'blocked',
                        reason: 'Cockpit does not accept, persist, or redact submitted payloads in Slice 23.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'sanitized-preview-ready',
                        label: 'Sanitized Preview Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not build sanitized request previews in Slice 23.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'validation-error-contract-ready',
                        label: 'Validation Error Contract Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not expose validation error response contracts in Slice 23.',
                    ),
                ],
                redactions: [
                    'payloads' => 'validation-redaction-gates-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'validation_errors',
                        'mobile',
                        'email',
                        'recipient_reference',
                        'account_number',
                        'raw_payload',
                    ],
                ],
            ),
            mutation_handoff_plan: new CockpitQuickGenerateMutationHandoffPlanData(
                status: 'blocked',
                steps: [
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'existing-issuance-owner-identified',
                        label: 'Existing Issuance Owner Identified',
                        status: 'passed',
                        reason: 'Quick Generate must hand off to the existing x-change issuance owner instead of inventing Cockpit generation behavior.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'generate-pay-code-action-handoff',
                        label: 'GeneratePayCode Action Handoff',
                        status: 'blocked',
                        reason: 'Cockpit does not call GeneratePayCode in Slice 24.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'generate-pay-code-controller-handoff',
                        label: 'GeneratePayCodeController Handoff',
                        status: 'blocked',
                        reason: 'Cockpit does not register a mutation route or controller handoff in Slice 24.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'preconditions-green',
                        label: 'Preconditions Green',
                        status: 'blocked',
                        reason: 'Authorization, pricing, funding, idempotency, validation, and redaction gates remain blocked.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'side-effect-boundary-confirmed',
                        label: 'Side Effect Boundary Confirmed',
                        status: 'blocked',
                        reason: 'No voucher generation, wallet movement, provider call, journal write, action run, or feedback delivery is authorized.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'operator-response-contract-ready',
                        label: 'Operator Response Contract Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not define a mutation success, failure, or validation response contract in Slice 24.',
                    ),
                ],
                redactions: [
                    'payloads' => 'mutation-handoff-plan-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'mutation_payload',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'journal_payload',
                        'action_payload',
                        'feedback_payload',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ),
            mutation_preconditions_review: new CockpitQuickGenerateMutationPreconditionsReviewData(
                status: 'blocked',
                recommendation: 'remain-read-only',
                items: [
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'authorization-ready',
                        label: 'Authorization Ready',
                        status: 'blocked',
                        reason: 'Generation, provider, and money movement authorization gates remain blocked.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'pricing-ready',
                        label: 'Pricing Ready',
                        status: 'blocked',
                        reason: 'Amount input, pricing service wiring, funding source selection, reservation, and provider fee quote gates remain blocked.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'funding-ready',
                        label: 'Funding Ready',
                        status: 'blocked',
                        reason: 'Issuer wallet, balance, sufficiency, reservation, and provider funding readiness remain blocked.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'idempotency-ready',
                        label: 'Idempotency Ready',
                        status: 'blocked',
                        reason: 'Idempotency key source, payload fingerprinting, replay lookup, conflict response, and TTL policy remain blocked.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'validation-redaction-ready',
                        label: 'Validation and Redaction Ready',
                        status: 'blocked',
                        reason: 'Required fields, validation rules, submitted-payload redaction, sanitized previews, and validation error contracts remain blocked.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'handoff-ready',
                        label: 'Handoff Ready',
                        status: 'blocked',
                        reason: 'GeneratePayCode action handoff and GeneratePayCodeController handoff remain blocked.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'operator-response-ready',
                        label: 'Operator Response Ready',
                        status: 'blocked',
                        reason: 'Cockpit has no mutation success, failure, validation, rollback, or retry response contract.',
                    ),
                ],
                redactions: [
                    'payloads' => 'mutation-preconditions-review-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'precondition_payload',
                        'mutation_approval',
                        'mutation_route',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ),
            mutation_authorization_decision: new CockpitQuickGenerateMutationAuthorizationDecisionData(
                status: 'blocked',
                decision: 'not_authorized',
                required_approval: 'human-approval-required-before-route-scaffold',
                rationale: 'Mutation preconditions remain blocked; Cockpit must not register a write route until explicit human approval and a smaller mutation contract exist.',
                next_step: 'request-explicit-approval-or-continue-read-only-hardening',
                redactions: [
                    'payloads' => 'mutation-authorization-decision-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'mutation_payload',
                        'approval_payload',
                        'route_definition',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ),
            mutation_contract: new CockpitQuickGenerateMutationContractData(
                status: 'approved_plan',
                authorization: 'operator-authorization-required-before-route-shell',
                route: 'x-change.cockpit.quick-generate.store',
                request_adapter: 'GeneratePayCodeRequest-compatible-adapter',
                issuance_owner: 'GeneratePayCode',
                idempotency: 'required-before-submit-enabled',
                response_contract: 'operator-safe-redacted-result',
                runtime_enabled: false,
                gates: [
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'route-contract-defined',
                        label: 'Route Contract Defined',
                        status: 'planned',
                        decision: 'POST route name reserved; route not registered in Wave 1A.',
                        reason: 'Wave 1A defines the route contract before any mutation route shell is scaffolded.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'request-adapter-defined',
                        label: 'Request Adapter Defined',
                        status: 'planned',
                        decision: 'Adapter must remain compatible with GeneratePayCodeRequest.',
                        reason: 'Cockpit must not invent a second issuance validation language.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'issuance-owner-confirmed',
                        label: 'Issuance Owner Confirmed',
                        status: 'passed',
                        decision: 'GeneratePayCode remains the issuance owner.',
                        reason: 'Cockpit is an operator shell and must hand off to existing x-change issuance behavior.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'idempotency-required',
                        label: 'Idempotency Required',
                        status: 'planned',
                        decision: 'Idempotency key is required before UI submit can be enabled.',
                        reason: 'Repeated operator submits must not duplicate issuance.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'operator-response-redacted',
                        label: 'Operator Response Redacted',
                        status: 'planned',
                        decision: 'Response must expose operator-safe generated facts only.',
                        reason: 'Provider payloads, wallet data, raw voucher payloads, secrets, and internal IDs remain excluded.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'runtime-disabled',
                        label: 'Runtime Disabled',
                        status: 'blocked',
                        decision: 'No mutation route or submit behavior is enabled in Wave 1A.',
                        reason: 'Wave 1A is a contract and safety-gate scaffold only.',
                    ),
                ],
                allowed_methods: ['GET'],
                redactions: [
                    'payloads' => 'mutation-contract-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'idempotency_key',
                        'payload_fingerprint',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'balance',
                        'funding_source',
                        'journal_payload',
                        'action_payload',
                        'feedback_payload',
                        'raw_payload',
                    ],
                ],
            ),
            draft_contract: new CockpitQuickGenerateDraftContractData(
                status: 'draft_only',
                template_key: 'money-changer',
                currency: 'PHP',
                redactions: [
                    'payloads' => 'draft-shape-only',
                    'excluded' => [
                        'mobile',
                        'email',
                        'wallet',
                        'balance',
                        'provider_payload',
                        'raw_payload',
                        'account_number',
                        'pricing_breakdown',
                        'funding_source',
                        'issuer_id',
                    ],
                ],
            ),
            authorization: new CockpitQuickGenerateAuthorizationData(
                status: 'blocked',
                gates: [
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'operator-authenticated',
                        label: 'Operator Authenticated',
                        status: 'passed',
                        reason: 'Authenticated Cockpit GET route resolved.',
                    ),
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'can-view-cockpit',
                        label: 'Can View Cockpit',
                        status: 'passed',
                        reason: 'Read-only Cockpit access is available.',
                    ),
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'can-generate-pay-code',
                        label: 'Can Generate Pay Code',
                        status: 'blocked',
                        reason: 'No Cockpit mutation route is registered.',
                    ),
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'can-call-providers',
                        label: 'Can Call Providers',
                        status: 'blocked',
                        reason: 'Provider calls are outside the Slice 19 boundary.',
                    ),
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'can-move-money',
                        label: 'Can Move Money',
                        status: 'blocked',
                        reason: 'Money movement remains disabled in Cockpit.',
                    ),
                ],
                redactions: [
                    'payloads' => 'authorization-gates-only',
                    'excluded' => [
                        'roles',
                        'permissions',
                        'policy_payload',
                        'tenant_payload',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ),
            action: new CockpitQuickGenerateActionData(
                enabled: false,
                reason: 'issuance-not-wired',
            ),
            redactions: [
                'payloads' => 'sanitized-quick-generate-catalog-only',
                'excluded' => [
                    'wallet',
                    'balance',
                    'provider_payload',
                    'raw_payload',
                    'account_number',
                    'pricing_breakdown',
                    'funding_source',
                    'issuer_id',
                ],
            ],
        );
    }

    public function forCampaignAdoption(CockpitReadModelQueryData $query): CockpitCampaignReadModelData
    {
        if ($this->integrations === null) {
            return $this->fallback->forCampaignAdoption($query);
        }

        return $this->integrations->campaignAdoption($query);
    }

    public function forPayCodeList(CockpitReadModelQueryData $query): CockpitPayCodeListReadModelData
    {
        $rows = collect($this->vouchers->list())
            ->map(fn (mixed $row): ?CockpitPayCodeListRecordData => $this->listRecord($this->toArray($row)))
            ->filter()
            ->values()
            ->all();

        return new CockpitPayCodeListReadModelData(
            status: 'available',
            authorized: true,
            query: $query->code,
            records: $rows,
            redactions: [
                'payloads' => 'sanitized-list-summary-only',
                'excluded' => $this->excludedPayloadKeys(),
            ],
        );
    }

    private function normalizeCode(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $detail): array
    {
        if (is_array($detail)) {
            return $detail;
        }

        if ($detail instanceof Arrayable) {
            return $detail->toArray();
        }

        if ($detail instanceof JsonSerializable) {
            $serialized = $detail->jsonSerialize();

            return is_array($serialized) ? $serialized : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function summary(array $detail, string $fallbackCode): array
    {
        return collect([
            'code' => $this->summaryCode($detail, $fallbackCode),
            'status' => $this->summaryStatus($detail),
            'display_status' => $detail['display_status'] ?? null,
            'amount' => $detail['amount'] ?? null,
            'currency' => $detail['currency'] ?? null,
            'claimed' => $detail['claimed'] ?? null,
            'fully_claimed' => $detail['fully_claimed'] ?? null,
            'created_at' => $detail['created_at'] ?? null,
            'starts_at' => $detail['starts_at'] ?? null,
            'expires_at' => $detail['expires_at'] ?? null,
            'redeemed_at' => $detail['redeemed_at'] ?? null,
        ])
            ->filter(fn (mixed $value, string $key): bool => $key === 'redeemed_at' || $value !== null)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function listRecord(array $row): ?CockpitPayCodeListRecordData
    {
        $code = $this->summaryCode($row, '');

        if ($code === '') {
            return null;
        }

        $status = $this->summaryStatus($row);

        return new CockpitPayCodeListRecordData(
            code: $code,
            template: $this->stringValue($row['template'] ?? null, 'Pay Code'),
            amount: $this->amountValue($row['amount'] ?? null),
            currency: $this->nullableString($row['currency'] ?? null),
            status: $status,
            display_status: $this->stringValue($row['display_status'] ?? null, $status),
            owner: $this->stringValue($row['owner'] ?? null, 'Redacted'),
            last_activity: $this->nullableString(
                $row['last_activity']
                    ?? $row['updated_at']
                    ?? $row['redeemed_at']
                    ?? $row['created_at']
                    ?? null
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function summaryCode(array $detail, string $fallbackCode): string
    {
        $code = $detail['code'] ?? null;

        if (is_scalar($code) && trim((string) $code) !== '') {
            return strtoupper(trim((string) $code));
        }

        return $fallbackCode;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function summaryStatus(array $detail): string
    {
        $status = $detail['status'] ?? null;

        if (is_scalar($status) && trim((string) $status) !== '') {
            return trim((string) $status);
        }

        return 'available';
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return null;
    }

    private function amountValue(mixed $value): string|int|float|null
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function countStatus($rows, string $status): int
    {
        return $rows
            ->filter(fn (array $row): bool => $this->summaryStatus($row) === $status)
            ->count();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, CockpitDashboardActivityData>
     */
    private function dashboardActivity($rows): array
    {
        return $rows
            ->map(fn (array $row): array => [
                'code' => $this->summaryCode($row, ''),
                'display_status' => $this->stringValue($row['display_status'] ?? null, $this->summaryStatus($row)),
                'timestamp' => $this->nullableString(
                    $row['updated_at']
                        ?? $row['redeemed_at']
                        ?? $row['expires_at']
                        ?? $row['created_at']
                        ?? null
                ),
            ])
            ->filter(fn (array $row): bool => $row['code'] !== '' && $row['timestamp'] !== null)
            ->sortByDesc('timestamp')
            ->take(3)
            ->map(fn (array $row): CockpitDashboardActivityData => new CockpitDashboardActivityData(
                id: $row['code'],
                label: $row['code'],
                description: 'Status: '.$row['display_status'],
                timestamp: $row['timestamp'],
                source: 'system',
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function excludedPayloadKeys(): array
    {
        return [
            'id',
            'voucher_id',
            'issuer_id',
            'instructions',
            'claims',
            'approval',
            'provider_payload',
            'raw_payload',
            'wallet',
            'provider',
        ];
    }
}
