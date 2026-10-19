<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Http\Request;
use LBHurtado\FormFlowManager\Services\FormFlowPresentationResolver;
use Symfony\Component\Yaml\Yaml;

final class ClaimPresentationProfile
{
    public function __construct(private readonly FormFlowPresentationResolver $presentations) {}

    /** @return array<string, mixed> */
    public function current(): array
    {
        $path = config_path('form-flow-drivers/voucher-redemption.yaml');
        $driver = is_file($path) ? Yaml::parseFile($path) : [];

        return $this->presentations->resolve($driver['presentation'] ?? ['profile' => 'guided-claim-v1']);
    }

    /** @return array<string, mixed> */
    public function forRequest(Request $request): array
    {
        $code = $request->route('code');
        $stored = is_string($code) && $request->hasSession()
            ? $request->session()->get('claim_ui_profiles.'.hash('sha256', $code))
            : null;

        return is_array($stored) ? $stored : $this->current();
    }

    /** @param array<string, mixed> $state */
    public function remember(string $code, array $state): void
    {
        session()->put('claim_ui_profiles.'.hash('sha256', $code),
            data_get($state, 'instructions.metadata.form_flow_presentation') ?? $this->current());
    }
}
