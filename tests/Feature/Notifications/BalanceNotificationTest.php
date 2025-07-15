<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\BalanceNotification;
use LBHurtado\Wallet\Enums\WalletType;
use Brick\Money\Money;
use App\Models\User;

uses(RefreshDatabase::class);

test('balance notification is sent via mail and engage_spark', function () {
    Notification::fake();

    $user = User::factory()->create();
    $balance = Money::of(1500.75, 'PHP');
    $walletType = WalletType::PLATFORM;

    $user->notify(new BalanceNotification($balance, $walletType));

    Notification::assertSentTo(
        $user,
        BalanceNotification::class,
        function (BalanceNotification $notification, array $channels) use ($balance, $walletType) {
            expect($channels)->toMatchArray(['mail', 'engage_spark']);

            return $notification->balance->isEqualTo($balance)
                && $notification->walletType === $walletType;
        }
    );
});

test('balance notification is sent live', function () {
//    Config::set('MAIL_MAILER', 'resend');
    //change <env name="MAIL_MAILER" value="array"/>
    //to <env name="MAIL_MAILER" value="resend"/>
    $user = User::factory()->create(['email' => 'lester@lyflyn.net']);
    $user->mobile = '09173011987';
    $user->save();
    $balance = Money::of(5.37, 'PHP');
    $walletType = WalletType::PLATFORM;

    $user->notify(new BalanceNotification($balance, $walletType));
})->skip();

test('balance notification is sent live via sendBalanceNotification', function () {
    $user = User::factory()->create(['email' => 'admin@disburse.cash']);
    $this->actingAs($user);
    $user->mobile = '09173011987';
    $user->depositFloat(537.00);
    $user->save();

    $user->sendBalanceNotification();
});
