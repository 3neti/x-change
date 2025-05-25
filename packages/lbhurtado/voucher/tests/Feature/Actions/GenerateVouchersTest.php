<?php

use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Data\InputFieldsData;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
});

it('generates multiple vouchers using default count, prefix, mask, and TTL', function () {
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
        ]),
        feedback: new FeedbackInstructionData(
            email: 'feedback@acme.com',
            mobile: '09171234567',
            webhook: 'https://acme.com/webhook',
        ),
        rider: new RiderInstructionData(
            message: 'Welcome!',
            url: 'https://acme.com/rider',
        )
    );

    $vouchers = GenerateVouchers::run($instructions);

    expect($vouchers)->toHaveCount(1);
    expect($vouchers->first())->toBeInstanceOf(Voucher::class);
    expect($vouchers->first()->metadata['instructions']['cash']['amount'])->toBe(1000);
    Event::assertDispatched(VouchersGenerated::class, function ($event) use ($vouchers) {
        return $event->getVouchers()->count() === 1;
    });
});

it('generates vouchers with custom prefix, mask, and TTL', function () {
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: 500,
            currency: 'USD',
            validation: new CashValidationRulesData(
                secret: 'abcdef',
                mobile: '09998887777',
                country: 'US',
                location: 'New York',
                radius: '500m'
            )
        ),
        inputs: new InputFieldsData([
            VoucherInputField::KYC,
            VoucherInputField::REFERENCE_CODE,
        ]),
        feedback: new FeedbackInstructionData(
            email: 'support@us.example.com',
            mobile: '09998887777',
            webhook: 'https://us.example.com/webhook',
        ),
        rider: new RiderInstructionData(
            message: 'Claim in USD only',
            url: 'https://us.example.com/rules',
        )
    );

    $vouchers = GenerateVouchers::run(
        data: $instructions,
        count: 2,
        prefix: 'AA',
        mask: '****',
        ttl: 'PT12H'
    );

    expect($vouchers)->toHaveCount(2);
    expect(Str::startsWith($vouchers->first()->code, 'AA'))->toBeTrue();
    expect($vouchers->first()->metadata['instructions']['cash']['currency'])->toBe('USD');
    Event::assertDispatched(VouchersGenerated::class, function ($event) use ($vouchers) {
        return $event->getVouchers()->count() === 2;
    });
});
