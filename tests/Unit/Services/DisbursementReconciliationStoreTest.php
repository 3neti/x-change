<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore;

it('records a disbursement reconciliation row', function () {
    $store = new DefaultDisbursementReconciliationStore;

    $record = $store->record([
        'voucher_id' => 1,
        'voucher_code' => 'TEST-1234',
        'claim_type' => 'withdraw',
        'provider' => 'constellation',
        'provider_reference' => 'TEST-1234-09171234567-S1',
        'provider_transaction_id' => 'TX-001',
        'transaction_uuid' => 'UUID-001',
        'status' => 'pending',
        'internal_status' => 'recorded',
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******4567',
        'settlement_rail' => 'INSTAPAY',
        'attempt_count' => 1,
        'raw_request' => ['reference' => 'TEST-1234-09171234567-S1'],
        'raw_response' => ['status' => 'pending'],
        'meta' => ['flow' => 'withdraw'],
    ]);

    expect($record->id)->not->toBeNull();
    expect($record->voucher_code)->toBe('TEST-1234');
    expect($record->status)->toBe('pending');
});

it('updates existing reconciliation row for same voucher reference and claim', function () {
    $store = new DefaultDisbursementReconciliationStore;

    $first = $store->record([
        'voucher_code' => 'TEST-1234',
        'claim_type' => 'withdraw',
        'provider_reference' => 'REF-001',
        'status' => 'pending',
    ]);

    $second = $store->record([
        'voucher_code' => 'TEST-1234',
        'claim_type' => 'withdraw',
        'provider_reference' => 'REF-001',
        'status' => 'succeeded',
        'provider_transaction_id' => 'TX-002',
    ]);

    expect($second->id)->toBe($first->id);
    expect($second->status)->toBe('succeeded');
    expect($second->provider_transaction_id)->toBe('TX-002');
});
