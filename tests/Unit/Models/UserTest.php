<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use LBHurtado\ModelChannel\Models\Channel;
use LBHurtado\Wallet\Enums\WalletType;
use App\Models\User;

uses(RefreshDatabase::class);

test('user implementations', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(Bavix\Wallet\Interfaces\Wallet::class);
    expect($user)->toBeInstanceOf(Bavix\Wallet\Interfaces\Confirmable::class);
    expect($user)->toBeInstanceOf(LBHurtado\ModelChannel\Contracts\ChannelsInterface::class);
    expect($user)->toBeInstanceOf(LBHurtado\PaymentGateway\Contracts\MerchantInterface::class);
});

test('user wallets', function () {
    $user = User::factory()->create();
    expect($user->wallets()->count())->toBe(4);

    expect($user->wallet)->toBeInstanceOf(Bavix\Wallet\Models\Wallet::class);
    expect($user->wallet->slug)->toBe(WalletType::default()->value);
});

test('user email', function () {
    $user = User::factory()->unverified()->create();
    expect($user->email)->toBeString();
    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('user mobile and webhook channels', function () {
    $user = User::factory()->create();

    expect($user->mobile)->toBeNull();
    expect($user->webhook)->toBeNull();

    $user->mobile = '09171234567'; // magic __set() triggers channel creation
    $user->webhook = 'https://example.com/webhook';
    $user->save();

    $user->refresh();

    // Check that the accessor returns normalized mobile
    expect($user->mobile)->toBe('639171234567');

    $mobile_channel = Channel::where('model_type', $user->getMorphClass())
        ->where('model_id', $user->getKey())
        ->where('name', 'mobile')
        ->where('value', '639171234567') // E.164 without +
        ->first();

    expect($mobile_channel)->not()->toBeNull();

    $webhook_channel = Channel::where('model_type', $user->getMorphClass())
        ->where('model_id', $user->getKey())
        ->where('name', 'webhook')
        ->where('value', 'https://example.com/webhook')
        ->first();

    expect($webhook_channel)->not()->toBeNull();
});
