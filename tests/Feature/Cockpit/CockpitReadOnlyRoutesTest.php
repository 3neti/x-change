<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers read-only cockpit routes under the x cockpit namespace', function () {
    expect(route('x-change.cockpit.dashboard'))->toBe('http://localhost/x/cockpit')
        ->and(route('x-change.cockpit.quick-generate'))->toBe('http://localhost/x/cockpit/quick-generate')
        ->and(route('x-change.cockpit.pay-codes.index'))->toBe('http://localhost/x/cockpit/pay-codes')
        ->and(route('x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001')
        ->and(route('x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001/distribution');
});

it('renders cockpit pages as read-only inertia endpoints', function (string $route, array $parameters, string $component) {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route($route, $parameters))
        ->assertOk()
        ->assertJsonPath('component', $component)
        ->assertJsonPath('props.can.view_cockpit', true)
        ->assertJsonPath('props.can.mutate_vouchers', false)
        ->assertJsonPath('props.can.execute_drivers', false)
        ->assertJsonPath('props.can.write_journal_entries', false)
        ->assertJsonPath('props.can.send_feedback', false)
        ->assertJsonPath('props.can.call_providers', false)
        ->assertJsonPath('props.can.move_money', false)
        ->assertJsonPath('props.redaction.policy', 'default-cockpit-redaction')
        ->assertJsonPath('props.redaction.payloads', 'redacted-until-authorized-read-models-exist');
})->with([
    'dashboard' => ['x-change.cockpit.dashboard', [], 'x-change/cockpit/Dashboard'],
    'quick generate' => ['x-change.cockpit.quick-generate', [], 'x-change/cockpit/QuickGenerate'],
    'pay code explorer' => ['x-change.cockpit.pay-codes.index', [], 'x-change/cockpit/PayCodeExplorer'],
    'voucher detail' => ['x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001'], 'x-change/cockpit/VoucherDetail'],
    'distribution workspace' => ['x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001'], 'x-change/cockpit/DistributionWorkspace'],
]);

it('keeps voucher route context explicit without loading voucher payloads', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']))
        ->assertOk()
        ->assertJsonPath('props.context.code', 'PC-READY-001')
        ->assertJsonMissingPath('props.voucher')
        ->assertJsonMissingPath('props.journal')
        ->assertJsonMissingPath('props.actions')
        ->assertJsonMissingPath('props.feedback')
        ->assertJsonMissingPath('props.provider_payload');
});

it('presents not wired cockpit read models on voucher scoped pages', function (string $route) {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route($route, ['code' => 'PC-READY-001']))
        ->assertOk()
        ->assertJsonPath('props.read_model.code', 'PC-READY-001')
        ->assertJsonPath('props.read_model.voucher.status', 'not_wired')
        ->assertJsonPath('props.read_model.voucher.authorized', false)
        ->assertJsonPath('props.read_model.execution.status', 'not_wired')
        ->assertJsonPath('props.read_model.execution.execution_id', null)
        ->assertJsonPath('props.read_model.journal.status', 'not_wired')
        ->assertJsonPath('props.read_model.actions.status', 'not_wired')
        ->assertJsonPath('props.read_model.feedback.status', 'not_wired')
        ->assertJsonPath('props.read_model.voucher.redactions.payloads', 'not-loaded')
        ->assertJsonPath('props.read_model.execution.redactions.payloads', 'not-loaded')
        ->assertJsonMissingPath('props.read_model.provider_payload')
        ->assertJsonMissingPath('props.read_model.raw_payload')
        ->assertJsonMissingPath('props.read_model.wallet')
        ->assertJsonMissingPath('props.read_model.provider');
})->with([
    'voucher detail' => 'x-change.cockpit.pay-codes.show',
    'distribution workspace' => 'x-change.cockpit.pay-codes.distribution',
]);

