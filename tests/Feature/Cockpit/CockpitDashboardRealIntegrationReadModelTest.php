<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use LBHurtado\XChange\Models\VoucherClaim;

it('hydrates dashboard connected services from installed read-only integration packages', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.read_model.code', null)
        ->assertJsonPath('props.read_model.voucher.status', 'not_wired')
        ->assertJsonPath('props.read_model.execution.status', 'not_wired')
        ->assertJsonPath('props.read_model.journal.status', 'available')
        ->assertJsonPath('props.read_model.journal.authorized', true)
        ->assertJsonPath('props.read_model.journal.redactions.source', 'x-journal')
        ->assertJsonPath('props.read_model.journal.redactions.evidence_only', true)
        ->assertJsonPath('props.read_model.journal.redactions.writes_journal_entries', false)
        ->assertJsonPath('props.read_model.actions.status', 'available')
        ->assertJsonPath('props.read_model.actions.authorized', true)
        ->assertJsonPath('props.read_model.actions.redactions.source', 'x-action')
        ->assertJsonPath('props.read_model.actions.redactions.presentation_only', true)
        ->assertJsonPath('props.read_model.actions.redactions.executes_action', false)
        ->assertJsonPath('props.read_model.feedback.status', 'available')
        ->assertJsonPath('props.read_model.feedback.authorized', true)
        ->assertJsonPath('props.read_model.feedback.redactions.source', 'x-feedback')
        ->assertJsonPath('props.read_model.feedback.redactions.communication_state_only', true)
        ->assertJsonPath('props.read_model.feedback.redactions.sends_feedback', false)
        ->assertJsonMissingPath('props.read_model.provider_payload')
        ->assertJsonMissingPath('props.read_model.raw_payload')
        ->assertJsonMissingPath('props.read_model.wallet');
});

it('promotes claimed Pay Code facts into dashboard activity without exposing raw contact data', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher();
    $voucher->forceFill([
        'redeemed_at' => Carbon::parse('2026-09-01 10:15:00', 'Asia/Manila'),
    ])->save();

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'claim',
        'status' => 'paid',
        'requested_amount_minor' => 5500,
        'disbursed_amount_minor' => 5500,
        'remaining_balance_minor' => 0,
        'currency' => 'PHP',
        'claimer_mobile' => '09173011987',
        'completed_at' => Carbon::parse('2026-09-01 10:16:00', 'Asia/Manila'),
    ]);

    $response = $this->actingAs($issuer)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonMissing(['09173011987']);

    $activity = collect($response->json('props.dashboard_read_model.activity'))
        ->firstWhere('code', $voucher->code);

    expect($activity)->toMatchArray([
        'label' => $voucher->code.' claimed',
        'projection_badge' => 'Claimed',
        'projection_status' => 'redeemed',
        'projection_detail' => 'Recipient-facing completion',
        'code' => $voucher->code,
        'amount' => 'PHP 55.00',
        'target_label' => '•••• 1987',
        'detail_href' => route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code], false),
    ])
        ->and($activity['claim_summary']['schema'])->toBe('x-change.cockpit.pay-code-claim-summary.v1')
        ->and($activity['claim_summary']['claimed_mobile_masked'])->toBe('•••• 1987')
        ->and($activity['claim_summary']['amount_minor'])->toBe(5500);
});

it('hydrates dashboard campaign package presence from installed x-campaign without selected campaign context', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.status', 'available')
        ->assertJsonPath('props.campaign_read_model.authorized', true)
        ->assertJsonPath('props.campaign_read_model.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.facts.context_status', 'no-campaign-selected')
        ->assertJsonPath('props.campaign_read_model.facts.selected', false)
        ->assertJsonPath('props.campaign_read_model.facts.metadata.package_available', true)
        ->assertJsonPath('props.campaign_read_model.redactions.payloads', 'campaign-cockpit-package-presence-only')
        ->assertJsonPath('props.campaign_read_model.redactions.read_only', true)
        ->assertJsonPath('props.campaign_read_model.redactions.mutates_campaigns', false)
        ->assertJsonPath('props.campaign_read_model.redactions.issues_pay_codes', false)
        ->assertJsonPath('props.campaign_read_model.redactions.sends_feedback', false)
        ->assertJsonPath('props.campaign_read_model.redactions.writes_journal', false)
        ->assertJsonPath('props.campaign_read_model.redactions.moves_money', false)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.enabled', false)
        ->assertJsonMissingPath('props.campaign_read_model.provider_payload')
        ->assertJsonMissingPath('props.campaign_read_model.raw_payload')
        ->assertJsonMissingPath('props.campaign_read_model.wallet')
        ->assertJsonMissingPath('props.campaign_read_model.campaign_mutation_endpoint')
        ->assertJsonMissingPath('props.campaign_read_model.pay_code_generation_payload')
        ->assertJsonMissingPath('props.campaign_read_model.delivery_dispatch_payload');
});

