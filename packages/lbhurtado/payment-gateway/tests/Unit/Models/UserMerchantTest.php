<?php

use LBHurtado\PaymentGateway\Models\Merchant;
use LBHurtado\PaymentGateway\Tests\Models\User;

it('can associate a user with a merchant', function () {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create();

    $user->merchant = $merchant;
    $user->save();

    expect($user->merchant)->toBeInstanceOf(Merchant::class)
        ->and($user->merchant->is($merchant))->toBeTrue();

    // Check the pivot table entry directly
    $this->assertDatabaseHas('merchant_user', [
        'user_id' => $user->id,
        'merchant_id' => $merchant->id
    ]);
});
