<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Stamp;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Support\Claim\PayoutDestinationIconCatalog;

final readonly class StampProviderIdentity
{
    public function __construct(
        private ProviderRuntimeSettingsResolverContract $settings,
        private PayoutDestinationIconCatalog $icons,
    ) {}

    /** @return array{code: string, name: string, logo: ?string}|null */
    public function resolve(?string $provider = null): ?array
    {
        $code = strtolower(trim($provider ?? $this->settings->provider()));
        if ($code === '' || $code === 'manual' || preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $code) !== 1) {
            return null;
        }
        $entry = $this->icons->forCode('PROVIDER:'.$code);
        if ($entry === null && ! array_key_exists($code, (array) config('x-change.provider_runtime.providers', []))) {
            return null;
        }
        $logo = null;
        $file = basename($entry['icon_asset'] ?? '');
        $path = dirname(__DIR__, 3).'/resources/assets/images/payout-destinations/'.$file;
        if ($file !== '' && is_file($path)) {
            $logo = file_get_contents($path) ?: null;
        }

        return ['code' => $code, 'name' => $entry['display_name'] ?? ucfirst($code), 'logo' => $logo];
    }

    /** @return array{code: string, name: string, logo: ?string}|null */
    public function forVoucher(Voucher $voucher): ?array
    {
        $code = data_get($voucher->metadata, 'treasury.pay_code_reservation.provider')
            ?? data_get($voucher->metadata, 'instructions.metadata.custom.stamp_provider');

        return is_string($code) ? $this->resolve($code) : null;
    }
}
