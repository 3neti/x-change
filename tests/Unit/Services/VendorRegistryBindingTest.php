<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VendorRegistryContract;
use LBHurtado\XChange\Services\ConfigVendorRegistry;

it('resolves config vendor registry by default', function () {
    config()->set('x-change.vendors.registry', 'config');

    app()->forgetInstance(VendorRegistryContract::class);

    expect(app(VendorRegistryContract::class))
        ->toBeInstanceOf(ConfigVendorRegistry::class);
});

it('fails for unsupported vendor registry driver', function () {
    config()->set('x-change.vendors.registry', 'bogus');

    app()->forgetInstance(VendorRegistryContract::class);

    app(VendorRegistryContract::class);
})->throws(InvalidArgumentException::class, 'Unsupported vendor registry: bogus');
