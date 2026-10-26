<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Contracts\EventStoreContract;
use LBHurtado\XChange\Models\StoredValueAuditEvent;
use LBHurtado\XChange\Models\StoredValueCollection;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\StoredValue\StoredValueAuditJournal;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('retains an activation audit event when delivery fails and records it exactly once on retry', function (): void {
    $voucher = Voucher::query()->create(['code' => 'AU01', 'state' => 'active']);
    $binding = StoredValueHolderBinding::query()->create([
        'voucher_id' => $voucher->getKey(),
        'allocation_reference' => 'audit-allocation',
        'reservation_operation_reference' => 'audit-reservation',
        'activation_operation_reference' => 'audit-activation',
        'holder_type' => Contact::class,
        'holder_id' => '1',
        'holder_principal_reference' => 'audit-principal',
        'holder_authority_reference' => 'audit-authority',
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
    $this->artisan('x-change:stored-value:reconcile-audit')->assertExitCode(1);
    $this->artisan('x-change:stored-value:backfill-audit --commit')->assertExitCode(1);
    expect(StoredValueAuditEvent::query()->count())->toBe(0);

    $writer = Mockery::mock(AppendableEventStoreContract::class);
    $writer->shouldReceive('append')->once()->andThrow(new RuntimeException('Journal unavailable'));
    $journal = new StoredValueAuditJournal($writer, app(EventStoreContract::class));
    $journal->captureActivation($binding, 10_000);
    $journal->deliverForBinding($binding->reference);

    expect(StoredValueAuditEvent::query()->sole()->journal_status)->toBe('pending')
        ->and(StoredValueAuditEvent::query()->sole()->journal_attempts)->toBe(1)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'stored_value.activated')->count())->toBe(0);
    $this->artisan('x-change:stored-value:reconcile-audit')->assertExitCode(1);

    $retryJournal = app(StoredValueAuditJournal::class);
    expect($retryJournal->deliverPending())->toBe(1);
    $retryJournal->deliverForBinding($binding->reference);

    expect(StoredValueAuditEvent::query()->sole()->journal_status)->toBe('delivered')
        ->and(StoredValueAuditEvent::query()->sole()->journal_attempts)->toBe(2)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'stored_value.activated')->count())->toBe(1);
    $this->artisan('x-change:stored-value:reconcile-audit')->assertExitCode(0);
});

it('preserves collection authorization and destination evidence in the journal exactly once', function (): void {
    $voucher = Voucher::query()->create(['code' => 'AU02', 'state' => 'active']);
    $binding = StoredValueHolderBinding::query()->create([
        'voucher_id' => $voucher->getKey(),
        'allocation_reference' => 'collection-audit-allocation',
        'reservation_operation_reference' => 'collection-audit-reservation',
        'activation_operation_reference' => 'collection-audit-activation',
        'holder_type' => Contact::class,
        'holder_id' => '1',
        'holder_principal_reference' => 'collection-audit-principal',
        'holder_authority_reference' => 'collection-audit-authority',
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
    $collection = StoredValueCollection::query()->create([
        'stored_value_holder_binding_id' => $binding->getKey(),
        'collector_channel' => 'cockpit',
        'collector_type' => 'user',
        'collector_id' => '42',
        'idempotency_key_hash' => hash('sha256', 'collection-audit-key'),
        'request_hash' => hash('sha256', 'collection-audit-request'),
        'amount_minor' => 2_500,
        'currency' => 'PHP',
        'status' => 'committed',
        'holder_mobile_hash' => hash('sha256', '09173011987'),
        'expires_at' => now()->addMinutes(10),
        'authorization_method' => 'collector',
        'committed_at' => now(),
        'balance_after_minor' => 7_500,
        'receipt' => ['destination_principal_reference' => 'client-funds:collector-42'],
    ]);
    $journal = app(StoredValueAuditJournal::class);

    $event = $journal->captureCollection($collection);
    $journal->deliverForCollection($collection->reference);
    $journal->deliverForCollection($collection->reference);

    $entry = ExecutionJournalEntry::query()->where('source_event_id', $event->event_id)->sole();
    expect($event->fresh()->journal_status)->toBe('delivered')
        ->and(data_get($entry->payload, 'authorization_method'))->toBe('collector')
        ->and(data_get($entry->payload, 'destination_principal_reference'))->toBe('client-funds:collector-42')
        ->and(data_get($entry->payload, 'amount_minor'))->toBe(2_500)
        ->and(data_get($entry->payload, 'balance_after_minor'))->toBe(7_500)
        ->and(ExecutionJournalEntry::query()->where('source_event_id', $event->event_id)->count())->toBe(1);
});
