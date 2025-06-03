<?php

use LBHurtado\PaymentGateway\Models\Merchant;

it('has fillable properties', function () {
    $model = Merchant::factory()->make();

    expect($model->getFillable())->toBe(['code', 'name', 'city']);
});

it('has a factory', function () {
    $model = Merchant::factory()->create();
    expect($model)->toBeInstanceOf(Merchant::class);
});

it('can create a merchant', function () {
    $data = [
        'code' => 'AA-537',
        'name' => 'Dev Asiana',
        'city' => 'City of Manila',
    ];

    $model = Merchant::create($data);

    // Assert the record was saved in the database
    expect(Merchant::find($model->id))->not->toBeNull();
    $this->assertDatabaseHas('merchants', $data); // Laravel assertion helper

    // Verify attributes
    expect($model->code)->toBe('AA-537');
    expect($model->name)->toBe('Dev Asiana');
    expect($model->city)->toBe('City of Manila');
});

it('can return a factory instance', function () {
    $factory = Merchant::newFactory();

    expect($factory)->toBeInstanceOf(\LBHurtado\PaymentGateway\Database\Factories\MerchantFactory::class);
});
