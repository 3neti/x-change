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

it('exposes settlement envelope as an executable quick generate template', function () {
    $profile = (new DefaultCockpitIssuanceTemplateRegistry)->resolve('settlement-envelope');

    expect($profile)->not->toBeNull()
        ->and($profile?->enabled)->toBeTrue()
        ->and($profile?->profile)->toBe('settlement');
});

it('exposes a canonical blank pay code starting point', function () {
    $profile = (new DefaultCockpitIssuanceTemplateRegistry)->resolve('blank-pay-code');

    expect($profile)->not->toBeNull()
        ->and($profile?->enabled)->toBeTrue()
        ->and($profile?->default_input_fields)->toBe([])
        ->and($profile?->default_validation)->toBe([])
        ->and($profile?->metadata['purpose'])->toBe('operator-defined');
});

it('returns null for unknown template keys', function () {
    expect((new DefaultCockpitIssuanceTemplateRegistry)->resolve('imaginary-template'))->toBeNull();
});