it('hydrates the pay code explorer with a sanitized list read model prop', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/PayCodeExplorer')
        ->assertJsonPath('props.pay_codes_read_model.status', 'available')
        ->assertJsonPath('props.pay_codes_read_model.authorized', true)
        ->assertJsonPath('props.pay_codes_read_model.query', null)
        ->assertJsonPath('props.pay_codes_read_model.records', [])
        ->assertJsonPath('props.pay_codes_read_model.redactions.payloads', 'sanitized-list-summary-only')
        ->assertJsonMissingPath('props.pay_codes_read_model.provider_payload')
        ->assertJsonMissingPath('props.pay_codes_read_model.raw_payload')
        ->assertJsonMissingPath('props.pay_codes_read_model.wallet')
        ->assertJsonMissingPath('props.pay_codes_read_model.provider');
});

it('hydrates the dashboard with a sanitized dashboard read model prop', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.dashboard_read_model.status', 'available')
        ->assertJsonPath('props.dashboard_read_model.authorized', true)
        ->assertJsonPath('props.dashboard_read_model.metrics.0.key', 'pay-codes-visible')
        ->assertJsonPath('props.dashboard_read_model.metrics.0.value', '0')
        ->assertJsonPath('props.dashboard_read_model.pipeline.0.key', 'issued')
        ->assertJsonPath('props.dashboard_read_model.pipeline.0.value', '0')
        ->assertJsonPath('props.dashboard_read_model.risk_signals.0.key', 'expired-pay-codes')
        ->assertJsonPath('props.dashboard_read_model.risk_signals.0.value', '0 sanitized summaries')
        ->assertJsonPath('props.dashboard_read_model.activity', [])
        ->assertJsonPath('props.dashboard_read_model.redactions.payloads', 'sanitized-dashboard-summary-only')
        ->assertJsonMissingPath('props.dashboard_read_model.provider_payload')
        ->assertJsonMissingPath('props.dashboard_read_model.raw_payload')
        ->assertJsonMissingPath('props.dashboard_read_model.wallet')
        ->assertJsonMissingPath('props.dashboard_read_model.provider');
});

it('exposes a read-only campaign cockpit read model prop on the dashboard route', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.schema', 'x-change.cockpit.campaign-adoption.v1')
        ->assertJsonPath('props.campaign_read_model.status', 'unavailable')
        ->assertJsonPath('props.campaign_read_model.authorized', false)
        ->assertJsonPath('props.campaign_read_model.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.facts', [])
        ->assertJsonPath('props.campaign_read_model.mutation.enabled', false)
        ->assertJsonPath('props.campaign_read_model.mutation.status', 'blocked')
        ->assertJsonPath('props.campaign_read_model.mutation.reason', 'campaign-mutations-not-authorized')
        ->assertJsonPath('props.campaign_read_model.redactions.payloads', 'not-loaded')
        ->assertJsonPath('props.campaign_read_model.redactions.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.redactions.reason', 'package-not-installed')
        ->assertJsonMissingPath('props.campaign_read_model.provider_payload')
        ->assertJsonMissingPath('props.campaign_read_model.raw_payload')
        ->assertJsonMissingPath('props.campaign_read_model.wallet')
        ->assertJsonMissingPath('props.campaign_read_model.campaign_mutation_endpoint')
        ->assertJsonMissingPath('props.campaign_read_model.pay_code_generation_payload')
        ->assertJsonMissingPath('props.campaign_read_model.delivery_dispatch_payload')
        ->assertJsonMissingPath('props.campaign_read_model.mutation_route');
});

