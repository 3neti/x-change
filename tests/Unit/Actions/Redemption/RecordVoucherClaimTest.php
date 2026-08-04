<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;

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
        'inputs' => [
            'name' => 'Juan Dela Cruz',
            'location' => [
                'latitude' => 14.5995,
                'longitude' => 121.0288,
                'formatted_address' => 'Makati City',
                'map' => 'data:image/png;base64,map',
            ],
        ],
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
    expect($voucher->inputs()->where('name', 'name')->value('value'))->toBe('Juan Dela Cruz');
    expect(json_decode(
        (string) $voucher->inputs()->where('name', 'location')->value('value'),
        true,
        flags: JSON_THROW_ON_ERROR,
    ))->toMatchArray([
        'latitude' => 14.5995,
        'longitude' => 121.0288,
        'formatted_address' => 'Makati City',
    ]);
    expect(data_get($claim->fresh()->meta, 'evidence.persisted'))->toBeTrue()
        ->and(data_get($claim->fresh()->meta, 'evidence.input_ids'))->toHaveCount(2)
        ->and(data_get($claim->fresh()->meta, 'evidence.record_ids'))->toHaveCount(2)
        ->and(VoucherClaimEvidence::query()->whereBelongsTo($claim, 'claim')->count())->toBe(2)
        ->and($claim->evidence()->where('requirement_key', 'name')->value('summary'))->toBe('Juan Dela Cruz')
        ->and($claim->evidence()->where('requirement_key', 'location')->value('summary'))->toBe('Makati City');
    expect($voucher->fresh()->redeemed_at)->toBeNull();
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
    expect($voucher->fresh()->redeemed_at)->not->toBeNull();
});

it('marks a voucher redeemed when a withdrawal fully consumes it', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-RECORD-003',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                    'slice_mode' => 'open',
                    'max_slices' => 1,
                    'min_withdrawal' => 100,
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
        status: 'withdrawn',
        requested_amount: 100,
        disbursed_amount: 100,
        currency: 'PHP',
        remaining_balance: 0,
        fully_claimed: true,
        disbursement: [],
        messages: ['OK'],
    );

    app(RecordVoucherClaim::class)->handle($voucher, $result, []);

    expect($voucher->fresh()->redeemed_at)->not->toBeNull();
});
