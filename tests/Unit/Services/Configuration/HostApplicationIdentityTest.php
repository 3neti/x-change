<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Configuration\HostApplicationIdentity;

it('normalizes an existing application prefix without duplicating it', function () {
    $identity = app(HostApplicationIdentity::class);

    expect($identity->resolve('X-Change'))->toBe([
        'display_name' => 'x-Change',
        'slug' => 'x-change',
    ])->and($identity->resolve('x-PayOut'))->toBe([
        'display_name' => 'x-PayOut',
        'slug' => 'x-payout',
    ]);
});
