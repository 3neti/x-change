<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;
use LBHurtado\EmiCore\Data\Providers\ProviderSourceAccountReadinessRequestData;
use LBHurtado\XChange\Services\Funding\SourceAccountReadinessResolver;
use Throwable;

class CheckProviderSourceAccountReadiness
{
    public function __construct(private readonly ?ProviderSourceAccountReadiness $source = null) {}

    /** @return array<string, mixed> */
    public function handle(?int $requiredMinor = null, string $provider = 'netbank'): array
    {
        $source = $this->source !== null && $this->source->providerCode() === $provider
            ? $this->source
            : app(SourceAccountReadinessResolver::class)->for($provider);
        if ($source === null) {
            $enabled = (bool) config("x-change.provider_runtime.providers.{$provider}.source_account_readiness.enabled", false);

            return [
                'enabled' => $enabled,
                'ready' => ! $enabled,
                'checked' => false,
                'applicable' => $enabled,
                'message' => $enabled ? 'No provider source-account readiness capability is available.' : 'Provider source-account readiness check is disabled.',
            ];
        }

        try {
            return $source->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(requiredAmountMinor: $requiredMinor)) ?? [
                'enabled' => true, 'ready' => false, 'checked' => false,
                'message' => 'Provider source-account readiness is unavailable.',
            ];
        } catch (Throwable) {
            return [
                'enabled' => true, 'ready' => false, 'checked' => true,
                'reason' => 'balance_unavailable',
                'message' => 'Provider source-account readiness is unavailable.',
            ];
        }
    }
}