it('passes optional campaign context from the dashboard route to the read-only campaign adapter', function () {
    $operator = actingAsTestUser();

    $service = new class
    {
        /**
         * @var array<string, mixed>
         */
        public array $received = [];

        /**
         * @param  array<string, mixed>  $metadata
         * @return array<string, mixed>
         */
        public function summary(
            string $planningKey,
            string $executionId,
            string $operatorId,
            string $channel = 'sms',
            ?string $correlationId = null,
            array $metadata = [],
        ): array {
            $this->received = compact('planningKey', 'executionId', 'operatorId', 'channel', 'correlationId', 'metadata');

            return [
                'status' => 'ready',
                'planning_key' => $planningKey,
                'execution_id' => $executionId,
                'operator_id' => $operatorId,
                'cards' => [
                    'campaign' => [
                        'name' => 'Food Aid July',
                        'recipient_count' => 250,
                        'secret' => 'do-not-show',
                    ],
                ],
                'panels' => [
                    'audience_import_workspace' => ['status' => 'ready'],
                ],
                'actions' => [
                    'review_campaign' => ['enabled' => true],
                    'generate_pay_codes' => ['enabled' => false],
                ],
                'effects' => [
                    'persists' => false,
                    'uses_database' => false,
                    'queues_jobs' => false,
                    'issues_pay_codes' => false,
                    'sends_feedback' => false,
                    'writes_journal' => false,
                    'moves_money' => false,
                ],
                'metadata' => [
                    'source' => 'fake-x-campaign',
                    'read_only' => true,
                    'token' => 'sensitive-token',
                ],
            ];
        }
    };

    config(['x-change.cockpit.integrations.campaign.cockpit' => 'fake.route.campaign.cockpit']);
    app()->instance('fake.route.campaign.cockpit', $service);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => ' campaign-plan-1 ',
            'campaign_execution_id' => ' execution-1 ',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.status', 'available')
        ->assertJsonPath('props.campaign_read_model.authorized', true)
        ->assertJsonPath('props.campaign_read_model.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.facts.planning_key', 'campaign-plan-1')
        ->assertJsonPath('props.campaign_read_model.facts.execution_id', 'execution-1')
        ->assertJsonPath('props.campaign_read_model.facts.operator_id', (string) $operator->getAuthIdentifier())
        ->assertJsonPath('props.campaign_read_model.facts.cards.campaign.name', 'Food Aid July')
        ->assertJsonPath('props.campaign_read_model.facts.cards.campaign.recipient_count', 250)
        ->assertJsonPath('props.campaign_read_model.facts.cards.campaign.secret', '[redacted]')
        ->assertJsonPath('props.campaign_read_model.facts.metadata.token', '[redacted]')
        ->assertJsonPath('props.campaign_read_model.mutation.enabled', false)
        ->assertJsonPath('props.campaign_read_model.redactions.payloads', 'campaign-cockpit-summary-only')
        ->assertJsonPath('props.campaign_read_model.redactions.routes_registered', false)
        ->assertJsonPath('props.campaign_read_model.redactions.controllers_registered', false)
        ->assertJsonPath('props.campaign_read_model.redactions.mutates_campaigns', false)
        ->assertJsonPath('props.campaign_read_model.redactions.issues_pay_codes', false)
        ->assertJsonPath('props.campaign_read_model.redactions.sends_feedback', false)
        ->assertJsonPath('props.campaign_read_model.redactions.writes_journal', false)
        ->assertJsonPath('props.campaign_read_model.redactions.moves_money', false)
        ->assertJsonMissingPath('props.campaign_read_model.provider_payload')
        ->assertJsonMissingPath('props.campaign_read_model.raw_payload')
        ->assertJsonMissingPath('props.campaign_read_model.wallet')
        ->assertJsonMissingPath('props.campaign_read_model.campaign_mutation_endpoint')
        ->assertJsonMissingPath('props.campaign_read_model.pay_code_generation_payload')
        ->assertJsonMissingPath('props.campaign_read_model.delivery_dispatch_payload')
        ->assertJsonMissingPath('props.campaign_read_model.mutation_route');

    expect($service->received)->toMatchArray([
        'planningKey' => 'campaign-plan-1',
        'executionId' => 'execution-1',
        'operatorId' => (string) $operator->getAuthIdentifier(),
        'channel' => 'cockpit',
        'correlationId' => 'execution-1',
        'metadata' => [
            'source' => 'x-change.cockpit',
            'read_only' => true,
            'integration' => 'campaign.cockpit',
        ],
    ]);
});

