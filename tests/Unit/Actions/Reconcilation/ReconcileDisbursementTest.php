<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Reconciliation\ReconcileDisbursement;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;

it('reconciles a disbursement by id', function () {
    $record = new DisbursementReconciliationData(
        id: 1,
        voucher_id: 10,
        voucher_code: 'TEST-1234',
        claim_type: 'withdraw',
        provider: 'constellation',
        provider_reference: 'REF-001',
        provider_transaction_id: null,
        transaction_uuid: null,
        status: 'pending',
        internal_status: 'recorded',
        amount: 100.00,
        currency: 'PHP',
        bank_code: 'GXCHPHM2XXX',
        account_number_masked: '******4567',
        settlement_rail: 'INSTAPAY',
        attempt_count: 1,
    );

    $updated = [
        'id' => 1,
        'voucher_id' => 10,
        'voucher_code' => 'TEST-1234',
        'claim_type' => 'withdraw',
        'provider' => 'constellation',
        'provider_reference' => 'REF-001',
        'provider_transaction_id' => 'TX-001',
        'transaction_uuid' => 'UUID-001',
        'status' => 'succeeded',
        'internal_status' => 'matched',
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '******4567',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
    ];

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $store->shouldReceive('findById')
        ->once()
        ->with(1)
        ->andReturn($record);

    $service = Mockery::mock(DisbursementReconciliationContract::class);
    $service->shouldReceive('reconcile')
        ->once()
        ->with($record)
        ->andReturn($updated);

    $action = new ReconcileDisbursement($store, $service);

    $result = $action->handle(1);

    expect($result)->toBeInstanceOf(DisbursementReconciliationData::class);
    expect($result->status)->toBe('succeeded');
});
