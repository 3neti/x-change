<?php

use LBHurtado\Voucher\Data\{FeedbackInstructionData, CashInstructionData, CashValidationRulesData, InputFieldsData, RiderInstructionData, VoucherInstructionsData};
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\{CanConfirmDeposit, CanDisburse, CanGenerate};
use LBHurtado\Voucher\Actions\{GenerateVouchers, RedeemVoucher};
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use LBHurtado\Voucher\Events\DisburseInputPrepared;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Config, Event};
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Propaganistas\LaravelPhone\PhoneNumber;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Cash\Models\Cash;
use App\Actions\EncashCheck;
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
            ),
            count: 1, // Default count
            prefix: null, // Use default prefix from config
            mask: null,   // Use default mask from config
            ttl: null     // Use default TTL (12 hours) from action
        ))->first() ]
    ];
});

test('encash check works', function (Voucher $voucher) {
    $cash = $voucher->getEntities(Cash::class)->first();
    expect((float) $cash->balanceFloat)->toBe(53.7);
//    $phoneNumber = new PhoneNumber('09173011987', 'PH');
    $phoneNumber  = new PhoneNumber('09467438575', 'PH');
//    $meta = ['bank_account' => 'BNORPHMMXXX:000661592316'];
    $meta = [];
    $response = EncashCheck::run($voucher, $phoneNumber, $meta);
    expect($response)->toBeTrue();
})->with('voucher');

test('EncashCheck dispatches RedeemVoucher::run with the right arguments', function (Voucher $voucher) {
    // build a phone number + meta
    $phone  = new PhoneNumber('09173011987', 'PH');
    $meta   = ['bank_account' => 'BNORPHMMXXX:000661592316'];

    // spy on RedeemVoucher::run
    RedeemVoucher::mock()->shouldReceive('handle')
        ->once()
        ->withArgs(function (Contact $c, string $code, array $m) use ($phone, $voucher, $meta) {
            // the Contact::fromPhoneNumber(...) logic lives in your EncashCheck
            $expected = Contact::fromPhoneNumber($phone);
            return $c->is($expected)
                && $code === $voucher->code
                && $m === $meta;
        })
        ->andReturnTrue();

    // call your EncashCheck action
    $result = EncashCheck::run($voucher, $phone, $meta);

    expect($result)->toBeTrue();
})->with('voucher');

//it('fires DisburseInputPrepared with the exact bank payload', function ($voucher) {
//    Event::fake([DisburseInputPrepared::class]);
//
//    // 1) Bind a no-op gateway so we don’t actually hit the bank
//    $this->app->instance(
//        PaymentGatewayInterface::class,
//        new class implements PaymentGatewayInterface {
//            use CanDisburse;
//            use CanConfirmDeposit;
//            use CanGenerate;
//        }
//    );
//
//    // 2) Create a Contact and redeem the voucher, passing our test bank_account meta
//    $contact = Contact::factory()->create(['mobile' => '09171234567']);
//    Vouchers::redeem($voucher->code, $contact, ['redemption.bank_account' => 'BNORPHMMXXX:000661592316']);
//
//    // 3) Now that the voucher has a redeemer, run your pipeline stage
//    app(DisburseCash::class)
//        ->handle($voucher, fn() => null);
//
//    // 4) Assert the event carried the exact payload
//    Event::assertDispatched(DisburseInputPrepared::class, function (DisburseInputPrepared $e) use ($voucher) {
//        return $e->voucher->is($voucher)
//            && $e->input->bank           === 'BNORPHMMXXX'
//            && $e->input->account_number === '000661592316'
//            && $e->input->reference      === "{$voucher->code}-09171234567";
//    });
//})->with('voucher');

//TODO: fix the above test
