<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final class DatabaseCommercialOfferingResolver implements CommercialOfferingResolverContract
{
    public function __construct(
        private readonly BootstrapCommercialOfferingFactory $bootstrap,
    ) {}

    public function resolve(string $profile): CommercialOfferingData
    {
        if (! (bool) config('x-change.commercial.offerings.use_published', false)
            || ! Schema::hasTable('x_change_commercial_offerings')) {
            return $this->bootstrap->make($profile);
        }

        $offering = CommercialOffering::query()
            ->where('profile', $profile)
            ->where('status', CommercialOfferingStatus::Published->value)
            ->where('effective_at', '<=', now())
            ->latest('effective_at')
            ->latest('version')
            ->first();

        return $offering?->offering() ?? $this->bootstrap->make($profile);
    }
}
