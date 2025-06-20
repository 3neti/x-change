<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Events\DepositConfirmed;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DepositNotification;
use App\Models\{System, Subscriber};

uses(RefreshDatabase::class);

beforeEach(function () {
    // Per our project convention, always seed the system user first
    System::factory()->create();
});

test('FeedbackDeposit listener sends deposit notification', function () {
    Notification::fake();

    // Create a regular user
    $user = Subscriber::factory()->create([
        'email' => 'lbhurtado@gmail.com',
    ]);

    // Mobile isn't fillable; set it explicitly
    $user->mobile = '09178251991';
    $user->save();

    // Ensure the wallet exists and starts at zero
    $user->wallet;
    $user->wallet->refreshBalance();
    expect((float)$user->balanceFloat)->toBe(0.00);

    // Top up the wallet
    $transfer = TopupWalletAction::run($user, 1000.00);
    $tx = $transfer->deposit;
    $tx->meta = ['foo' => 'bar'];
    $tx->save();

    // Fire the deposit-confirmed event
    event(new DepositConfirmed($tx));

    // Assert that the notification was sent via both mail and SMS
    Notification::assertSentTo(
        $user,
        DepositNotification::class,
        function (DepositNotification $notification, array $channels) use ($tx) {
            // both channels
            expect($channels)->toContain('mail')
                ->and($channels)->toContain('engage_spark');

            // payload matches
            $data = $notification->toArray($notification);
            expect((float) $data['amount'])
                ->toBe((float)$tx->amount / 100)
                ->and($data['meta'])->toBe($tx->meta);

            return true;
        }
    );
});
