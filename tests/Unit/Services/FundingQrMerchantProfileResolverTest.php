<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Funding\FundingQrMerchantProfileResolver;
use LBHurtado\XChange\Support\Funding\FundingMerchantSnapshot;
use LBHurtado\XChange\Tests\Fakes\User;

it('bounds generated QR merchant labels without rejecting an ordinary account name', function () {
    $user = User::query()->create([
        'name' => 'Cloud OTP Alpha Test',
        'email' => 'cloud-otp-alpha@example.test',
        'password' => bcrypt('password'),
    ]);

    $merchant = app(FundingQrMerchantProfileResolver::class)->resolve($user);

    expect($merchant->displayName)
        ->toBe('Cloud OTP Alpha Test')
        ->and(mb_strlen($merchant->displayName))
        ->toBeLessThanOrEqual(25)
        ->and($merchant->city)->toBe('Manila');
});

it('truncates an account name that exceeds the QR merchant label limit', function () {
    $user = User::query()->create([
        'name' => 'A Merchant Name That Is Far Too Long',
        'email' => 'long-merchant@example.test',
        'password' => bcrypt('password'),
    ]);

    $merchant = app(FundingQrMerchantProfileResolver::class)->resolve($user);

    expect($merchant->displayName)
        ->toBe('A Merchant Name That Is F')
        ->toHaveLength(25);
});

it('round trips the canonical QR merchant snapshot', function () {
    $user = User::query()->create([
        'name' => 'Snapshot Store',
        'email' => 'snapshot-merchant@example.test',
        'password' => bcrypt('password'),
    ]);
    $merchant = app(FundingQrMerchantProfileResolver::class)->resolve($user);

    expect(FundingMerchantSnapshot::fromData(
        FundingMerchantSnapshot::toData(FundingMerchantSnapshot::fromData($merchant)),
    ))->toBe(FundingMerchantSnapshot::fromData($merchant));
});
