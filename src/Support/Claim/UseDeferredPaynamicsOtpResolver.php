<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Closure;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;

final class UseDeferredPaynamicsOtpResolver
{
    public function run(Closure $callback): mixed
    {
        $app = app();

        $previous = $app->bound(ConstellationOtpResolver::class)
            ? $app->make(ConstellationOtpResolver::class)
            : null;

        $app->forgetInstance(ConstellationOtpResolver::class);

        $app->bind(ConstellationOtpResolver::class, DeferredOtpResolver::class);

        try {
            return $callback();
        } finally {
            $app->forgetInstance(ConstellationOtpResolver::class);

            if ($previous) {
                $app->instance(ConstellationOtpResolver::class, $previous);
            }
        }
    }
}