it('hydrates quick generate with a sanitized quick generate read model prop', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/QuickGenerate')
        ->assertJsonPath('props.quick_generate_read_model.status', 'available')
        ->assertJsonPath('props.quick_generate_read_model.authorized', true)
        ->assertJsonPath('props.quick_generate_read_model.templates.0.key', 'money-changer')
        ->assertJsonPath('props.quick_generate_read_model.templates.0.disabled', false)
        ->assertJsonPath('props.quick_generate_read_model.runtime_inputs.0.key', 'amount')
        ->assertJsonPath('props.quick_generate_read_model.pricing_summaries.0.key', 'pricing')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.0.key', 'template-selected')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.1.key', 'amount-input-present')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.1.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.2.key', 'pricing-service-wired')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.3.key', 'funding-source-selected')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.4.key', 'funds-reservation')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.5.key', 'provider-fee-quote')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.redactions.payloads', 'pricing-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.0.key', 'funding-policy-known')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.1.key', 'issuer-wallet-identified')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.1.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.2.key', 'wallet-balance-available')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.3.key', 'sufficient-funds')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.4.key', 'funds-reservation-ready')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.5.key', 'provider-funding-ready')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.redactions.payloads', 'funding-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.0.key', 'idempotency-policy-known')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.1.key', 'idempotency-key-source-defined')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.1.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.2.key', 'payload-fingerprint-defined')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.3.key', 'replay-lookup-ready')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.4.key', 'conflict-response-ready')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.5.key', 'ttl-policy-ready')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.redactions.payloads', 'idempotency-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.0.key', 'request-schema-known')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.1.key', 'required-fields-defined')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.1.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.2.key', 'validation-rules-wired')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.3.key', 'sensitive-fields-redacted')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.4.key', 'sanitized-preview-ready')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.5.key', 'validation-error-contract-ready')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.redactions.payloads', 'validation-redaction-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.0.key', 'existing-issuance-owner-identified')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.1.key', 'generate-pay-code-action-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.1.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.2.key', 'generate-pay-code-controller-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.3.key', 'preconditions-green')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.4.key', 'side-effect-boundary-confirmed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.5.key', 'operator-response-contract-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.redactions.payloads', 'mutation-handoff-plan-only')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.recommendation', 'remain-read-only')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.0.key', 'authorization-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.0.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.1.key', 'pricing-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.2.key', 'funding-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.3.key', 'idempotency-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.4.key', 'validation-redaction-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.5.key', 'handoff-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.6.key', 'operator-response-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.redactions.payloads', 'mutation-preconditions-review-only')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.decision', 'not_authorized')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.required_approval', 'human-approval-required-before-route-scaffold')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.rationale', 'Mutation preconditions remain blocked; Cockpit must not register a write route until explicit human approval and a smaller mutation contract exist.')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.next_step', 'request-explicit-approval-or-continue-read-only-hardening')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.redactions.payloads', 'mutation-authorization-decision-only')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.schema', 'x-change.cockpit.quick-generate-draft.v1')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.status', 'draft_only')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.template_key', 'money-changer')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.amount', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.currency', 'PHP')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.recipient_reference', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.idempotency_key', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.redactions.payloads', 'draft-shape-only')
        ->assertJsonPath('props.quick_generate_read_model.authorization.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.0.key', 'operator-authenticated')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.2.key', 'can-generate-pay-code')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.2.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.3.key', 'can-call-providers')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.4.key', 'can-move-money')
        ->assertJsonPath('props.quick_generate_read_model.authorization.redactions.payloads', 'authorization-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.action.enabled', false)
        ->assertJsonPath('props.quick_generate_read_model.action.reason', 'issuance-not-wired')
        ->assertJsonPath('props.quick_generate_read_model.redactions.payloads', 'sanitized-quick-generate-catalog-only')
        ->assertJsonMissingPath('props.quick_generate_read_model.provider_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.raw_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.wallet')
        ->assertJsonMissingPath('props.quick_generate_read_model.balance')
        ->assertJsonMissingPath('props.quick_generate_read_model.available_balance')
        ->assertJsonMissingPath('props.quick_generate_read_model.provider_wallet')
        ->assertJsonMissingPath('props.quick_generate_read_model.funding_source')
        ->assertJsonMissingPath('props.quick_generate_read_model.idempotency_key')
        ->assertJsonMissingPath('props.quick_generate_read_model.payload_fingerprint')
        ->assertJsonMissingPath('props.quick_generate_read_model.stored_response')
        ->assertJsonMissingPath('props.quick_generate_read_model.replay_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.cache_key')
        ->assertJsonMissingPath('props.quick_generate_read_model.request_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.validated_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.validation_errors')
        ->assertJsonMissingPath('props.quick_generate_read_model.mobile')
        ->assertJsonMissingPath('props.quick_generate_read_model.email')
        ->assertJsonMissingPath('props.quick_generate_read_model.recipient_reference')
        ->assertJsonMissingPath('props.quick_generate_read_model.account_number')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.issued_voucher')
        ->assertJsonMissingPath('props.quick_generate_read_model.generated_pay_code')
        ->assertJsonMissingPath('props.quick_generate_read_model.side_effect_result')
        ->assertJsonMissingPath('props.quick_generate_read_model.precondition_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_approval')
        ->assertJsonMissingPath('props.quick_generate_read_model.approval_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.route_definition')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_route');
});

