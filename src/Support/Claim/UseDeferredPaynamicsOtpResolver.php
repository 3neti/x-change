<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Closure;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;

final class UseDeferredPaynamicsOtpResolver
{
    public function run(Closure $callback): mixed
    {
        $app = app();

        $previousResolver = $app->bound(ConstellationOtpResolver::class)
            ? $app->make(ConstellationOtpResolver::class)
            : null;

        $previousPayoutProvider = $app->bound(PayoutProvider::class)
            ? $app->make(PayoutProvider::class)
            : null;

        $app->forgetInstance(ConstellationOtpResolver::class);

        $app->instance(
            ConstellationOtpResolver::class,
            $app->make(DeferredOtpResolver::class),
        );

        if ($previousPayoutProvider instanceof ConstellationPayoutProvider) {
            $app->forgetInstance(PayoutProvider::class);

            $app->instance(
                PayoutProvider::class,
                $app->make($previousPayoutProvider::class),
            );
        }

        try {
            return $callback();
        } finally {
            $app->forgetInstance(PayoutProvider::class);
            $app->forgetInstance(ConstellationOtpResolver::class);

            if ($previousResolver) {
                $app->instance(ConstellationOtpResolver::class, $previousResolver);
            }

            if ($previousPayoutProvider) {
                $app->instance(PayoutProvider::class, $previousPayoutProvider);
            }
        }
    }
}
