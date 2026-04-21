<?php

declare(strict_types=1);

use Carbon\Carbon;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Services\ReconciliationLifecycleService;

it('lists reconciliations as lifecycle summaries', function () {
    $record = DisbursementReconciliationData::from([
        'id' => 1,
        'voucher_code' => 'TEST-1234',
        'provider_reference' => 'PR-001',
        'status' => 'pending',
        'amount' => 100.00,
        'currency' => 'PHP',
        'review_reason' => null,
        'completed_at' => null,
        'raw_response' => [
            'status' => 'failed',
        ],
    ]);

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $store->shouldReceive('getPending')
        ->once()
        ->with(50)
        ->andReturn([$record]);

    $reconciler = Mockery::mock(DisbursementReconciliationContract::class);

    $service = new ReconciliationLifecycleService($store, $reconciler);

    $result = $service->list([]);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe('1')
        ->and($result[0]['reference'])->toBe('PR-001')
        ->and($result[0]['status'])->toBe('pending')
        ->and($result[0]['provider_status'])->toBe('failed')
        ->and($result[0]['amount'])->toBe(100.0)
        ->and($result[0]['currency'])->toBe('PHP');
});

it('shows a reconciliation as lifecycle detail', function () {
    $record = DisbursementReconciliationData::from([
        'id' => 2,
        'voucher_code' => 'TEST-5678',
        'provider_reference' => 'PR-002',
        'status' => 'pending_review',
        'amount' => 250.00,
        'currency' => 'PHP',
        'review_reason' => 'Provider mismatch',
        'completed_at' => null,
        'raw_response' => [
            'status' => 'failed',
        ],
    ]);

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $store->shouldReceive('findById')
        ->once()
        ->with(2)
        ->andReturn($record);

    $reconciler = Mockery::mock(DisbursementReconciliationContract::class);

    $service = new ReconciliationLifecycleService($store, $reconciler);

    $result = $service->show('2');

    expect($result)->toBeArray()
        ->and($result['id'])->toBe('2')
        ->and($result['reference'])->toBe('PR-002')
        ->and($result['status'])->toBe('pending_review')
        ->and($result['provider_status'])->toBe('failed')
        ->and($result['amount'])->toBe(250.0)
        ->and($result['currency'])->toBe('PHP')
        ->and($result['reason'])->toBe('Provider mismatch')
        ->and($result['resolved'])->toBeFalse()
        ->and($result['resolved_at'])->toBeNull();
});

it('resolves a reconciliation through the reconciliation service', function () {
    $record = DisbursementReconciliationData::from([
        'id' => 3,
        'voucher_code' => 'TEST-9999',
        'provider_reference' => 'PR-003',
        'status' => 'pending',
        'amount' => 300.00,
        'currency' => 'PHP',
        'review_reason' => null,
        'completed_at' => null,
        'raw_response' => [],
    ]);

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $store->shouldReceive('findById')
        ->once()
        ->with(3)
        ->andReturn($record);

    $reconciler = Mockery::mock(DisbursementReconciliationContract::class);
    $reconciler->shouldReceive('reconcile')
        ->once()
        ->with(Mockery::type(DisbursementReconciliationData::class))
        ->andReturn([
            'updated' => true,
            'before_status' => 'pending',
            'fetched_status' => 'success',
            'resolved_status' => 'succeeded',
            'reconciliation_id' => 3,
            'raw' => ['status' => 'success'],
            'needs_review' => false,
            'review_reason' => null,
            'trusted_failure' => true,
        ]);

    $service = new ReconciliationLifecycleService($store, $reconciler);

    $result = $service->resolve('3', [
        'resolution' => 'manual_clear',
        'notes' => 'Reviewed and cleared.',
    ]);

    expect($result)->toBeArray()
        ->and($result['reconciliation_id'])->toBe('3')
        ->and($result['status'])->toBe('succeeded')
        ->and($result['resolution'])->toBe('manual_clear')
        ->and($result['resolved'])->toBeTrue()
        ->and($result['notes'])->toBe('Reviewed and cleared.')
        ->and($result['messages'][0])->toContain('Reconciliation 3 processed from pending to succeeded.');
});

it('uses voucher code as reference when provider reference is missing', function () {
    $record = DisbursementReconciliationData::from([
        'id' => 4,
        'voucher_code' => 'TEST-ABCD',
        'provider_reference' => null,
        'status' => 'pending',
        'amount' => 50.00,
        'currency' => 'PHP',
        'review_reason' => null,
        'completed_at' => null,
        'raw_response' => [],
    ]);

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $store->shouldReceive('findById')
        ->once()
        ->with(4)
        ->andReturn($record);

    $reconciler = Mockery::mock(DisbursementReconciliationContract::class);

    $service = new ReconciliationLifecycleService($store, $reconciler);

    $result = $service->show('4');

    expect($result['reference'])->toBe('TEST-ABCD');
});

it('marks reconciliation as resolved when completed_at exists and status is terminal', function () {
    $record = DisbursementReconciliationData::from([
        'id' => 5,
        'voucher_code' => 'TEST-TERM',
        'provider_reference' => 'PR-005',
        'status' => 'succeeded',
        'amount' => 400.00,
        'currency' => 'PHP',
        'review_reason' => null,
        'completed_at' => Carbon::parse('2026-04-22 10:00:00')->toIso8601String(),
        'raw_response' => [
            'status' => 'success',
        ],
    ]);

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $store->shouldReceive('findById')
        ->once()
        ->with(5)
        ->andReturn($record);

    $reconciler = Mockery::mock(DisbursementReconciliationContract::class);

    $service = new ReconciliationLifecycleService($store, $reconciler);

    $result = $service->show('5');

    expect($result)->toBeArray()
        ->and($result['status'])->toBe('succeeded')
        ->and($result['resolved'])->toBeTrue()
        ->and($result['resolved_at'])->not->toBeNull();
});
