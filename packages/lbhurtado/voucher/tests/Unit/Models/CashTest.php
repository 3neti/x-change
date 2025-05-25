<?php

use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use FrittenKeeZ\Vouchers\Models\Voucher;
use LBHurtado\Voucher\Models\Cash;
use Brick\Money\Money;

uses(RefreshDatabase::class);

it('creates a cash record with meta and reference', function () {
    $voucher = Vouchers::create();

    $cash = Cash::factory()->forReference($voucher)->create([
        'amount' => 1500.00,
        'currency' => 'PHP',
        'reference_type' => $voucher::class,
        'reference_id' => $voucher->id,
        'meta' => ['note' => 'Disbursed for transport support'],
    ]);

    expect($cash)->toBeInstanceOf(Cash::class)
        ->and($cash->reference->is($voucher))->toBeTrue()
        ->and($cash->amount)->toBeInstanceOf(Money::class)
        ->and($cash->amount->getAmount()->toFloat())->toBe(1500.00)
        ->and($cash->getRawOriginal('amount'))->toBe(150000)
        ->and($cash->currency)->toBe('PHP')
        ->and($cash->meta)->toBeInstanceOf(ArrayObject::class)
        ->and($cash->meta->note)->toBe('Disbursed for transport support')
        ->and($cash->meta['note'])->toBe('Disbursed for transport support')
        ->and($cash->reference)->toBeInstanceOf(Voucher::class)
        ->and($cash->reference->id)->toBe($voucher->id);
});
