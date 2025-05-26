<?php

use Illuminate\Support\Facades\Hash;
use LBHurtado\Voucher\Models\Cash;
use Illuminate\Support\Str;

it('has a default null value for the secret', function () {
    $cash = Cash::factory()->create();

    expect($cash->secret)->toBeNull();
});

it('hashes the secret before saving', function () {
    $cash = Cash::factory()->create();

    $rawSecret = 'my-secret-123';
    $cash->secret = $rawSecret; // This triggers the mutator
    $cash->save();

    // Ensure the stored secret is not raw
    expect(Hash::check($rawSecret, $cash->secret))->toBeTrue();
    expect($cash->secret)->not->toEqual($rawSecret);
});

it('verifies the correct secret using verifySecret method', function () {
    $cash = Cash::factory()->create();

    $rawSecret = 'secure-password';
    $cash->secret = $rawSecret;
    $cash->save();

    // Verify the correct secret
    expect($cash->verifySecret($rawSecret))->toBeTrue();

    // Verify a wrong secret
    expect($cash->verifySecret('invalid-secret'))->toBeFalse();
});

it('handles cash redemption correctly with the proper secret', function () {
    $cash = Cash::factory()->create();
    $rawSecret = 'redeem-now';

    $cash->secret = $rawSecret;
    $cash->save();

    // Redemption logic with the correct secret
    $redeemed = $cash->verifySecret($rawSecret);

    expect($redeemed)->toBeTrue();
});

it('rejects invalid secrets during redemption', function () {
    $cash = Cash::factory()->create();
    $rawSecret = 'real-one';

    $cash->secret = $rawSecret;
    $cash->save();

    // Trying to redeem with an invalid secret
    $invalidSecret = 'fake-secret';

    expect($cash->verifySecret($invalidSecret))->toBeFalse();
});

it('does not allow redemption if cash has expired', function () {
    $cash = Cash::factory()->create([
        'expires_on' => now()->subDay(), // The cash expired yesterday
    ]);

    $rawSecret = 'expired-secret';
    $cash->secret = $rawSecret;
    $cash->save();

    // Ensure expiration date is in the past
    expect($cash->expires_on->isPast())->toBeTrue(); // Cash is expired

    // The provided secret should still be valid when checked
    expect($cash->verifySecret($rawSecret))->toBeTrue();

    // Simulate redemption logic that also considers expiration
    $redemptionAllowed = !$cash->expires_on->isPast() && $cash->verifySecret($rawSecret);

    // Ensure redemption fails because of expiration
    expect($redemptionAllowed)->toBeFalse();
});

it('allows generating system-generated secrets', function () {
    $cash = Cash::factory()->create();

    // Generate a random system secret
    $systemGeneratedSecret = Str::random(16);
    $cash->secret = $systemGeneratedSecret;
    $cash->save();

    // Verify the raw secret against the stored hashed secret
    expect(Hash::check($systemGeneratedSecret, $cash->secret))->toBeTrue();

    // Ensure it's not stored as plain text
    expect($cash->secret)->not->toEqual($systemGeneratedSecret);
});
