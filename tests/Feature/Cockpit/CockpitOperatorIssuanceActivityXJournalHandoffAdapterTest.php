<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Services\Cockpit\XJournalCockpitOperatorIssuanceActivityJournalHandoff;
use LBHurtado\XJournal\Contracts\JournalSinkContract;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('records durable activity into x journal with idempotent replay semantics', function () {
    $handoff = app(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class);
    $activity = cockpitXJournalHandoffActivity();

    $first = $handoff->handoff($activity);
    $second = $handoff->handoff($activity);

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($first->status)->toBe('recorded')
        ->and($first->writes_journal)->toBeTrue()
        ->and($first->journal_entry_id)->not->toBeNull()
        ->and($second->status)->toBe('recorded')
        ->and($second->writes_journal)->toBeTrue()
        ->and($second->journal_entry_id)->toBe($first->journal_entry_id)
        ->and($first->metadata['idempotency_key'])->toBe(hash('sha256', 'cockpit.operator_issuance_activity|activity-xjournal-1|corr-xjournal-1|PC-XJOURNAL-1|operator-1'));

    $entry = ExecutionJournalEntry::query()->firstOrFail();

    expect($entry->event_type)->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($entry->idempotency_key)->toBe($first->metadata['idempotency_key'])
        ->and($entry->actor_type)->toBe('operator')
        ->and($entry->actor_id)->toBe('operator-1')
        ->and($entry->subject_type)->toBe('pay_code')
        ->and($entry->subject_id)->toBe('PC-XJOURNAL-1')
        ->and($entry->correlation_id)->toBe('corr-xjournal-1')
        ->and($entry->causation_id)->toBe('activity-xjournal-1')
        ->and($entry->payload)->toMatchArray([
            'activity_id' => 'activity-xjournal-1',
            'code' => 'PC-XJOURNAL-1',
            'amount' => '25',
            'currency' => 'PHP',
            'status' => 'issued',
        ])
        ->and($entry->metadata)->toHaveKey('redactions')
        ->and($entry->metadata)->not->toHaveKeys([
            'raw_payload',
            'provider_payload',
            'wallet',
            'recipient_secret',
            'otp',
            'funding_source',
        ]);
});

it('returns a non blocking failure result when x journal recording fails', function () {
    app()->instance(JournalSinkContract::class, new class implements JournalSinkContract
    {
        public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
        {
            throw new RuntimeException('Simulated journal sink failure.');
        }
    });

    app()->forgetInstance(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class);

    $result = app(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class)
        ->handoff(cockpitXJournalHandoffActivity());

    expect($result->status)->toBe('failed_non_blocking')
        ->and($result->writes_journal)->toBeFalse()
        ->and($result->activity_id)->toBe('activity-xjournal-1')
        ->and($result->correlation_id)->toBe('corr-xjournal-1')
        ->and($result->journal_entry_id)->toBeNull()
        ->and($result->reason)->toBe('x-journal handoff failed without blocking the Cockpit activity flow.')
        ->and($result->metadata['exception'])->toBe(RuntimeException::class)
        ->and($result->metadata['idempotency_key'])->toBe(hash('sha256', 'cockpit.operator_issuance_activity|activity-xjournal-1|corr-xjournal-1|PC-XJOURNAL-1|operator-1'));

    expect(ExecutionJournalEntry::query()->count())->toBe(0);
});

function cockpitXJournalHandoffActivity(): CockpitOperatorIssuanceActivityItemData
{
    return new CockpitOperatorIssuanceActivityItemData(
        id: 'activity-xjournal-1',
        code: 'PC-XJOURNAL-1',
        amount: '25',
        currency: 'PHP',
        status: 'issued',
        issued_at: '2026-07-10T09:00:00+08:00',
        route: 'x-change.cockpit.quick-generate.store',
        correlation_id: 'corr-xjournal-1',
        idempotency_key: 'raw-idempotency-key',
        operator_id: 'operator-1',
        detail_href: '/x/cockpit/pay-codes/PC-XJOURNAL-1',
        metadata: [
            'source' => 'x-change.cockpit',
            'safe_note' => 'allowed',
            'raw_payload' => ['secret' => 'value'],
            'provider_payload' => ['authorization' => 'Bearer token'],
            'wallet' => ['balance' => 100],
            'recipient_secret' => 'secret',
            'otp' => '123456',
            'funding_source' => 'wallet',
        ],
    );
}
