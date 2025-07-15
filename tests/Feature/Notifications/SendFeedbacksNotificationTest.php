<?php

use Illuminate\Support\Facades\{Config, Notification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\SendFeedbacksNotification;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Data\{
    CashValidationRulesData,
    FeedbackInstructionData,
    VoucherInstructionsData,
    RiderInstructionData,
    CashInstructionData,
    InputFieldsData,
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

it('sends SendFeedbackNotification via mail and engage_spark', function (Voucher $voucher) {
    Notification::fake();

    $routes = [
        'mail' => 'lester@hurtado.ph',
        'engage_spark' => '09173011987',
    ];

    Notification::routes($routes)->notify(new SendFeedbacksNotification($voucher->code));

    Notification::assertSentOnDemand(SendFeedbacksNotification::class, function ($notification, $channels, $notifiable) use ($routes) {
        expect($notifiable)->toBeInstanceOf(Illuminate\Notifications\AnonymousNotifiable::class);
        expect($channels)->toContain('mail')->toContain('engage_spark');
        expect($notifiable->routes)->toMatchArray($routes);
        return true;
    });
})->with('voucher');
