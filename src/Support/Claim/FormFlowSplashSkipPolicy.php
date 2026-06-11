<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class FormFlowSplashSkipPolicy
{
    public function apply(array $payload): array
    {
        if (! $this->shouldSkipConsumedSplash($payload)) {
            return $payload;
        }

        $steps = $payload['steps'] ?? null;

        if (! is_array($steps)) {
            return $payload;
        }

        $payload['steps'] = array_values(array_filter(
            $steps,
            fn (mixed $step): bool => ! $this->isSplashStep($step),
        ));

        return $payload;
    }

    private function shouldSkipConsumedSplash(array $payload): bool
    {
        return data_get($payload, 'metadata.claim_experience.options.skip_consumed_splash') === true;
    }

    private function isSplashStep(mixed $step): bool
    {
        return is_array($step)
            && ($step['handler'] ?? null) === 'splash';
    }
}
