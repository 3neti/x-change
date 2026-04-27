<?php

it('defines all canonical voucher flow types', function () {
    $canonical = config('x-change.voucher_flow_types.canonical');

    expect($canonical)->toHaveKeys([
        'disbursable',
        'collectible',
        'settlement',
    ]);
});

it('defines required capability keys for each canonical flow type', function () {
    $required = [
        'label',
        'direction',
        'can_disburse',
        'can_collect',
        'can_settle',
        'supports_open_slices',
        'supports_delegated_spend',
        'requires_envelope',
        'pay_code_route',
        'qr_type',
    ];

    foreach (config('x-change.voucher_flow_types.canonical') as $key => $definition) {
        expect($definition)->toHaveKeys($required, "Missing required keys for [{$key}].");
    }
});

it('maps legacy aliases to valid canonical types', function () {
    $canonical = array_keys(config('x-change.voucher_flow_types.canonical'));
    $aliases = config('x-change.voucher_flow_types.aliases');

    foreach ($aliases as $legacy => $canonicalType) {
        expect($canonicalType)
            ->toBeIn($canonical, "Alias [{$legacy}] points to invalid canonical type [{$canonicalType}].");
    }
});
