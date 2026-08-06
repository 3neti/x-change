<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Merchant\Contracts\MerchantProfileRepositoryContract;
use LBHurtado\Merchant\Models\Merchant;
use LBHurtado\Merchant\Services\MerchantDisplayNameRenderer;

final class FundingQrMerchantProfileReadModel
{
    public function __construct(
        private readonly MerchantProfileRepositoryContract $merchantProfiles,
        private readonly MerchantDisplayNameRenderer $displayNames,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forOwner(Model $owner): array
    {
        $merchant = $this->merchantProfiles->findForUser($owner);
        $ownerName = trim((string) $owner->getAttribute('name'));
        $name = $merchant?->name
            ?? ($ownerName !== ''
                ? $ownerName
                : (string) config('merchant.qr_profile.fallback_name', 'Account Holder'));
        $city = $merchant?->city
            ?? (string) config('merchant.qr_profile.default_city', 'Manila');
        $merchantCategoryCode = $merchant?->merchant_category_code
            ?? (string) config('merchant.qr_profile.default_category_code', '0000');
        $merchantNameTemplate = $merchant?->merchant_name_template
            ?? (string) config(
                'merchant.qr_profile.default_name_template',
                '{name} - {city}',
            );
        $applicationName = (string) config('x-change.product.name', 'X-Change');
        $profile = $merchant ?? new Merchant([
            'name' => $name,
            'city' => $city,
            'merchant_category_code' => $merchantCategoryCode,
            'merchant_name_template' => $merchantNameTemplate,
        ]);

        return [
            'name' => $name,
            'city' => $city,
            'merchant_category_code' => $merchantCategoryCode,
            'merchant_name_template' => $merchantNameTemplate,
            'rendered_label' => $this->displayNames->render($profile, $applicationName),
            'maximum_label_length' => max(
                1,
                (int) config('merchant.qr_profile.name_max_length', 25),
            ),
            'uppercase' => (bool) config('merchant.qr_profile.uppercase', false),
            'application_name' => $applicationName,
            'template_options' => [
                ['value' => '{name}', 'label' => 'Name'],
                ['value' => '{name} - {city}', 'label' => 'Name + City'],
                ['value' => '{app_name} - {name}', 'label' => 'x-change + Name'],
            ],
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
