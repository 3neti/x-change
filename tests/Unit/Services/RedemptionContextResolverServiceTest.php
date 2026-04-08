<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\XChange\Services\DefaultRedemptionContextResolverService;

it('resolves redemption context from payload', function () {
    $service = new DefaultRedemptionContextResolverService;

    $result = $service->resolve([
        'mobile' => '09171234567',
        'secret' => '1234',
        'inputs' => [
            'name' => 'Juan Dela Cruz',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ]);

    expect($result)->toBeInstanceOf(RedemptionContext::class);
    expect($result->mobile)->toBe('09171234567');
    expect($result->secret)->toBe('1234');
    expect($result->vendorAlias)->toBeNull();
    expect($result->inputs)->toBe([
        'name' => 'Juan Dela Cruz',
    ]);
    expect($result->bankAccount)->toBe([
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
    ]);
});
