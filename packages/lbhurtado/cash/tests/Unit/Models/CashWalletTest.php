<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Cash\Tests\Models\User;
use LBHurtado\Cash\Models\Cash;

uses(RefreshDatabase::class);

it('has a wallet and can be purchased', function () {
    $cash = Cash::factory()->create([
        'amount' => 1_500.00,
        'currency' => 'PHP',
        'meta' => ['note' => 'Disbursed for transport support'],
    ]);
    expect((float) $cash->wallet->balanceFloat)->toBe(0.0);
    $user = User::factory()->create();
    $user->depositFloat(2_000);
    $user->pay($cash);
    expect((float) $cash->wallet->balanceFloat)->toBe(1_500.0);
    expect((float) $cash->balanceFloat)->toBe(1_500.0);
    expect((float) $user->wallet->balanceFloat)->toBe(500.0);
});


