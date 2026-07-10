<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;

it('requires the explicit local only flag before writing the diagnostic fixture', function () {
    $this->artisan('x-change:cockpit:seed-diagnostic-activity')
        ->expectsOutputToContain('--local-only')
        ->assertFailed();

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(0);
});

it('refuses to seed the diagnostic fixture in production', function () {
    config()->set('app.env', 'production');

    $this->artisan('x-change:cockpit:seed-diagnostic-activity --local-only')
        ->expectsOutputToContain('Refusing to seed Cockpit diagnostic activity in production.')
        ->assertFailed();

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(0);
});

it('seeds one safe local durable diagnostic activity record', function () {
    $this->artisan('x-change:cockpit:seed-diagnostic-activity --local-only --json')
        ->expectsOutputToContain('"activity_id":"fixture-cockpit-journal-diagnostic-activity"')
        ->assertSuccessful();

    $activity = CockpitOperatorIssuanceActivity::query()
        ->where('activity_id', 'fixture-cockpit-journal-diagnostic-activity')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->source)->toBe('cockpit.local-diagnostic-fixture')
        ->and($activity?->subject_reference)->toBe('PC-LOCAL-DIAGNOSTIC')
        ->and($activity?->journal_handoff_status)->toBe('recorded')
        ->and($activity?->action_handoff_status)->toBe('not_wired')
        ->and($activity?->feedback_handoff_status)->toBe('not_wired')
        ->and($activity?->safe_context)->toMatchArray([
            'amount' => '25.00',
            'currency' => 'PHP',
            'route' => 'x-change.cockpit.local-diagnostic-fixture',
            'detail_href' => '/x/cockpit/pay-codes/PC-LOCAL-DIAGNOSTIC',
        ])
        ->and($activity?->metadata)->toHaveKey('journal_handoff')
        ->and(data_get($activity?->metadata, 'journal_handoff.metadata.reference_number'))->toBe('ERN-LOCAL-COCKPIT-0001')
        ->and(data_get($activity?->metadata, 'journal_handoff.metadata.event_type'))->toBe('cockpit.operator_issuance_activity.fixture')
        ->and($activity?->metadata)->not->toHaveKey('raw_payload')
        ->and($activity?->metadata)->not->toHaveKey('provider_payload')
        ->and($activity?->metadata)->not->toHaveKey('wallet')
        ->and($activity?->metadata)->not->toHaveKey('token')
        ->and($activity?->redaction_flags)->toMatchArray([
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
        ]);
});

it('hydrates the seeded diagnostic fixture through the cockpit read model when database activity is configured', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $this->artisan('x-change:cockpit:seed-diagnostic-activity --local-only')
        ->assertSuccessful();

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forOperatorIssuanceActivity(new CockpitReadModelQueryData(
            operatorId: 'local-fixture-operator',
            correlationId: 'corr-local-cockpit-diagnostic',
        ));

    expect($readModel->status)->toBe('available')
        ->and($readModel->items)->toHaveCount(1)
        ->and($readModel->presentations)->toHaveCount(1)
        ->and($readModel->items[0]->code)->toBe('PC-LOCAL-DIAGNOSTIC')
        ->and($readModel->presentations[0]->metadata['journal_handoff'])->toMatchArray([
            'status' => 'recorded',
            'journal_entry_id' => 'journal-entry-local-fixture',
            'writes_journal' => true,
            'source' => 'local_fixture',
            'reason' => 'Synthetic local fixture for Cockpit diagnostic visual verification.',
            'metadata' => [
                'reference_number' => 'ERN-LOCAL-COCKPIT-0001',
                'event_type' => 'cockpit.operator_issuance_activity.fixture',
                'idempotency_key' => 'fixture-redacted-idempotency-key',
            ],
            'diagnostic' => [
                'classification' => 'recorded',
                'tone' => 'success',
                'label' => 'Journal recorded',
                'description' => 'The durable activity was handed to the journal and a journal entry identifier is available for read-only inspection.',
                'operator_action' => 'none',
                'read_only' => true,
                'retry_enabled' => false,
                'mutation_enabled' => false,
                'raw_payloads_exposed' => false,
            ],
        ])
        ->and($readModel->presentations[0]->metadata['raw_payload'] ?? null)->toBeNull()
        ->and($readModel->presentations[0]->metadata['provider_payload'] ?? null)->toBeNull()
        ->and($readModel->presentations[0]->metadata['wallet'] ?? null)->toBeNull();
});
