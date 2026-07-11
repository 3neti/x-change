<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use JsonSerializable;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Cockpit\CockpitCampaignReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardActivityData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardPipelineStageData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardRiskSignalData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityReadModelData;
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
        private readonly ?DurableCockpitOperatorIssuanceActivityReadModelProvider $operatorIssuanceActivity = null,
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
                    value: 'Use the Quick Generate form',
                    helper: 'Pricing and funding preflights appear after a successful form submit.',
                ),
                new CockpitQuickGenerateRuntimeInputData(
                    key: 'recipient',
                    label: 'Recipient',
                    value: 'Use the Quick Generate form',
                    helper: 'Recipient reference is submitted through the existing issuance handoff.',
                ),
                new CockpitQuickGenerateRuntimeInputData(
                    key: 'purpose',
                    label: 'Purpose',
                    value: 'Optional form note',
                    helper: 'Purpose/message is passed as operator-safe issuance context.',
                ),
            ],
            pricing_summaries: [
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'pricing',
                    label: 'Pricing Estimate',
                    value: 'Shown after submit',
                    helper: 'The result panel displays the operator-safe pricing preflight returned by the existing runtime.',
                ),
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'funding',
                    label: 'Funding Impact',
                    value: 'Shown after submit',
                    helper: 'The result panel displays the operator-safe funding preflight; reservation and money movement remain behind existing issuance services.',
                ),
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'execution',
                    label: 'Execution Summary',
                    value: 'Existing handoff',
                    helper: 'Quick Generate compiles a draft and hands off to GeneratePayCode; execution semantics stay voucher-owned.',
                ),
            ],
            pricing_gate: new CockpitQuickGeneratePricingGateData(
                status: 'runtime-informational',
                checks: [
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'template-selected',
                        label: 'Template Selected',
                        status: 'passed',
                        reason: 'The Money Changer template is selected by default for the current Quick Generate runtime.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'amount-input-present',
                        label: 'Amount Input Present',
                        status: 'passed',
                        reason: 'The Quick Generate form accepts an operator amount and submits it to the existing issuance handoff.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'pricing-service-wired',
                        label: 'Pricing Service Wired',
                        status: 'passed',
                        reason: 'The mutation result exposes an operator-safe pricing preflight after GeneratePayCode completes.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'funding-source-selected',
                        label: 'Funding Source Selected',
                        status: 'runtime-diagnostic',
                        reason: 'Funding source details remain redacted; the operator sees only the safe funding preflight result after submit.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'funds-reservation',
                        label: 'Funds Reservation',
                        status: 'blocked',
                        reason: 'Cockpit does not reserve, debit, or hold funds directly; those effects remain behind the existing issuance services.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'provider-fee-quote',
                        label: 'Provider Fee Quote',
                        status: 'blocked',
                        reason: 'Cockpit does not call provider quote APIs directly.',
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
                status: 'runtime-informational',
                checks: [
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'funding-policy-known',
                        label: 'Funding Policy Known',
                        status: 'passed',
                        reason: 'Funding preflight is represented as an operator-safe result after Quick Generate submits.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'issuer-wallet-identified',
                        label: 'Issuer Wallet Identified',
                        status: 'runtime-diagnostic',
                        reason: 'Issuer funding details are evaluated by the existing issuance path and redacted from the Cockpit read model.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'wallet-balance-available',
                        label: 'Wallet Balance Available',
                        status: 'runtime-diagnostic',
                        reason: 'The operator sees only the safe balance/funding preflight summary returned by the issuance runtime.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'sufficient-funds',
                        label: 'Sufficient Funds',
                        status: 'runtime-diagnostic',
                        reason: 'Sufficiency is reported as an operator-safe preflight after submit; raw wallet data remains hidden.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'funds-reservation-ready',
                        label: 'Funds Reservation Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not reserve, hold, debit, or transfer funds directly.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'provider-funding-ready',
                        label: 'Provider Funding Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not call provider funding or account-readiness services directly.',
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
                status: 'backend-ready',
                checks: [
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'idempotency-policy-known',
                        label: 'Idempotency Policy Known',
                        status: 'passed',
                        reason: 'Cockpit uses the existing x-change idempotency policy for Quick Generate mutation requests.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'idempotency-key-source-defined',
                        label: 'Idempotency Key Source Defined',
                        status: 'passed',
                        reason: 'Cockpit accepts the configured Idempotency-Key header on the Quick Generate mutation route.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'payload-fingerprint-defined',
                        label: 'Payload Fingerprint Defined',
                        status: 'passed',
                        reason: 'Cockpit delegates payload fingerprinting to the existing IdempotencyService.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'replay-lookup-ready',
                        label: 'Replay Lookup Ready',
                        status: 'passed',
                        reason: 'Cockpit replays stored redacted operator responses for matching keys and payloads.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'conflict-response-ready',
                        label: 'Conflict Response Ready',
                        status: 'passed',
                        reason: 'Cockpit returns the existing idempotency conflict response before a second issuance action call.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'ttl-policy-ready',
                        label: 'TTL Policy Ready',
                        status: 'passed',
                        reason: 'Cockpit uses the existing IdempotencyService TTL configuration.',
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
                status: 'backend-ready',
                checks: [
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'request-schema-known',
                        label: 'Request Schema Known',
                        status: 'passed',
                        reason: 'The Quick Generate mutation request shape is known and handled by the existing handoff route.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'required-fields-defined',
                        label: 'Required Fields Defined',
                        status: 'passed',
                        reason: 'The Quick Generate form submits the required issuance fields to the existing GeneratePayCode request path.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'validation-rules-wired',
                        label: 'Validation Rules Wired',
                        status: 'passed',
                        reason: 'The Cockpit handoff route uses GeneratePayCodeRequest-compatible validation.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'sensitive-fields-redacted',
                        label: 'Sensitive Fields Redacted',
                        status: 'passed',
                        reason: 'Operator responses exclude raw payloads, provider payloads, wallet details, and idempotency internals.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'sanitized-preview-ready',
                        label: 'Sanitized Preview Ready',
                        status: 'passed',
                        reason: 'The result panel renders sanitized generated facts and preflight summaries only.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'validation-error-contract-ready',
                        label: 'Validation Error Contract Ready',
                        status: 'passed',
                        reason: 'Validation errors remain on the Quick Generate form through the Inertia handoff route.',
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
                status: 'backend-handoff-wired',
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
                        status: 'passed',
                        reason: 'Cockpit POST route calls the existing GeneratePayCode action in Wave 1C.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'generate-pay-code-controller-handoff',
                        label: 'GeneratePayCodeController Handoff',
                        status: 'confirmed',
                        reason: 'The public API route remains owned by GeneratePayCodeController while Cockpit shares the action directly.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'preconditions-green',
                        label: 'Preconditions Green',
                        status: 'blocked',
                        reason: 'Provider, journal, action, and feedback side effects remain separately gated.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'side-effect-boundary-confirmed',
                        label: 'Side Effect Boundary Confirmed',
                        status: 'passed',
                        reason: 'Cockpit does not call providers, wallets, journal, action, or feedback directly; issuance side effects remain behind GeneratePayCode.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'operator-response-contract-ready',
                        label: 'Operator Response Contract Ready',
                        status: 'passed',
                        reason: 'Cockpit returns only operator-safe generated facts from the existing issuance action.',
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
                status: 'existing-handoff-ready',
                recommendation: 'use-existing-issuance-handoff',
                items: [
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'authorization-ready',
                        label: 'Authorization Ready',
                        status: 'passed',
                        reason: 'The authenticated Cockpit route may submit through the approved GeneratePayCode handoff.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'pricing-ready',
                        label: 'Pricing Ready',
                        status: 'runtime-informational',
                        reason: 'Pricing preflight is available in the operator-safe result panel after submit.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'funding-ready',
                        label: 'Funding Ready',
                        status: 'runtime-informational',
                        reason: 'Funding preflight is available in the operator-safe result panel after submit; raw wallet details remain redacted.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'idempotency-ready',
                        label: 'Idempotency Ready',
                        status: 'passed',
                        reason: 'Wave 1D wires idempotency key extraction, payload fingerprinting, replay lookup, conflict response, and TTL policy through the existing IdempotencyService.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'validation-redaction-ready',
                        label: 'Validation and Redaction Ready',
                        status: 'passed',
                        reason: 'GeneratePayCodeRequest-compatible validation and operator-safe response redaction are wired.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'handoff-ready',
                        label: 'Handoff Ready',
                        status: 'passed',
                        reason: 'Wave 1C wires the GeneratePayCode action handoff and confirms the public GeneratePayCodeController route remains unchanged.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'operator-response-ready',
                        label: 'Operator Response Ready',
                        status: 'passed',
                        reason: 'Cockpit returns a redacted operator result with generated Pay Code, preflights, and activity runtime diagnostics.',
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
                status: 'approved-handoff',
                decision: 'authorized_existing_handoff',
                required_approval: 'completed-for-existing-generate-pay-code-handoff',
                rationale: 'Cockpit may submit Quick Generate through the existing GeneratePayCode action without inventing a parallel issuance runtime.',
                next_step: 'keep-provider-journal-action-feedback-mutations-separately-gated',
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
                status: 'existing_issuance_handoff_registered',
                authorization: 'operator-authenticated-handoff-route',
                route: 'x-change.cockpit.quick-generate.store',
                route_url: Route::has('x-change.cockpit.quick-generate.store')
                    ? route('x-change.cockpit.quick-generate.store', [], false)
                    : null,
                request_adapter: 'GeneratePayCodeRequest-compatible-adapter',
                issuance_owner: 'GeneratePayCode',
                idempotency: 'replay-safe-route-registered',
                response_contract: 'operator-safe-redacted-result',
                runtime_enabled: true,
                gates: [
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'route-contract-defined',
                        label: 'Route Contract Defined',
                        status: 'passed',
                        decision: 'POST route is registered under the reserved route name.',
                        reason: 'Wave 1C reuses the Wave 1B route shell for the existing issuance handoff.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'request-adapter-defined',
                        label: 'Request Adapter Defined',
                        status: 'passed',
                        decision: 'Cockpit route uses GeneratePayCodeRequest validation.',
                        reason: 'Cockpit does not invent a second issuance validation language.',
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
                        status: 'passed',
                        decision: 'Idempotency key and replay handling are wired through the existing IdempotencyService.',
                        reason: 'Repeated operator submits with the same key and payload replay the stored operator response without duplicate issuance.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'operator-response-redacted',
                        label: 'Operator Response Redacted',
                        status: 'passed',
                        decision: 'Response exposes operator-safe generated facts only.',
                        reason: 'Provider payloads, wallet data, raw voucher payloads, secrets, and internal IDs remain excluded.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'ui-submit-disabled',
                        label: 'UI Submit Enabled',
                        status: 'passed',
                        decision: 'Cockpit UI may submit only to the idempotency-protected route URL from the read model.',
                        reason: 'Wave 1E enables a guarded submit control while keeping refresh, redirect, and optimistic UI deferred.',
                    ),
                ],
                allowed_methods: ['GET', 'POST'],
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
                status: 'runtime-ready',
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
                        status: 'passed',
                        reason: 'The approved Cockpit Quick Generate mutation route submits through the existing GeneratePayCode action.',
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
                enabled: true,
                reason: 'existing-issuance-handoff-enabled',
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

    public function forOperatorIssuanceActivity(CockpitReadModelQueryData $query): CockpitOperatorIssuanceActivityReadModelData
    {
        return $this->operatorIssuanceActivity?->forOperator($query)
            ?? $this->fallback->forOperatorIssuanceActivity($query);
    }

    public function forPayCodeList(CockpitReadModelQueryData $query): CockpitPayCodeListReadModelData
    {
        $queryCode = $this->normalizeCode($query->code);
        $rows = collect($this->vouchers->list())
            ->map(fn (mixed $row): ?CockpitPayCodeListRecordData => $this->listRecord($this->toArray($row)))
            ->filter()
            ->when($queryCode !== null, fn ($rows) => $rows->filter(
                fn (CockpitPayCodeListRecordData $record): bool => str_contains($record->code, $queryCode)
            ))
            ->values()
            ->all();

        return new CockpitPayCodeListReadModelData(
            status: 'available',
            authorized: true,
            query: $queryCode,
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
