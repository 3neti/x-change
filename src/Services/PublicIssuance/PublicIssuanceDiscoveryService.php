<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Data\PublicIssuance\PublicIssuanceDiscoveryData;

final readonly class PublicIssuanceDiscoveryService
{
    public function describe(): PublicIssuanceDiscoveryData
    {
        $enabled = (bool) config('x-change.public_auto_generate.enabled', true);

        return new PublicIssuanceDiscoveryData(
            schema: 'x-change.public-issuance-discovery.v1',
            service: 'on_demand_pay_code_issuance',
            available: $enabled,
            issuer_model: 'commissioned_commercial_principal',
            registration_required: false,
            creates_order: false,
            canonical_url: route('x-change.public-auto-generate.show'),
            pricing_url: route('x-change.pricing.show'),
            api: [
                'estimate_url' => Route::has('x-change.api.public-issuance.estimate')
                    ? route('x-change.api.public-issuance.estimate')
                    : null,
                'handoff_url' => Route::has('x-change.api.public-issuance.handoff')
                    ? route('x-change.api.public-issuance.handoff')
                    : null,
            ],
            supported: [
                'voucher_type' => 'redeemable',
                'currencies' => (array) config('x-change.public_auto_generate.currencies', ['PHP']),
                'minimum_principal_minor' => (int) config('x-change.public_auto_generate.minimum_principal_minor', 100),
                'maximum_principal_minor' => (int) config('x-change.public_auto_generate.maximum_principal_minor', 100_000),
            ],
            funding: [
                'basis' => 'full_amount',
                'bank_transfer' => (bool) config('x-change.funding.requests.bank_transfer.enabled', true),
                'qr_ph' => (bool) config('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', false),
                'pay_code' => false,
            ],
            safety: [
                'read_only_contracts' => true,
                'payment_required_before_issuance' => true,
                'principal_selection' => false,
            ],
        );
    }
}
