<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\ProviderProvisioningPresentation;
use LBHurtado\XChange\Data\ProvisioningFlowDescriptorData;
use RuntimeException;

class BuildProvisioningFlowDescriptor
{
    public function handle(string $provider, string $mode, ?string $topology = null): ProvisioningFlowDescriptorData
    {
        $provider = strtolower($provider);
        $matches = [];
        foreach (['emi.provider-provisioning-profiles', 'emi.provider-provisioning-gateways'] as $tag) {
            foreach (app()->tagged($tag) as $candidate) {
                if ($candidate instanceof ProviderProvisioningPresentation && $candidate->providerCode() === $provider) {
                    $matches[] = $candidate;
                }
            }
        }
        if (count($matches) > 1) {
            throw new RuntimeException("Multiple provisioning presentations are registered for provider [{$provider}].");
        }

        $presentation = $matches[0] ?? null;
        $topology ??= $presentation?->defaultProvisioningTopology() ?? 'manual';
        $data = $presentation?->provisioningPresentation($mode) ?? [
            'title' => 'Complete account setup',
            'steps' => ['profile', 'consent', 'ready'],
            'fields' => ['mobile', 'name'],
            'actions' => ['continue'],
        ];

        return ProvisioningFlowDescriptorData::from([
            ...$data,
            'provider' => $provider,
            'topology' => $topology,
            'mode' => $mode,
        ]);
    }
}
