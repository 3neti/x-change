<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, get};
use Illuminate\Support\Facades\Route;
use App\Models\User;

uses(RefreshDatabase::class);

// --- Setup route bindings if needed
beforeAll(function () {
    Route::getRoutes()->refreshNameLookups(); // ensures route('wallet.balance') works
});

//test('authenticated user can view inertia wallet balance page', function () {
//    $user = User::factory()->create();
//    actingAs($user)
//        ->get(route('wallet.balance'))
//        ->assertOk()
//        ->assertInertia(fn ($page) => $page
//            ->component('Wallet/Balance')
//            ->has('balance.amount')
//            ->has('balance.currency')
//            ->has('balance.type')
//        );
//});

test('authenticated API user can get JSON wallet balance', function () {
    $user = User::factory()->create();
    actingAs($user, 'sanctum');

    get('/api/wallet/balance', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure([
            'balance',
            'currency',
            'type',
        ]);
});

test('unauthenticated user cannot access inertia wallet balance', function () {
    get(route('wallet.balance'))
        ->assertRedirectToRoute('login');
});

test('unauthenticated API user cannot access wallet balance JSON', function () {
    get('/api/wallet/balance', ['Accept' => 'application/json'])
        ->assertUnauthorized();
});
