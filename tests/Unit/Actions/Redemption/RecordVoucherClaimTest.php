<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Models\VoucherClaim;

it('records a voucher claim row from a normalized claim result', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-RECORD-001',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 300,
                    'currency' => 'PHP',
                    'slice_mode' => 'open',
                    'max_slices' => 3,
                    'min_withdrawal' => 50,
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);

    $result = new SubmitPayCodeClaimResultData(
        voucher_code: $voucher->code,
        claim_type: 'withdraw',
        claimed: true,
        status: 'succeeded',
        requested_amount: 100,
        disbursed_amount: 100,
        currency: 'PHP',
        remaining_balance: 200,
        fully_claimed: false,
        disbursement: [],
        messages: ['Claim submitted successfully.'],
    );

    $claim = app(RecordVoucherClaim::class)->handle($voucher, $result, [
        'mobile' => '639171234567',
        'recipient_country' => 'PH',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
        '_meta' => [
            'idempotency_key' => 'claim-record-001',
        ],
        'reference' => 'REF-CLAIM-001',
    ]);

    expect($claim)->toBeInstanceOf(VoucherClaim::class);
    expect($claim->voucher_id)->toBe($voucher->id);
    expect($claim->claim_number)->toBe(1);
    expect($claim->claim_type)->toBe('withdraw');
    expect($claim->status)->toBe('succeeded');
    expect($claim->requested_amount_minor)->toBe(10000);
    expect($claim->disbursed_amount_minor)->toBe(10000);
    expect($claim->remaining_balance_minor)->toBe(20000);
    expect($claim->claimer_mobile)->toBe('639171234567');
    expect($claim->bank_code)->toBe('GXCHPHM2XXX');
    expect($claim->account_number_masked)->toEndWith('1987');
    expect($claim->idempotency_key)->toBe('claim-record-001');
    expect($claim->reference)->toBe('REF-CLAIM-001');
    expect($claim->attempted_at)->not->toBeNull();
    expect($claim->completed_at)->not->toBeNull();
});

it('increments claim number for subsequent claims on the same voucher', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-RECORD-002',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 300,
                    'currency' => 'PHP',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);

    $result = new SubmitPayCodeClaimResultData(
        voucher_code: $voucher->code,
        claim_type: 'claim',
        claimed: true,
        status: 'succeeded',
        requested_amount: 300,
        disbursed_amount: 300,
        currency: 'PHP',
        remaining_balance: 0,
        fully_claimed: true,
        disbursement: [],
        messages: ['OK'],
    );

    app(RecordVoucherClaim::class)->handle($voucher, $result, []);
    $second = app(RecordVoucherClaim::class)->handle($voucher, $result, []);

    expect($second->claim_number)->toBe(2);
});
