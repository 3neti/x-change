<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Contracts\ExecutionResultJournalHandoffContract;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.lifecycle.defaults.system_user_email' => 'system@example.test',
        'x-change.lifecycle.defaults.test_user_email' => 'lester@hurtado.ph',
        'x-change.lifecycle.defaults.test_user_mobile' => '09173011987',
        'x-change.settlement.default_driver' => 'philhealth-bst',
        'x-change.settlement.drivers_path' => settlementEnvelopeDriversPath(),
        'x-change.execution_result_handoffs.journal' => 'x-journal',
        'queue.default' => 'sync',
    ]);

    app()->forgetInstance(ExecutionResultJournalHandoffContract::class);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('projects x-journal execution result records into cockpit dashboard activity', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $scenario = json_decode(Artisan::output(), true);
    $entry = ExecutionJournalEntry::query()->sole();

    actingAsTestUser();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.dashboard_read_model.status', 'available')
        ->assertJsonPath('props.dashboard_read_model.authorized', true);

    $activities = data_get($response->json(), 'props.dashboard_read_model.activity', []);
    $executionActivity = collect($activities)->firstWhere(
        'id',
        'execution-'.data_get($scenario, 'execution.execution_id'),
    );

    expect($exitCode)->toBe(0)
        ->and($entry->event_type)->toBe('execution.result.recorded')
        ->and($executionActivity)->toBeArray()
        ->and($executionActivity['source'])->toBe('execution')
        ->and($executionActivity['label'])->toBe('Execution recorded for '.data_get($scenario, 'voucher_code'))
        ->and($executionActivity['description'])->toContain('settlement_envelope succeeded')
        ->and($executionActivity['description'])->toContain(data_get($scenario, 'execution.execution_id'))
        ->and($executionActivity['timestamp'])->toBe($entry->occurred_at->toISOString());
});
