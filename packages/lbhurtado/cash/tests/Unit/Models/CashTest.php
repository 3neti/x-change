<?php

use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Cash\Models\Cash;
use Brick\Money\Money;

uses(RefreshDatabase::class);

it('creates a cash record with meta and reference', function () {

    $cash = Cash::factory()->create([
        'amount' => 1500.00,
        'currency' => 'PHP',
        'meta' => ['note' => 'Disbursed for transport support'],
    ]);

    expect($cash)->toBeInstanceOf(Cash::class)
        ->and($cash->amount)->toBeInstanceOf(Money::class)
        ->and($cash->amount->getAmount()->toFloat())->toBe(1500.00)
        ->and($cash->amount->getMinorAmount()->toInt())->toBe(150000)
        ->and($cash->getRawOriginal('amount'))->toBe(150000)
        ->and($cash->currency)->toBe('PHP')
        ->and($cash->meta)->toBeInstanceOf(ArrayObject::class)
        ->and($cash->meta->note)->toBe('Disbursed for transport support')
        ->and($cash->meta['note'])->toBe('Disbursed for transport support')
    ;
});

it('accepts a Money object and stores it as minor units', function () {
    $money = Money::of(1500.00, 'PHP'); // major units

    $cash = Cash::create([
        'amount' => $money, // uses the mutator
        'currency' => 'PHP',
        'meta' => ['note' => 'Funded via Money object'],
    ]);

    expect($cash)->toBeInstanceOf(Cash::class)
        ->and($cash->getRawOriginal('amount'))->toBe(150000) // stored as minor
        ->and($cash->amount)->toBeInstanceOf(Money::class)
        ->and($cash->amount->getAmount()->toFloat())->toBe(1500.00)
        ->and($cash->amount->getCurrency()->getCurrencyCode())->toBe('PHP');
});

it('proxies value attribute to amount for backward compatibility', function () {
    $cash = Cash::factory()->create(['amount' => 123456, 'currency' => 'PHP']);

    expect($cash->value)->toEqual($cash->amount)
        ->and($cash->value->getMinorAmount()->toInt())->toBe(12345600);

    // Now test setting via `value`
    $cash2 = Cash::create(['value' => 789.00, 'currency' => 'PHP']);

    expect($cash2->amount->getMinorAmount()->toInt())->toBe(78900)
        ->and($cash2->value->getAmount()->toFloat())->toBe(789.00);
});

use Illuminate\Support\Carbon;
use LBHurtado\Cash\Enums\CashStatus;

it('sets and gets expired attribute correctly', function () {
    $cash = Cash::factory()->create();

    // Set expired to true
    $cash->expired = true;
    $cash->save();

    expect($cash->expires_on)->toBeInstanceOf(Carbon::class)
//        ->and($cash->expires_on)->toEqual(now())
        ->and($cash->status)->toEqual(CashStatus::EXPIRED->value);

    // Set expired to false
    $cash->expired = false;

    expect($cash->expires_on)->toBeNull();
});

it('checks if cash is expired', function () {
    $cash = Cash::factory()->create();

    // Expired with a past date
    $cash->expires_on = now()->subDay();

    expect($cash->expired)->toBeTrue();

    // Not expired with a future date
    $cash->expires_on = now()->addDay();

    expect($cash->expired)->toBeFalse();

    // Not expired if expires_on is null
    $cash->expires_on = null;

    expect($cash->expired)->toBeFalse();
});

//it('cannot be updated', function () {
//    $cash = Cash::factory()->create(['amount' => 1500, 'currency' => 'PHP']);
//    $cash->amount = 100;
//    $cash->save();
//    $cash->refresh();
//    expect($cash->amount->getAmount()->toFloat())->toBe(1500.0);
//});
