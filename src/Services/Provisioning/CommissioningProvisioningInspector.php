<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XProvisioning\Enums\ProvisioningSeatStatus;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;
use LBHurtado\XProvisioning\Models\ProvisioningSeat;

final readonly class CommissioningProvisioningInspector
{
    /** @return array<string, mixed> */
    public function inspect(): array
    {
        if (! Schema::hasTable('x_provisioning_seats')) {
            return [
                'storage_ready' => false,
                'configured_count' => count((array) config('x-change.provisioning.commissioning_seats', [])),
                'required_count' => 0,
                'vacant_count' => 0,
                'offered_count' => 0,
                'activated_count' => 0,
                'pending_request_count' => 0,
                'revoked_count' => 0,
                'superseded_count' => 0,
                'delivery_queue_ready' => false,
                'partner_api_storage_ready' => false,
                'production_mandate_pending_count' => 0,
                'production_client_count' => 0,
            ];
        }

        return [
            'storage_ready' => true,
            'configured_count' => count((array) config('x-change.provisioning.commissioning_seats', [])),
            'required_count' => ProvisioningSeat::query()->where('required', true)->count(),
            'vacant_count' => ProvisioningSeat::query()->where('status', ProvisioningSeatStatus::Vacant->value)->count(),
            'offered_count' => ProvisioningSeat::query()->where('status', ProvisioningSeatStatus::Offered->value)->count(),
            'activated_count' => ProvisioningSeat::query()->where('status', ProvisioningSeatStatus::Activated->value)->count(),
            'pending_request_count' => ProvisioningRequest::query()
                ->whereNotIn('status', ['activated', 'rejected', 'expired', 'withdrawn', 'revoked'])
                ->count(),
            'revoked_count' => ProvisioningOffer::query()->where('status', 'revoked')->count(),
            'superseded_count' => ProvisioningOffer::query()->where('status', 'superseded')->count(),
            'delivery_queue_ready' => config('x-change.redemption.feedback.queue') === 'x-change-feedback',
            'partner_api_storage_ready' => Schema::hasTable('x_change_partner_api_production_mandates'),
            'production_mandate_pending_count' => Schema::hasTable('x_change_partner_api_production_mandates')
                ? PartnerApiProductionMandate::query()->whereIn('status', ['awaiting_approval', 'approved'])->count()
                : 0,
            'production_client_count' => Schema::hasTable('x_change_partner_api_clients')
                ? PartnerApiClient::query()->where('environment', 'production')->count()
                : 0,
        ];
    }
}