it('hydrates a selected local campaign fixture through the real x-campaign cockpit adapter', function () {
    config([
        'x-change.cockpit.local_campaign_fixture.enabled' => true,
        'x-change.cockpit.local_campaign_fixture.planning_key' => 'plan-local',
        'x-change.cockpit.local_campaign_fixture.execution_id' => 'exec-local',
        'x-change.cockpit.local_campaign_fixture.audience_id' => 'audience-local',
    ]);

    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => 'plan-local',
            'campaign_execution_id' => 'exec-local',
            'campaign_id' => 'campaign-local',
            'campaign_audience_id' => 'audience-local',
            'campaign_source' => 'campaign_cockpit',
            'campaign_template_key' => 'ofw-remittance',
            'campaign_amount' => '500.00',
            'campaign_currency' => 'PHP',
            'campaign_recipient_reference' => '09173011987',
            'campaign_purpose' => 'Campaign payout',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.status', 'available')
        ->assertJsonPath('props.campaign_read_model.authorized', true)
        ->assertJsonPath('props.campaign_read_model.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.facts.planning_key', 'plan-local')
        ->assertJsonPath('props.campaign_read_model.facts.execution_id', 'exec-local')
        ->assertJsonPath('props.campaign_read_model.facts.cards.campaign.name', 'Local Cockpit Campaign')
        ->assertJsonPath('props.campaign_read_model.facts.cards.campaign.status', 'draft')
        ->assertJsonPath('props.campaign_read_model.facts.metadata.read_only', true)
        ->assertJsonPath('props.campaign_read_model.facts.metadata.operator_id', '1')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.enabled', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.planning_key', 'plan-local')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.execution_id', 'exec-local')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.template_key', 'ofw-remittance')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.amount', '500.00')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.currency', 'PHP')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.recipient_reference', '09173011987')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.purpose', 'Campaign payout')
        ->assertJsonPath('props.campaign_read_model.recipient_quick_generate_links', [])
        ->assertJsonPath('props.campaign_read_model.redactions.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.redactions.read_only', true)
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
        ->assertJsonMissingPath('props.campaign_read_model.delivery_dispatch_payload');
});

it('carries the selected local campaign fixture link into quick generate prefill', function () {
    config([
        'x-change.cockpit.local_campaign_fixture.enabled' => true,
        'x-change.cockpit.local_campaign_fixture.planning_key' => 'plan-local',
        'x-change.cockpit.local_campaign_fixture.execution_id' => 'exec-local',
        'x-change.cockpit.local_campaign_fixture.audience_id' => 'audience-local',
    ]);

    actingAsTestUser();

    $dashboard = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => 'plan-local',
            'campaign_execution_id' => 'exec-local',
        ]))
        ->assertOk()
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.enabled', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.read_only', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.mutates_campaign', false);

    $quickGenerateHref = $dashboard->json('props.campaign_read_model.quick_generate_link.href');

    expect($quickGenerateHref)->toBeString()
        ->and($quickGenerateHref)->toContain('/x/cockpit/quick-generate')
        ->and($quickGenerateHref)->toContain('campaign_planning_key=plan-local')
        ->and($quickGenerateHref)->toContain('campaign_execution_id=exec-local');

    $quickGeneratePath = parse_url($quickGenerateHref, PHP_URL_PATH);
    $quickGenerateQuery = parse_url($quickGenerateHref, PHP_URL_QUERY);

    $this
        ->withHeader('X-Inertia', 'true')
        ->get($quickGeneratePath.'?'.$quickGenerateQuery)
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/QuickGenerate')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.status', 'available')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.read_only', true)
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.mutates_campaign', false)
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.planning_key', 'plan-local')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.execution_id', 'exec-local')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.source', 'campaign_cockpit')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.template_key', 'ofw-remittance')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.amount', '500.00')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.currency', 'PHP')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.recipient_reference', '09173011987')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.purpose', 'Campaign payout')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.campaign_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.recipient_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.provider_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.wallet')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.raw_payload');
});
