<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Merchant\Contracts\MerchantProfileRepositoryContract;
use LBHurtado\Merchant\Models\Merchant;

final class FundingQrMerchantProfileReadModel
{
    public function __construct(
        private readonly MerchantProfileRepositoryContract $merchantProfiles,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forOwner(Model $owner): array
    {
        $merchant = $this->merchantProfiles->findForUser($owner);
        $ownerName = trim((string) $owner->getAttribute('name'));

        return [
            'name' => $merchant?->name
                ?? ($ownerName !== ''
                    ? $ownerName
                    : (string) config('merchant.qr_profile.fallback_name', 'Account Holder')),
            'city' => $merchant?->city
                ?? (string) config('merchant.qr_profile.default_city', 'Manila'),
            'merchant_category_code' => $merchant?->merchant_category_code
                ?? (string) config('merchant.qr_profile.default_category_code', '0000'),
            'merchant_name_template' => $merchant?->merchant_name_template
                ?? (string) config(
                    'merchant.qr_profile.default_name_template',
                    '{name} - {city}',
                ),
            'category_options' => collect(Merchant::getCategoryCodes())
                ->map(fn (string $label, string|int $code): array => [
                    'code' => (string) $code,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'presentation_only' => true,
            'controls_routing' => false,
            'controls_settlement' => false,
        ];
    }
}
