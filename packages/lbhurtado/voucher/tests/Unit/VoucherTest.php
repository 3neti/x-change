<?php

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Data\InputFieldsData;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Carbon\CarbonInterval;

uses(RefreshDatabase::class);

it('creates a voucher using data structures', function () {
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: 1000,
            currency: 'PHP',
            validation: new CashValidationRulesData(
                secret: '123456',
                mobile: '09171234567',
                country: 'PH',
                location: 'Makati City',
                radius: '1000m'
            )
        ),
        inputs: new InputFieldsData([
            VoucherInputField::EMAIL,
            VoucherInputField::MOBILE,
            VoucherInputField::REFERENCE_CODE,
            VoucherInputField::SIGNATURE,
            VoucherInputField::KYC,
        ]),
        feedback: new FeedbackInstructionData(
            email: 'feedback@acme.com',
            mobile: '09171234567',
            webhook: 'https://acme.com/webhook',
        ),
        rider: new RiderInstructionData(
            message: 'Hey, thanks for using our service!',
            url: 'https://acme.com/rider',
        )
    );

    $voucher = Vouchers::withPrefix('AA')
        ->withMask('****')
        ->withMetadata([
            'instructions' => $instructions->toArray(),
        ])
        ->withExpireTimeIn(CarbonInterval::hours(12))
        ->create();

    expect($voucher)->not->toBeNull()
        ->and($voucher)->toBeInstanceOf(Voucher::class)
        ->and($voucher->metadata['instructions']['cash']['amount'])->toBe(1000)
        ->and($voucher->metadata['instructions']['inputs']['fields'])->toContain('email', 'mobile');
});
