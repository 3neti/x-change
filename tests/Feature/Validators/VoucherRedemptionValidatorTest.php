<?php

use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\CashInstructionData;
use App\Validators\VoucherRedemptionValidator;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Data\InputFieldsData;
use Illuminate\Support\Facades\Config;
use LBHurtado\Voucher\Models\Voucher;
use App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('account.system_user.identifier', 'apple@hurtado.ph');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);
    $this->system = User::factory()->create(['email' => 'apple@hurtado.ph']);
    $this->system->wallet;
    $this->system->depositFloat(10000);

    $user = User::factory()->create();
    $user->mobile = '09178251991';
    $user->save();

    TopupWalletAction::run($user, 1000);

    $this->actingAs($user);
});

dataset('voucher', function () {
    return [
        [ fn() => GenerateVouchers::run(new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 53.7,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: 'correct-secret',
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
            ),
            count: 1, // Default count
            prefix: null, // Use default prefix from config
            mask: null,   // Use default mask from config
            ttl: null     // Use default TTL (12 hours) from action
        ))->first() ]
    ];
});

it('passes mobile validation when expected matches actual', function (Voucher $voucher) {
    $validator = new VoucherRedemptionValidator($voucher);
    expect($validator->validateMobile('0917-123-4567'))->toBeTrue();
})->with('voucher');

it('fails mobile validation when expected does not match actual', function (Voucher $voucher) {
    $validator = new VoucherRedemptionValidator($voucher);
    expect($validator->validateMobile('09998887777'))->toBeFalse();
    expect($validator->errors()->has('mobile'))->toBeTrue();
})->with('voucher');

it('skips mobile validation when expected mobile is not set', function (Voucher $voucher) {
    $meta = $voucher->metadata;
    $meta['instructions']['cash']['validation']['mobile'] = null;
    $voucher->metadata = $meta;
    $voucher->save();
    $validator = new VoucherRedemptionValidator($voucher);
    expect($validator->validateMobile('09998887777'))->toBeTrue();
})->with('voucher');

it('passes secret validation when correct secret is provided', function (Voucher $voucher) {
    $validator = new VoucherRedemptionValidator($voucher);
    expect($validator->validateSecret('correct-secret'))->toBeTrue();
})->with('voucher');

it('fails secret validation when incorrect secret is provided', function (Voucher $voucher) {
    $validator = new VoucherRedemptionValidator($voucher);
    expect($validator->validateSecret('wrong-secret'))->toBeFalse();
    expect($validator->errors()->has('secret'))->toBeTrue();
})->with('voucher');

//it('skips secret validation when voucher has no secret configured', function (Voucher $voucher) {
//    $meta = $voucher->metadata;
//    $meta['instructions']['cash']['validation']['secret'] = null;
//    $voucher->metadata = $meta;
//    $voucher->save();
//    $validator = new VoucherRedemptionValidator($voucher);
//    expect($validator->validateSecret('ssss'))->toBeTrue();
//})->with('voucher');