it('requires authentication for cockpit routes', function (string $route, array $parameters) {
    $this->withHeader('Accept', 'application/json')
        ->get(route($route, $parameters))
        ->assertUnauthorized();
})->with([
    'dashboard' => ['x-change.cockpit.dashboard', []],
    'quick generate' => ['x-change.cockpit.quick-generate', []],
    'pay code explorer' => ['x-change.cockpit.pay-codes.index', []],
    'voucher detail' => ['x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']],
    'distribution workspace' => ['x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001']],
]);

it('does not register cockpit mutation routes', function () {
    $mutatingRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'x-change.cockpit.'))
        ->filter(fn ($route): bool => collect($route->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->isNotEmpty()
        );

    expect($mutatingRoutes)->toHaveCount(0);
});

it('documents the quick generate issuance boundary before mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/004-quick-generate-issuance-boundary-plan.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 17')
        ->and($report)->toContain('Existing issuance owner')
        ->and($report)->toContain('GeneratePayCode')
        ->and($report)->toContain('GeneratePayCodeController')
        ->and($report)->toContain('Authorization')
        ->and($report)->toContain('Pricing')
        ->and($report)->toContain('Funding')
        ->and($report)->toContain('Idempotency')
        ->and($report)->toContain('Redaction')
        ->and($report)->toContain('No Cockpit mutation route is registered in Slice 17');
});

it('documents the quick generate request draft contract before persistence or mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/005-quick-generate-request-draft-contract-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 18')
        ->and($report)->toContain('x-change.cockpit.quick-generate-draft.v1')
        ->and($report)->toContain('template_key')
        ->and($report)->toContain('amount')
        ->and($report)->toContain('currency')
        ->and($report)->toContain('recipient_reference')
        ->and($report)->toContain('purpose')
        ->and($report)->toContain('idempotency_key')
        ->and($report)->toContain('Drafts are local and read-only in Slice 18')
        ->and($report)->toContain('No draft persistence or mutation route is registered in Slice 18');
});

it('documents the quick generate authorization gates before mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/006-quick-generate-authorization-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 19')
        ->and($report)->toContain('operator-authenticated')
        ->and($report)->toContain('can-view-cockpit')
        ->and($report)->toContain('can-generate-pay-code')
        ->and($report)->toContain('can-call-providers')
        ->and($report)->toContain('can-move-money')
        ->and($report)->toContain('Authorization gates are read-only facts in Slice 19')
        ->and($report)->toContain('No authorization gate enables generation in Slice 19');
});

it('documents the quick generate pricing gates before calculation or reservation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/007-quick-generate-pricing-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 20')
        ->and($report)->toContain('template-selected')
        ->and($report)->toContain('amount-input-present')
        ->and($report)->toContain('pricing-service-wired')
        ->and($report)->toContain('funding-source-selected')
        ->and($report)->toContain('funds-reservation')
        ->and($report)->toContain('provider-fee-quote')
        ->and($report)->toContain('Pricing gates are read-only facts in Slice 20')
        ->and($report)->toContain('No pricing gate calculates prices, reserves funds, or calls providers in Slice 20');
});

it('documents the quick generate funding gates before wallet access or reservation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/008-quick-generate-funding-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 21')
        ->and($report)->toContain('funding-policy-known')
        ->and($report)->toContain('issuer-wallet-identified')
        ->and($report)->toContain('wallet-balance-available')
        ->and($report)->toContain('sufficient-funds')
        ->and($report)->toContain('funds-reservation-ready')
        ->and($report)->toContain('provider-funding-ready')
        ->and($report)->toContain('Funding gates are read-only facts in Slice 21')
        ->and($report)->toContain('No funding gate reads wallets, reserves funds, debits balances, or calls providers in Slice 21');
});

