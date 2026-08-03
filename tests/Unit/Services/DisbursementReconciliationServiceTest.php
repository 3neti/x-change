<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationService;

it('reconciles a pending record to succeeded', function () {
    Event::fake();

    $record = DisbursementReconciliation::query()->create([
        'voucher_id' => 10,
        'voucher_code' => 'TEST-1234',
        'claim_type' => 'withdraw',
        'provider' => 'constellation',
        'provider_reference' => 'REF-001',
        'provider_transaction_id' => null,
        'transaction_uuid' => null,
        'status' => 'pending',
        'internal_status' => 'recorded',
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '******4567',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'needs_review' => false,
        'review_reason' => null,
        'error_message' => null,
        'raw_request' => null,
        'raw_response' => null,
        'meta' => null,
    ]);

    $fetcher = Mockery::mock(DisbursementStatusFetcherContract::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with(Mockery::on(function ($data) use ($record) {
            return $data->id === $record->id
                && $data->voucher_code === 'TEST-1234'
                && $data->status === 'pending';
        }))
        ->andReturn([
            'status' => 'completed',
            'transaction_id' => 'TX-001',
            'uuid' => 'UUID-001',
        ]);

    $resolver = Mockery::mock(DisbursementStatusResolverContract::class);
    $resolver->shouldReceive('resolveFromFetchedStatus')
        ->once()
        ->with('completed', [])
        ->andReturn('succeeded');

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);

    $service = new DefaultDisbursementReconciliationService($store, $fetcher, $resolver);

    $result = $service->reconcile($record);

    expect($result)->toBeArray();
    expect($result['resolved_status'])->toBe('succeeded');
    expect($result['before_status'])->toBe('pending');
    expect($result['updated'])->toBeTrue();
    expect($result['reconciliation_id'])->toBe($record->id);

    $record->refresh();

    expect($record->status)->toBe('succeeded');
    expect($record->needs_review)->toBeFalse();
    expect($record->review_reason)->toBeNull();
    expect($record->error_message)->toBeNull();
    expect($record->completed_at)->not->toBeNull();

    Event::assertDispatched(DisbursementConfirmed::class);
});

it('reconciles a trusted provider failure without leaving stale review flags', function () {
    Event::fake();

    $record = DisbursementReconciliation::query()->create([
        'voucher_id' => 11,
        'voucher_code' => 'TEST-FAIL',
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => 'TEST-FAIL-09173011987',
        'provider_transaction_id' => '407906626',
        'transaction_uuid' => null,
        'status' => 'pending',
        'internal_status' => 'recorded',
        'amount' => 2.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '******1987',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'needs_review' => true,
        'review_reason' => 'Low-confidence failed status from provider',
        'error_message' => 'Provider returned an untrusted failed status with incomplete metadata.',
        'raw_request' => null,
        'raw_response' => null,
        'meta' => null,
    ]);

    $metadata = [
        'transaction_id' => '407906626',
        'operation_id' => '407906626',
        'status' => 'Rejected',
        'settlement_rail' => 'INSTAPAY',
        'rejection_reason' => 'AC06 (Blocked account)',
        'status_details' => [
            ['status' => 'Pending', 'updated' => '2026-07-30T03:19:02Z'],
            ['status' => 'Rejected', 'message' => 'AC06 (Blocked account)', 'updated' => '2026-07-30T03:19:03Z'],
        ],
    ];

    $fetcher = Mockery::mock(DisbursementStatusFetcherContract::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->andReturn([
            'status' => 'failed',
            'metadata' => $metadata,
        ]);

    $resolver = Mockery::mock(DisbursementStatusResolverContract::class);
    $resolver->shouldReceive('resolveFromFetchedStatus')
        ->once()
        ->with('failed', $metadata)
        ->andReturn('failed');

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);

    $service = new DefaultDisbursementReconciliationService($store, $fetcher, $resolver);

    $result = $service->reconcile($record);

    expect($result['resolved_status'])->toBe('failed')
        ->and($result['needs_review'])->toBeFalse()
        ->and($result['trusted_failure'])->toBeTrue();

    $record->refresh();

    expect($record->status)->toBe('failed')
        ->and($record->needs_review)->toBeFalse()
        ->and($record->review_reason)->toBeNull()
        ->and($record->error_message)->toBe('AC06 (Blocked account)')
        ->and($record->raw_response)->toBe($metadata)
        ->and($record->next_retry_at)->toBeNull();

    Event::assertNotDispatched(DisbursementConfirmed::class);
});

it('redispatches confirmation for a succeeded reconciliation whose internal posting is incomplete', function () {
    Event::fake();

    $record = DisbursementReconciliation::query()->create([
        'voucher_id' => 12,
        'voucher_code' => 'TEST-INCOMPLETE',
        'claim_type' => 'withdraw',
        'provider' => 'netbank',
        'provider_reference' => 'TEST-INCOMPLETE-09173011987',
        'provider_transaction_id' => '409729887',
        'status' => 'succeeded',
        'internal_status' => 'recorded',
        'amount' => 25,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'needs_review' => false,
        'completed_at' => now(),
    ]);
    $fetcher = Mockery::mock(DisbursementStatusFetcherContract::class);
    $fetcher->shouldReceive('fetch')->once()->andReturn([
        'status' => 'completed',
        'metadata' => [
            'operation_id' => '409729887',
            'status' => 'Completed',
        ],
    ]);
    $resolver = Mockery::mock(DisbursementStatusResolverContract::class);
    $resolver->shouldReceive('resolveFromFetchedStatus')
        ->once()
        ->andReturn('succeeded');
    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);

    $result = (new DefaultDisbursementReconciliationService(
        $store,
        $fetcher,
        $resolver,
    ))->reconcile($record);

    expect($result['resolved_status'])->toBe('succeeded')
        ->and($result['updated'])->toBeFalse();
    Event::assertDispatched(DisbursementConfirmed::class);
});
