<?php

declare(strict_types=1);

use FrittenKeeZ\Vouchers\Config;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

it('shows an account holder only their own Pay Codes', function () {
    $issuer = actingAsTestUser();
    $visibleVoucher = issueVoucher();

    actingAsTestUser();
    $hiddenVoucher = issueVoucher();

    $this->actingAs($issuer)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('props.pay_codes_read_model.records.0.code', $visibleVoucher->code)
        ->assertJsonMissingPath('props.pay_codes_read_model.records.1')
        ->assertJsonMissing(['code' => $hiddenVoucher->code]);
});

it('projects a claimed contact without exposing the full mobile number', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher();
    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'name' => 'Leslie Chong',
        'bank_account' => 'GCASH:09171234567',
    ]);
    $redeemerClass = Config::model('redeemer');
    $redeemer = new $redeemerClass(['metadata' => []]);
    $redeemer->redeemer()->associate($contact);
    $voucher->redeemers()->save($redeemer);
    $voucher->forceFill(['redeemed_at' => now()])->save();

    $record = collect(app(VoucherLifecycleServiceContract::class)->list([
        'issuer_id' => $issuer->getKey(),
        'issuer_type' => $issuer->getMorphClass(),
        'include' => ['redeemer'],
    ]))->sole();

    expect($record['code'])->toBe($voucher->code)
        ->and($record['party'])->toBe([
            'state' => 'claimed',
            'label' => 'Claimed by',
            'primary' => 'Leslie Chong',
            'secondary' => '•••• 4567',
            'masked' => true,
        ])
        ->and(json_encode($record))->not->toContain('09171234567');
});

it('projects capability instructions target and timing without raw instruction payloads', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'voucher_type' => 'payable',
        'target_amount' => 250,
        'cash' => [
            'validation' => [
                'payable' => 'TESTSHOP',
            ],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
            ],
        ],
    ]));

    $record = collect(app(VoucherLifecycleServiceContract::class)->list([
        'issuer_id' => $issuer->getKey(),
        'issuer_type' => $issuer->getMorphClass(),
        'include' => ['redeemer'],
    ]))->sole();

    expect($record)->toMatchArray([
        'code' => $voucher->code,
        'capability' => [
            'key' => 'collection',
            'label' => 'Collection',
            'voucher_type_label' => 'Payable',
        ],
        'instruction_badges' => [
            ['key' => 'vendor_bound', 'label' => 'Vendor-bound'],
            ['key' => 'settlement_rail', 'label' => 'InstaPay'],
            ['key' => 'otp', 'label' => 'OTP'],
        ],
        'party' => [
            'state' => 'targeted',
            'label' => 'Vendor',
            'primary' => 'TESTSHOP',
            'secondary' => null,
            'masked' => false,
        ],
    ])
        ->and($record['timing']['created_at'])->not->toBeNull()
        ->and($record)->not->toHaveKey('instructions');
});
