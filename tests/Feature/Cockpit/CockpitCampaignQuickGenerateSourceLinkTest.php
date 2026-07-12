<?php

declare(strict_types=1);

it('hydrates a safe campaign quick generate source link on the dashboard read model', function (): void {
    actingAsTestUser();

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => 'plan-37b',
            'campaign_execution_id' => 'exec-37b',
            'campaign_id' => 'campaign-37b',
            'campaign_audience_id' => 'audience-37b',
            'campaign_recipient_id' => 'recipient-37b',
            'campaign_source' => 'campaign_cockpit',
            'campaign_template_key' => 'ofw-remittance',
            'campaign_amount' => '500.00',
            'campaign_currency' => 'PHP',
            'campaign_recipient_reference' => '09173011987',
            'campaign_purpose' => 'Campaign payout',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.schema', 'x-change.cockpit.campaign-quick-generate-link.v1')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.status', 'available')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.enabled', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.read_only', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.mutates_campaign', false)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.route', 'x-change.cockpit.quick-generate')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.label', 'Open Quick Generate')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.planning_key', 'plan-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.execution_id', 'exec-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.campaign_id', 'campaign-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.audience_id', 'audience-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.recipient_id', 'recipient-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.source', 'campaign_cockpit')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.template_key', 'ofw-remittance')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.amount', '500.00')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.currency', 'PHP')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.recipient_reference', '09173011987')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.purpose', 'Campaign payout')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.redactions.payloads', 'campaign-source-link-query-only')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.raw_payload')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.provider_payload')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.wallet')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.pay_code_generation_payload')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.delivery_dispatch_payload')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.mutation_route');

    $href = $response->json('props.campaign_read_model.quick_generate_link.href');

    expect($href)
        ->toBeString()
        ->toContain('/x/cockpit/quick-generate')
        ->toContain('campaign_planning_key=plan-37b')
        ->toContain('campaign_execution_id=exec-37b')
        ->toContain('campaign_id=campaign-37b')
        ->toContain('campaign_audience_id=audience-37b')
        ->toContain('campaign_recipient_id=recipient-37b')
        ->toContain('campaign_source=campaign_cockpit')
        ->toContain('campaign_template_key=ofw-remittance')
        ->toContain('campaign_amount=500.00')
        ->toContain('campaign_currency=PHP')
        ->toContain('campaign_recipient_reference=09173011987')
        ->toContain('campaign_purpose=Campaign%20payout');
});

it('keeps the campaign quick generate source link unavailable without campaign context', function (): void {
    actingAsTestUser();

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.schema', 'x-change.cockpit.campaign-quick-generate-link.v1')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.status', 'not_available')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.enabled', false)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.href', null)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.read_only', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.mutates_campaign', false);
});

it('hydrates campaign quick generate source link draft fields from adapter metadata', function (): void {
    actingAsTestUser();

    config(['x-change.cockpit.integrations.campaign.cockpit' => 'fake.wave38.campaign.cockpit']);
    app()->instance('fake.wave38.campaign.cockpit', new class
    {
        /**
         * @param  array<string, mixed>  $metadata
         * @return array<string, mixed>
         */
        public function summary(
            string $planningKey,
            string $executionId,
            string $operatorId,
            string $channel = 'cockpit',
            ?string $correlationId = null,
            array $metadata = [],
        ): array {
            return [
                'status' => 'ready',
                'planning_key' => $planningKey,
                'execution_id' => $executionId,
                'operator_id' => $operatorId,
                'cards' => [
                    'campaign' => [
                        'campaign_id' => 'campaign-38b',
                        'name' => 'Adapter Campaign',
                        'recipient_count' => 1,
                    ],
                ],
                'metadata' => [
                    'source' => 'fake-x-campaign',
                    'read_only' => true,
                    'quick_generate_context' => [
                        'campaign_id' => 'campaign-38b',
                        'audience_id' => 'audience-38b',
                        'recipient_id' => 'recipient-38b',
                        'source' => 'x_campaign_adapter',
                        'template_key' => 'ofw-remittance',
                        'amount' => '750.00',
                        'currency' => 'PHP',
                        'recipient_reference' => '091700000038',
                        'purpose' => 'Adapter sourced payout',
                        'raw_payload' => 'must-not-render',
                    ],
                ],
                'effects' => [
                    'issues_pay_codes' => false,
                    'sends_feedback' => false,
                    'writes_journal' => false,
                    'moves_money' => false,
                ],
            ];
        }
    });

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => 'plan-38b',
            'campaign_execution_id' => 'exec-38b',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.status', 'available')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.source', 'x_campaign_adapter')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.campaign_id', 'campaign-38b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.audience_id', 'audience-38b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.recipient_id', 'recipient-38b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.template_key', 'ofw-remittance')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.amount', '750.00')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.currency', 'PHP')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.recipient_reference', '091700000038')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.purpose', 'Adapter sourced payout')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.redactions.payloads', 'campaign-source-link-query-only')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.raw_payload');

    $href = $response->json('props.campaign_read_model.quick_generate_link.href');

    expect($href)
        ->toBeString()
        ->toContain('campaign_planning_key=plan-38b')
        ->toContain('campaign_execution_id=exec-38b')
        ->toContain('campaign_id=campaign-38b')
        ->toContain('campaign_audience_id=audience-38b')
        ->toContain('campaign_recipient_id=recipient-38b')
        ->toContain('campaign_source=x_campaign_adapter')
        ->toContain('campaign_template_key=ofw-remittance')
        ->toContain('campaign_amount=750.00')
        ->toContain('campaign_recipient_reference=091700000038')
        ->toContain('campaign_purpose=Adapter%20sourced%20payout')
        ->not->toContain('must-not-render');
});

