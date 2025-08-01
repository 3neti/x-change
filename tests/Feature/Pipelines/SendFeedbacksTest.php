<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Pipelines\RedeemedVoucher\SendFeedbacks;
use App\Notifications\SendFeedbacksNotification;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Config;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Data\{
    VoucherInstructionsData,
    CashValidationRulesData,
    FeedbackInstructionData,
    RiderInstructionData,
    CashInstructionData,
    InputFieldsData
};
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
                email: 'lester@hurtado.ph',
                mobile: '09173011987',
//                webhook: 'https://acme.com/webhook',
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


it('sends notification via mail and engage_spark if feedback provided', function (Voucher $voucher) {
    Notification::fake();

    $called = false;
    $pipeline = new SendFeedbacks();

    $result = $pipeline->handle($voucher, function ($v) use (&$called) {
        $called = true;
        return $v;
    });

    expect($called)->toBeTrue()
        ->and($result)->toBeInstanceOf(Voucher::class);

    Notification::assertSentOnDemand(SendFeedbacksNotification::class, function ($notification, $channels, $notifiable) {
        expect($channels)->toContain('mail')->toContain('engage_spark')
            ->and($notifiable->routes)->toMatchArray([
                'mail' => 'lester@hurtado.ph',
                'engage_spark' => '09173011987',
            ]);
        return true;
    });
})->with('voucher');

//it('skips notification when feedback routes are empty', function (Voucher $voucher) {
//    Notification::fake();
//
//    $voucher = createVoucherWithFeedback([]); // No email or mobile
//
//    $called = false;
//    $pipeline = new SendFeedbacks();
//
//    $result = $pipeline->handle($voucher, function ($v) use (&$called) {
//        $called = true;
//        return $v;
//    });
//
//    expect($called)->toBeTrue()
//        ->and($result)->toBeInstanceOf(Voucher::class);
//
//    Notification::assertNothingSent();
//})->with('voucher');
