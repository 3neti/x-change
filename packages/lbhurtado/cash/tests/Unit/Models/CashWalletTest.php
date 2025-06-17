<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Cash\Models\Cash;
use Brick\Money\Money;

uses(RefreshDatabase::class);

it('has a wallet and a default balance the same as the amount', function () {
    $cash = Cash::factory()->create([
        'amount' => 1_500.00,
        'currency' => 'PHP',
        'meta' => ['note' => 'Disbursed for transport support'],
    ]);
    expect((float) $cash->wallet->balanceFloat)->toBe(1_500.0);
    expect((float) $cash->balanceFloat)->toBe(1_500.0);
});


