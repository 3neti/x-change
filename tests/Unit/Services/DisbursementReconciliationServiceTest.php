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
