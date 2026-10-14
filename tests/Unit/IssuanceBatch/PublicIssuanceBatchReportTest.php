<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use LBHurtado\XChange\Http\Controllers\Web\PublicIssuance\PublicIssuanceBatchController;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

it('normalizes redemption facts for the batch workspace', function (): void {
    $claim = new VoucherClaim([
        'status' => 'succeeded',
        'requested_amount_minor' => 2500,
        'disbursed_amount_minor' => 2500,
        'currency' => 'PHP',
        'claimer_mobile' => '09175180722',
        'settlement_mode' => 'withdraw',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '••••0722',
        'completed_at' => '2026-10-14 12:00:00',
        'meta' => ['inputs' => ['name' => 'Apple Hurtado']],
    ]);
    $reconciliation = new DisbursementReconciliation([
        'amount' => 25.0,
        'currency' => 'PHP',
        'settlement_rail' => 'INSTAPAY',
        'provider' => 'netbank',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '••••0722',
        'status' => 'succeeded',
        'provider_transaction_id' => 'provider-123',
        'completed_at' => '2026-10-14 12:00:02',
    ]);

    $method = new ReflectionMethod(PublicIssuanceBatchController::class, 'batchItemLifecycle');
    $method->setAccessible(true);
    $result = $method->invoke(new PublicIssuanceBatchController, null, $claim, $reconciliation, 'issued');

    expect($result['status'])->toBe('paid')
        ->and($result['claim_summary'])->toMatchArray([
            'claimer_name' => 'Apple Hurtado',
            'claimer_mobile_masked' => '•••••••0722',
            'settlement_rail' => 'INSTAPAY',
            'provider' => 'netbank',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number_masked' => '••••0722',
            'provider_transaction_id' => 'provider-123',
            'payout_status' => 'succeeded',
            'amount_minor' => 2500,
        ]);
});
