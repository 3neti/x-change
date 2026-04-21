<?php

declare(strict_types=1);

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
        ->and(Route::has('api.x.v1.wallets.topups.store'))->toBeTrue();

});
