<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;
use LBHurtado\XChange\Support\Claim\UseDeferredPaynamicsOtpResolver;

it('temporarily resolves the deferred Paynamics OTP resolver', function () {
    app()->forgetInstance(ConstellationOtpResolver::class);

    $result = app(UseDeferredPaynamicsOtpResolver::class)->run(function () {
        return app(ConstellationOtpResolver::class)::class;
    });

    expect($result)->toBe(DeferredOtpResolver::class);
});
