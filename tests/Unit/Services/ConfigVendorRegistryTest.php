<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\VendorIdentityData;
use LBHurtado\XChange\Services\ConfigVendorRegistry;

it('normalizes vendor aliases', function () {
    $registry = new ConfigVendorRegistry;

    expect($registry->normalize(' meralco '))->toBe('MERALCO')
        ->and($registry->normalize(''))->toBeNull()
        ->and($registry->normalize(null))->toBeNull();
});

it('resolves configured vendor aliases to canonical identity', function () {
    config()->set('x-change.vendors.aliases', [
        'MERALCO' => [
            'id' => 'vendor.meralco',
            'name' => 'Manila Electric Company',
            'aliases' => [
                'meralco',
                'MERALCO ONLINE',
                'MANILA ELECTRIC COMPANY',
            ],
            'meta' => [
                'category' => 'utility',
            ],
        ],
    ]);

    $vendor = (new ConfigVendorRegistry)->resolve('meralco');

    expect($vendor)->toBeInstanceOf(VendorIdentityData::class)
        ->and($vendor->canonicalAlias)->toBe('MERALCO')
        ->and($vendor->vendorId)->toBe('vendor.meralco')
        ->and($vendor->displayName)->toBe('Manila Electric Company')
        ->and($vendor->aliases)->toContain('MERALCO')
        ->and($vendor->aliases)->toContain('MERALCO ONLINE')
        ->and($vendor->meta['category'])->toBe('utility');
});

it('returns normalized fallback identity for unknown aliases', function () {
    config()->set('x-change.vendors.aliases', []);

    $vendor = (new ConfigVendorRegistry)->resolve(' unknown vendor ');

    expect($vendor)->toBeInstanceOf(VendorIdentityData::class)
        ->and($vendor->canonicalAlias)->toBe('UNKNOWN VENDOR')
        ->and($vendor->vendorId)->toBeNull()
        ->and($vendor->aliases)->toBe(['UNKNOWN VENDOR']);
});

it('returns null when resolving empty vendor alias', function () {
    expect((new ConfigVendorRegistry)->resolve(null))->toBeNull()
        ->and((new ConfigVendorRegistry)->resolve(''))->toBeNull();
});
