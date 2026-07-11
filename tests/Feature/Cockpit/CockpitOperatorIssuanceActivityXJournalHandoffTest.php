<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Services\Cockpit\XJournalCockpitOperatorIssuanceActivityJournalHandoff;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('records cockpit operator issuance activity through the real x-journal recorder', function () {
    $result = app(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class)
        ->handoff(cockpitXJournalActivity());

    $entry = ExecutionJournalEntry::query()->sole();

    expect($result->status)->toBe('recorded')
        ->and($result->activity_id)->toBe('activity-xjournal-1')
        ->and($result->correlation_id)->toBe('corr-xjournal-1')
        ->and($result->writes_journal)->toBeTrue()
        ->and($result->journal_entry_id)->toBe((string) $entry->getKey())
        ->and($result->metadata['reference_number'])->toBe($entry->reference_number)
        ->and($result->metadata['event_type'])->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($entry->event_type)->toBe('cockpit.operator_issuance_activity.recorded')
        ->and($entry->actor_type)->toBe('operator')
        ->and($entry->actor_id)->toBe('operator-xjournal-1')
        ->and($entry->subject_type)->toBe('pay_code')
        ->and($entry->subject_id)->toBe('PC-XJOURNAL-1')
        ->and($entry->correlation_id)->toBe('corr-xjournal-1')
        ->and($entry->causation_id)->toBe('activity-xjournal-1')
        ->and($entry->payload['code'])->toBe('PC-XJOURNAL-1')
        ->and($entry->payload['amount'])->toBe('25.00')
        ->and($entry->payload['currency'])->toBe('PHP')
        ->and($entry->metadata['schema'])->toBe('x-change.cockpit.operator-issuance-activity-journal-payload.v1')
        ->and($entry->metadata['domain'])->toBe('cockpit')
        ->and($entry->metadata['source'])->toBe('x-change.cockpit');
});

it('returns the existing x-journal entry for idempotent duplicate activity handoff', function () {
    $handoff = app(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class);

    $first = $handoff->handoff(cockpitXJournalActivity());
    $second = $handoff->handoff(cockpitXJournalActivity());

    expect(ExecutionJournalEntry::query()->count())->toBe(1)
        ->and($first->status)->toBe('recorded')
        ->and($second->status)->toBe('recorded')
        ->and($second->journal_entry_id)->toBe($first->journal_entry_id)
        ->and($second->metadata['reference_number'])->toBe($first->metadata['reference_number']);
});

it('does not expose sensitive activity metadata in the x-journal entry', function () {
    app(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class)
        ->handoff(cockpitXJournalActivity([
            'raw_payload' => ['secret' => 'hidden'],
            'provider_payload' => ['token' => 'hidden'],
            'wallet' => ['balance' => 1000],
            'recipient_secret' => 'hidden',
            'otp' => '123456',
            'funding_source' => 'hidden',
            'safe_note' => 'visible',
        ]));

    $entry = ExecutionJournalEntry::query()->sole();

    expect($entry->metadata)->not->toHaveKeys([
        'raw_payload',
        'provider_payload',
        'wallet',
        'recipient_secret',
        'otp',
        'funding_source',
    ])->and($entry->metadata['safe_note'])->toBe('visible')
        ->and($entry->metadata['redactions'])->toMatchArray([
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
            'otp_exposed' => false,
            'funding_source_exposed' => false,
        ]);
});

/**
 * @param  array<string, mixed>  $metadata
 */
function cockpitXJournalActivity(array $metadata = []): CockpitOperatorIssuanceActivityItemData
{
    return new CockpitOperatorIssuanceActivityItemData(
        id: 'activity-xjournal-1',
        code: 'PC-XJOURNAL-1',
        amount: '25.00',
        currency: 'PHP',
        status: 'issued',
        issued_at: '2026-07-11T10:00:00+08:00',
        route: 'x-change.cockpit.quick-generate.store',
        correlation_id: 'corr-xjournal-1',
        idempotency_key: 'idem-xjournal-1',
        operator_id: 'operator-xjournal-1',
        detail_href: '/x/cockpit/pay-codes/PC-XJOURNAL-1',
        metadata: $metadata,
    );
}