it('documents the quick generate idempotency gates before persistence or mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/009-quick-generate-idempotency-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 22')
        ->and($report)->toContain('idempotency-policy-known')
        ->and($report)->toContain('idempotency-key-source-defined')
        ->and($report)->toContain('payload-fingerprint-defined')
        ->and($report)->toContain('replay-lookup-ready')
        ->and($report)->toContain('conflict-response-ready')
        ->and($report)->toContain('ttl-policy-ready')
        ->and($report)->toContain('Idempotency gates are read-only facts in Slice 22')
        ->and($report)->toContain('No idempotency gate persists keys, fingerprints payloads, reads replay records, or enables mutation routes in Slice 22');
});

it('documents the quick generate validation and redaction gates before request handling or mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/010-quick-generate-validation-redaction-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 23')
        ->and($report)->toContain('request-schema-known')
        ->and($report)->toContain('required-fields-defined')
        ->and($report)->toContain('validation-rules-wired')
        ->and($report)->toContain('sensitive-fields-redacted')
        ->and($report)->toContain('sanitized-preview-ready')
        ->and($report)->toContain('validation-error-contract-ready')
        ->and($report)->toContain('Validation and redaction gates are read-only facts in Slice 23')
        ->and($report)->toContain('No validation/redaction gate validates requests, persists payloads, exposes submitted PII, or enables mutation routes in Slice 23');
});

it('documents the quick generate mutation handoff boundary before mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/011-quick-generate-mutation-handoff-boundary-plan.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 24')
        ->and($report)->toContain('existing-issuance-owner-identified')
        ->and($report)->toContain('generate-pay-code-action-handoff')
        ->and($report)->toContain('generate-pay-code-controller-handoff')
        ->and($report)->toContain('preconditions-green')
        ->and($report)->toContain('side-effect-boundary-confirmed')
        ->and($report)->toContain('operator-response-contract-ready')
        ->and($report)->toContain('Mutation handoff remains a read-only boundary plan in Slice 24')
        ->and($report)->toContain('No Cockpit mutation route calls GeneratePayCode, GeneratePayCodeController, providers, wallets, journal, action, or feedback in Slice 24');
});

it('documents the quick generate mutation preconditions review before mutation approval', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/012-quick-generate-mutation-preconditions-review.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 25')
        ->and($report)->toContain('authorization-ready')
        ->and($report)->toContain('pricing-ready')
        ->and($report)->toContain('funding-ready')
        ->and($report)->toContain('idempotency-ready')
        ->and($report)->toContain('validation-redaction-ready')
        ->and($report)->toContain('handoff-ready')
        ->and($report)->toContain('operator-response-ready')
        ->and($report)->toContain('Mutation preconditions remain blocked in Slice 25')
        ->and($report)->toContain('No Cockpit mutation route, mutation approval, request validation execution, voucher issuance, provider call, wallet access, journal write, action run, or feedback delivery is introduced in Slice 25');
});

it('documents the quick generate mutation authorization decision point before mutation route scaffolding', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/013-quick-generate-mutation-authorization-decision-point.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 26')
        ->and($report)->toContain('Mutation Authorization Decision Point')
        ->and($report)->toContain('not_authorized')
        ->and($report)->toContain('human-approval-required-before-route-scaffold')
        ->and($report)->toContain('request-explicit-approval-or-continue-read-only-hardening')
        ->and($report)->toContain('No Cockpit mutation route is authorized in Slice 26')
        ->and($report)->toContain('No mutation endpoints, voucher issuance, request validation execution, payload persistence, provider call, wallet access, journal write, action run, feedback delivery, campaign behavior, or money movement is introduced in Slice 26');
});

it('documents the cross package read model integration baseline before mutations', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/014-cross-package-read-model-integration-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 27')
        ->and($report)->toContain('x-journal evidence summaries')
        ->and($report)->toContain('x-action safe CTA/action summaries')
        ->and($report)->toContain('x-feedback communication delivery summaries')
        ->and($report)->toContain('not hard Composer dependencies')
        ->and($report)->toContain('do not write journal entries')
        ->and($report)->toContain('do not execute or authorize actions')
        ->and($report)->toContain('do not send, retry, or call providers');
});
