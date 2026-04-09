<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationService;

it('reconciles a pending record to succeeded', function () {
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

    $fetcher = Mockery::mock(DisbursementStatusFetcherContract::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with($record)
        ->andReturn([
            'status' => 'completed',
            'transaction_id' => 'TX-001',
            'uuid' => 'UUID-001',
        ]);

    $resolver = Mockery::mock(DisbursementStatusResolverContract::class);
    $resolver->shouldReceive('resolveFromGatewayResponse')
        ->once()
        ->andReturn('succeeded');

    $store = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $store->shouldReceive('record')
        ->once()
        ->with(Mockery::on(function (array $payload) {
            expect($payload['status'])->toBe('succeeded');
            expect($payload['internal_status'])->toBe('matched');
            expect($payload['provider_transaction_id'])->toBe('TX-001');

            return true;
        }))
        ->andReturn(new DisbursementReconciliationData(
            id: 1,
            voucher_id: 10,
            voucher_code: 'TEST-1234',
            claim_type: 'withdraw',
            provider: 'constellation',
            provider_reference: 'REF-001',
            provider_transaction_id: 'TX-001',
            transaction_uuid: 'UUID-001',
            status: 'succeeded',
            internal_status: 'matched',
            amount: 100.00,
            currency: 'PHP',
            bank_code: 'GXCHPHM2XXX',
            account_number_masked: '******4567',
            settlement_rail: 'INSTAPAY',
            attempt_count: 1,
        ));

    $service = new DefaultDisbursementReconciliationService($store, $fetcher, $resolver);

    $result = $service->reconcile($record);

    expect($result->status)->toBe('succeeded');
    expect($result->internal_status)->toBe('matched');
});
