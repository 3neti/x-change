<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Composer\InstalledVersions;
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

    /** @return list<array{name: string, version: string}> */
    public function versionsForRequest(Request $request): array
    {
        if (! app()->environment(['local', 'testing'])) {
            return [];
        }
        $profile = $this->forRequest($request);
        $enabled = (bool) data_get($profile, 'diagnostics.show_versions', false)
            || collect($profile['screens'] ?? [])->contains(fn (array $screen): bool => (bool) data_get($screen, 'diagnostics.show_versions', false));
        if (! $enabled) {
            return [];
        }
        $versions = [];
        foreach (['3neti/x-change', '3neti/form-flow', '3neti/form-handler-otp', '3neti/form-handler-kyc', '3neti/form-handler-location', '3neti/form-handler-selfie', '3neti/form-handler-signature'] as $package) {
            if (InstalledVersions::isInstalled($package)) {
                $version = InstalledVersions::getPrettyVersion($package);
                if (is_string($version) && $version !== '') {
                    $versions[] = ['name' => $package, 'version' => $version];
                }
            }
        }

        return $versions;
    }

    /** @param array<string, mixed> $state */
    public function remember(string $code, array $state): void
    {
        session()->put('claim_ui_profiles.'.hash('sha256', $code),
            data_get($state, 'instructions.metadata.form_flow_presentation') ?? $this->current());
    }
}