it('prefers explicit dashboard source link query values over adapter metadata', function (): void {
    actingAsTestUser();

    config(['x-change.cockpit.integrations.campaign.cockpit' => 'fake.wave38.override.campaign.cockpit']);
    app()->instance('fake.wave38.override.campaign.cockpit', new class
    {
        /**
         * @param  array<string, mixed>  $metadata
         * @return array<string, mixed>
         */
        public function summary(
            string $planningKey,
            string $executionId,
            string $operatorId,
            string $channel = 'cockpit',
            ?string $correlationId = null,
            array $metadata = [],
        ): array {
            return [
                'status' => 'ready',
                'planning_key' => $planningKey,
                'execution_id' => $executionId,
                'operator_id' => $operatorId,
                'metadata' => [
                    'quick_generate_context' => [
                        'template_key' => 'adapter-template',
                        'amount' => '999.00',
                        'currency' => 'USD',
                        'recipient_reference' => 'adapter-recipient',
                        'purpose' => 'Adapter purpose',
                    ],
                ],
            ];
        }
    });

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => 'plan-38b-override',
            'campaign_execution_id' => 'exec-38b-override',
            'campaign_template_key' => 'ofw-remittance',
            'campaign_amount' => '500.00',
            'campaign_currency' => 'PHP',
            'campaign_recipient_reference' => '09173011987',
            'campaign_purpose' => 'Explicit payout',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.template_key', 'ofw-remittance')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.amount', '500.00')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.currency', 'PHP')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.recipient_reference', '09173011987')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.purpose', 'Explicit payout');
});

it('normalizes campaign adapter template intent into the quick generate source link', function (): void {
    actingAsTestUser();

    config(['x-change.cockpit.integrations.campaign.cockpit' => 'fake.wave39.campaign.cockpit']);
    app()->instance('fake.wave39.campaign.cockpit', new class
    {
        /**
         * @param  array<string, mixed>  $metadata
         * @return array<string, mixed>
         */
        public function summary(
            string $planningKey,
            string $executionId,
            string $operatorId,
            string $channel = 'cockpit',
            ?string $correlationId = null,
            array $metadata = [],
        ): array {
            return [
                'status' => 'ready',
                'planning_key' => $planningKey,
                'execution_id' => $executionId,
                'operator_id' => $operatorId,
                'metadata' => [
                    'quick_generate_context' => [
                        'campaign_id' => 'campaign-39c',
                        'audience_id' => 'audience-39c',
                        'recipient_id' => 'recipient-39c',
                        'source' => 'x_campaign_adapter',
                        'template_intent' => 'money_changer',
                        'amount' => '125.00',
                        'currency' => 'PHP',
                        'recipient_reference' => '091700000039',
                        'purpose' => 'Template intent payout',
                    ],
                ],
            ];
        }
    });

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => 'plan-39c',
            'campaign_execution_id' => 'exec-39c',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.status', 'available')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.template_key', 'money-changer')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.amount', '125.00')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.recipient_reference', '091700000039')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.mutates_campaign', false);

    $href = $response->json('props.campaign_read_model.quick_generate_link.href');

    expect($href)
        ->toBeString()
        ->toContain('campaign_template_key=money-changer')
        ->toContain('campaign_source=x_campaign_adapter')
        ->not->toContain('template_intent');
});
