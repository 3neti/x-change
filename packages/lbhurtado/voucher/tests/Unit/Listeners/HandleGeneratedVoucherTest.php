<?php

use LBHurtado\Voucher\Listeners\HandleGeneratedVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Data\InputFieldsData;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use FrittenKeeZ\Vouchers\Models\Voucher;
use LBHurtado\Voucher\Models\Cash;
use Carbon\CarbonInterval;

uses(RefreshDatabase::class);

//it('handles generated vouchers and creates associated cash records', function () {
//    // Arrange: Create VoucherInstructionsData object
//    $instructions = new VoucherInstructionsData(
//        cash: new CashInstructionData(
//            amount: 2000,
//            currency: 'USD',
//            validation: new CashValidationRulesData(
//                secret: '123456',
//                mobile: '09179876543',
//                country: 'US',
//                location: 'New York',
//                radius: '5000m'
//            )
//        ),
//        inputs: new InputFieldsData([
//            VoucherInputField::EMAIL,
//            VoucherInputField::MOBILE,
//        ]),
//        feedback: new FeedbackInstructionData(
//            email: 'support@company.com',
//            mobile: '09179876543',
//            webhook: 'https://company.com/webhook',
//        ),
//        rider: new RiderInstructionData(
//            message: 'Welcome!',
//            url: 'https://company.com/welcome',
//        ),
//        count: 2,
//        prefix: 'TEST',
//        mask: '****-****',
//        ttl: CarbonInterval::hours(24),
//    );
//
//    // Act: Create vouchers using the Vouchers facade
//    $vouchers = Vouchers::withPrefix($instructions->prefix)
//        ->withMask($instructions->mask)
//        ->withMetadata([
//            'instructions' => $instructions->toArray(),
//        ])
//        ->withExpireTimeIn($instructions->ttl)
//        ->create($instructions->count);
//
//    $collection = collect($vouchers instanceof Voucher ? [$vouchers] : $vouchers);
//    // Dispatch the VouchersGenerated event
//    $event = new VouchersGenerated($collection);
//
//    // Handle the event with the listener
//    $listener = new HandleGeneratedVouchers();
//    $listener->handle($event);
//
//    // Assert: Check that Cash records were created
//    expect(Cash::count())->toBe(2);
//
//    foreach ($vouchers as $voucher) {
//        $cash = $voucher->getEntities(Cash::class)->first();
//
//        // Ensure the Cash record exists and contains the correct data
//        expect($cash)->not->toBeNull()
//            ->and($cash->amount->getAmount()->toInt())->toBe($instructions->cash->amount)
//            ->and($cash->currency)->toBe($instructions->cash->currency)
//        ;
//    }
//
//    // Assert: Ensure that vouchers are marked as processed
//    foreach ($vouchers as $voucher) {
////        $voucher->refresh();
//        expect($voucher->processed)->toBeTrue();
//    }
//});

it('does not process vouchers that are already marked as processed', function () {
    // Arrange: Create VoucherInstructionsData object
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: 1500,
            currency: 'USD',
            validation: new CashValidationRulesData(
                secret: 'abcdef',
                mobile: '09179876543',
                country: 'US',
                location: 'New York',
                radius: '5000m'
            )
        ),
        inputs: new InputFieldsData([
            VoucherInputField::EMAIL,
            VoucherInputField::MOBILE,
        ]),
        feedback: new FeedbackInstructionData(
            email: 'support@company.com',
            mobile: '09179876543',
            webhook: 'https://company.com/webhook',
        ),
        rider: new RiderInstructionData(
            message: 'Hello!',
            url: 'https://company.com/hello',
        ),
        count: 1,
        prefix: 'TEST',
        mask: '****-****',
        ttl: CarbonInterval::hours(24),
    );

    // Act: Create a voucher marked as already processed
    $voucher = Vouchers::withPrefix($instructions->prefix)
        ->withMask($instructions->mask)
        ->withMetadata([
            'instructions' => $instructions->toArray(),
        ])
        ->withExpireTimeIn($instructions->ttl)
        ->create(1)
        ->first();

    $voucher->processed = true;
    $voucher->save();

    // Dispatch the VouchersGenerated event
    $event = new VouchersGenerated(collect([$voucher]));

    // Handle the event with the listener
    $listener = new HandleGeneratedVouchers();
    $listener->handle($event);

    // Assert: Ensure no Cash record was created
    expect(Cash::count())->toBe(0);
});
