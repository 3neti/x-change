<?php

use LBHurtado\Cash\Data\CashData;
use LBHurtado\Cash\Models\Cash;
use Illuminate\Support\Carbon;
use Brick\Money\Money;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // make sure the transformer has at least one format to pick
    config()->set('data.date_format', 'Y-m-d\TH:i:sP');
});

it('maps a Cash model with non-null expires_on into CashData', function () {
    /** @var Cash $cash */
    $cash = Cash::factory()->create([
        // factory should set amount via your model mutator
        'expires_on' => now()->addHours(2),
        'meta'       => ['foo' => 'bar'],
        'secret'     => 'supersecret',
    ]);

    $dto = CashData::fromModel($cash);

    expect($dto->amount)->toBeInstanceOf(Money::class)
        ->and((string) $dto->amount)->toBe((string) $cash->amount);

    expect($dto->meta)->toBe($cash->meta);
    expect($dto->secret)->toBe($cash->secret);

    // expires_on comes through as Carbon
    expect($dto->expires_on)->toBeInstanceOf(Carbon::class)
        ->and($dto->expires_on->equalTo($cash->expires_on))->toBeTrue();

    // expired must match the model
    expect($dto->expired)->toBe($cash->expired);

    expect($dto->status)->toBe($cash->status);
    expect($dto->tags)->toBe($cash->tags->pluck('name')->all());
});

it('maps a Cash model with null expires_on into CashData', function () {
    /** @var Cash $cash */
    $cash = Cash::factory()->create([
        'expires_on' => null,
        'meta'       => ['hello' => 'world'],
        'secret'     => 'anothersecret',
    ]);

    $dto = CashData::fromModel($cash);

    expect($dto->expires_on)->toBeNull();
    expect($dto->expired)->toBeFalse();   // should not be expired when null
});

it('serializes to array/JSON correctly', function () {
    /** @var Cash $cash */
    $cash = Cash::factory()->create([
        'expires_on' => now()->subMinutes(30),
        'meta'       => ['x' => 'y'],
        'secret'     => 'abc123',
    ]);

    $dto = CashData::fromModel($cash);
    $arr = $dto->toArray();

    // Check keys exist
    expect(array_keys($arr))->toEqual([
        'amount', 'meta', 'secret', 'expires_on', 'expired', 'status', 'tags',
    ]);

    // The transformed amount should be a string (per MoneyToStringTransformer)
    expect($arr['amount'])->toBeString();

    // expires_on should be an ISO 8601 string
    expect($arr['expires_on'])->toBeString()
        ->and(fn($value) => strtotime($value) !== false);
});
