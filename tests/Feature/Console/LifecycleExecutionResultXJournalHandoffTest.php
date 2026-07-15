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

it('records execution result handoff into x-journal during lifecycle scenario execution', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);
    $entry = ExecutionJournalEntry::query()->sole();

    expect($exitCode)->toBe(0)
        ->and(data_get($json, 'execution.handoffs.blocks_execution'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.journal.status'))->toBe('recorded')
        ->and(data_get($json, 'execution.handoffs.journal.performed_side_effect'))->toBeTrue()
        ->and(data_get($json, 'execution.handoffs.journal.metadata.journal_entry_id'))->toBe((string) $entry->getKey())
        ->and(data_get($json, 'execution.handoffs.journal.metadata.event_type'))->toBe('execution.result.recorded')
        ->and($entry->event_type)->toBe('execution.result.recorded')
        ->and($entry->subject_id)->toBe(data_get($json, 'voucher_code'))
        ->and($entry->payload['execution_id'])->toBe(data_get($json, 'execution.execution_id'))
        ->and($entry->payload['driver'])->toBe('settlement_envelope')
        ->and($entry->payload['status'])->toBe('succeeded')
        ->and($entry->metadata['source'])->toBe('x-change.execution')
        ->and($entry->metadata['redactions']['raw_provider_payloads_exposed'])->toBeFalse();
});
