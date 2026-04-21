<?php

it('exposes lifecycle routes for scramble', function () {
    $routes = collect(app('router')->getRoutes())->map->uri();

    expect($routes->filter(fn ($uri) => str_starts_with($uri, 'api/x/v1')))
        ->not->toBeEmpty();
});
