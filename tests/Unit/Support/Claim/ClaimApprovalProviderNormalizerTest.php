<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\ClaimApprovalProviderNormalizer;
use LBHurtado\XChange\Tests\Fakes\FakePayoutProvider;

it('normalizes known claim approval provider aliases', function (string $input, string $expected) {
    $normalizer = new ClaimApprovalProviderNormalizer();

    expect($normalizer->normalize($input))->toBe($expected);
})->with([
    ['paynamics', 'paynamics'],
    ['payanamics', 'paynamics'],
    ['emi-paynamics', 'paynamics'],
    ['emi_paynamics', 'paynamics'],
    ['netbank', 'netbank'],
    ['emi-netbank', 'netbank'],
    ['emi_netbank', 'netbank'],
]);

it('normalizes provider-like class names into clean provider labels', function () {
    $normalizer = new ClaimApprovalProviderNormalizer();

    expect($normalizer->normalize('App\\Providers\\PaynamicsPayoutProvider'))->toBe('paynamics')
        ->and($normalizer->normalize('App\\Providers\\NetbankPayoutProvider'))->toBe('netbank');
});

it('normalizes provider objects by class name', function () {
    $normalizer = new ClaimApprovalProviderNormalizer();

    expect($normalizer->normalize(new FakePayoutProvider()))->toBe(strtolower(FakePayoutProvider::class));
});

it('returns null for empty claim approval provider values', function () {
    $normalizer = new ClaimApprovalProviderNormalizer();

    expect($normalizer->normalize(null))->toBeNull()
        ->and($normalizer->normalize(''))->toBeNull()
        ->and($normalizer->normalize('   '))->toBeNull();
});
