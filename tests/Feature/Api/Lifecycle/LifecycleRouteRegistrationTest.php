<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers the first lifecycle routes', function () {
    $routes = collect(app('router')->getRoutes()->getRoutesByName());

    expect($routes)->toHaveKey('api.x.v1.issuers.store')
        ->and($routes)->toHaveKey('api.x.v1.issuers.wallets.store')
        ->and($routes)->toHaveKey('api.x.v1.pricelist.show')
        ->and($routes)->toHaveKey('api.x.v1.vouchers.estimate')
        ->and($routes)->toHaveKey('api.x.v1.vouchers.store')
        ->and($routes)->toHaveKey('api.x.v1.vouchers.claim.start')
        ->and($routes)->toHaveKey('api.x.v1.vouchers.claim.submit')
        ->and($routes)->toHaveKey('api.x.v1.reconciliations.show')
        ->and(Route::has('api.x.v1.wallets.show'))->toBeTrue()
        ->and(Route::has('api.x.v1.wallets.balance.show'))->toBeTrue()
        ->and(Route::has('api.x.v1.wallets.ledger.index'))->toBeTrue()
        ->and(Route::has('api.x.v1.wallets.topups.store'))->toBeTrue()
        ->and(Route::has('api.x.v1.vouchers.claim.complete'))->toBeTrue()
        ->and(Route::has('api.x.v1.vouchers.claim.status.show'))->toBeTrue()
        ->and(Route::has('api.x.v1.vouchers.index'))->toBeTrue()
        ->and(Route::has('api.x.v1.vouchers.show'))->toBeTrue()
        ->and(Route::has('api.x.v1.vouchers.code.show'))->toBeTrue()
        ->and(Route::has('api.x.v1.vouchers.status.show'))->toBeTrue()
        ->and(Route::has('api.x.v1.vouchers.cancel'))->toBeTrue()
        ->and(Route::has('api.x.v1.reconciliations.index'))->toBeTrue()
        ->and(Route::has('api.x.v1.reconciliations.show'))->toBeTrue()
        ->and(Route::has('api.x.v1.reconciliations.resolve'))->toBeTrue()
        ->and(Route::has('api.x.v1.events.index'))->toBeTrue()
        ->and(Route::has('api.x.v1.events.show'))->toBeTrue()
        ->and(Route::has('api.x.v1.events.idempotency.show'))->toBeTrue();
});
