<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceTemplateRegistry;

it('resolves known cockpit issuance template profiles', function () {
    $registry = new DefaultCockpitIssuanceTemplateRegistry;

    $profile = $registry->resolve('ofw-remittance');

    expect($profile)->not->toBeNull()
        ->and($profile?->key)->toBe('ofw-remittance')
        ->and($profile?->default_input_fields)->toContain('mobile')
        ->and($profile?->metadata['purpose'])->toBe('remittance');
});

it('keeps unsupported future templates disabled instead of executable by default', function () {
    $profile = (new DefaultCockpitIssuanceTemplateRegistry)->resolve('settlement-envelope');

    expect($profile)->not->toBeNull()
        ->and($profile?->enabled)->toBeFalse();
});

it('returns null for unknown template keys', function () {
    expect((new DefaultCockpitIssuanceTemplateRegistry)->resolve('imaginary-template'))->toBeNull();
});
