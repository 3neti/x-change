<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Repositories\InstructionItemRepository;
use Illuminate\Support\Facades\Config;
use App\Models\InstructionItem;
use App\Models\User;


uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('account.system_user.identifier', 'apple@hurtado.ph');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);

    User::factory()->create(['email' => 'apple@hurtado.ph']);
});

it('finds an item by dot index', function () {
    InstructionItem::factory()->create([
        'index' => 'voucher.cash.validation.mobile',
        'price' => 5000,
        'currency' => 'PHP',
        'meta' => ['description' => 'Mobile number of recipient'],
    ]);

    $item = app(InstructionItemRepository::class)->findByIndex('voucher.cash.validation.mobile');

    expect($item)->not()->toBeNull()
        ->and($item->price)->toBe(5000)
        ->and($item->currency)->toBe('PHP')
        ->and($item->meta['description'])->toBe('Mobile number of recipient');
});

it('returns null when item is not found', function () {
    $item = app(InstructionItemRepository::class)->findByIndex('voucher.cash.validation.secret');

    expect($item)->toBeNull();
});
