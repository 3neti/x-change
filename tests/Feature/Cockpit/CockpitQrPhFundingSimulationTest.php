<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;

it('publishes a verified and rate-limited Cockpit QR Ph simulation route', function () {
    $route = Route::getRoutes()->getByName(
        'x-change.cockpit.funding.scenarios.qrph.store',
    );

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('verified')
        ->and($route->gatherMiddleware())->toContain('throttle:3,1');
});

it('renders the rollback-only QR Ph simulator controls without a monetary QR payload', function () {
    $owner = actingAsTestUser();
    $owner->forceFill([
        'email_verified_at' => now(),
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ])->save();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'));

    $response->assertOk()
        ->assertJsonPath('props.funding_simulation.enabled', true)
        ->assertJsonPath('props.funding_simulation.mode', 'rollback-only')
        ->assertJsonPath('props.funding_simulation.provider_calls', false)
        ->assertJsonPath('props.funding_simulation.balance_changes', false)
        ->assertJsonPath('props.funding_simulation.mobile_ready', true);

    expect((string) $response->json('props.funding_simulation.qr_code'))
        ->toStartWith('data:image/png;base64,');
});

it('runs the Cockpit QR Ph funding simulation without durable state or duplicate credit', function () {
    Http::preventStrayRequests();
    config()->set('x-change.cockpit.qrph_funding_simulation.enabled', true);
    config()->set('x-change.lifecycle.qrph_funding_simulation.enabled', true);
    $owner = actingAsTestUser();
    $owner->forceFill([
        'email_verified_at' => now(),
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ])->save();
    config()->set('x-change.lifecycle.defaults.user_model', $owner::class);
    $balanceBefore = (int) $owner->wallet->balance;
    $countsBefore = [
        FundingIntent::query()->count(),
        SimulatedFundingTransaction::query()->count(),
        FundingSettlement::query()->count(),
        DB::table('webhook_receipts')->count(),
        DB::table('provider_funding_observations')->count(),
    ];

    $response = $this->postJson(route(
        'x-change.cockpit.funding.scenarios.qrph.store',
    ));

    $response->assertOk()
        ->assertJsonPath('schema', 'x-change.lifecycle.qrph-funding-simulation.v1')
        ->assertJsonPath('success', true)
        ->assertJsonPath('rollback_completed', true)
        ->assertJsonPath('simulation.provider_calls', 0)
        ->assertJsonPath('balance.credited_minor', 2_500)
        ->assertJsonCount(7, 'steps')
        ->assertJsonMissing(['639173011987']);

    expect([
        FundingIntent::query()->count(),
        SimulatedFundingTransaction::query()->count(),
        FundingSettlement::query()->count(),
        DB::table('webhook_receipts')->count(),
        DB::table('provider_funding_observations')->count(),
    ])->toBe($countsBefore)
        ->and((int) $owner->wallet->refresh()->balance)->toBe($balanceBefore);
});

it('requires an already verified mobile for the Cockpit QR Ph simulation', function () {
    config()->set('x-change.cockpit.qrph_funding_simulation.enabled', true);
    $owner = actingAsTestUser();
    $owner->forceFill([
        'email_verified_at' => now(),
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();

    $this->postJson(route(
        'x-change.cockpit.funding.scenarios.qrph.store',
    ))->assertForbidden()
        ->assertJsonPath('message', 'Verify your mobile number before continuing.');
});
