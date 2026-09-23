<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use LBHurtado\XCampaign\Models\EndpointCampaign;
use LBHurtado\XChange\Models\CampaignPaymentEvidenceQuarantine;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;

final class CampaignPaymentEvidenceAttentionReadModel
{
    /**
     * @param  EloquentCollection<int, EndpointCampaign>  $campaigns
     * @return array<int|string, array{count: int, status: string, label: string, latest_reason: string|null, latest_opened_at: string|null}>
     */
    public function forCampaigns(EloquentCollection $campaigns): array
    {
        $campaignIds = $campaigns->modelKeys();

        if ($campaignIds === []) {
            return [];
        }

        $bindings = CampaignPaymentQrBinding::query()
            ->whereIn('endpoint_campaign_id', $campaignIds)
            ->get(['id', 'endpoint_campaign_id']);
        $bindingCampaignIds = $bindings->pluck('endpoint_campaign_id', 'id');

        if ($bindingCampaignIds->isEmpty()) {
            return [];
        }

        return CampaignPaymentEvidenceQuarantine::query()
            ->whereIn('campaign_payment_qr_binding_id', $bindingCampaignIds->keys())
            ->latest('opened_at')
            ->get()
            ->groupBy(fn (CampaignPaymentEvidenceQuarantine $quarantine): int|string => $bindingCampaignIds->get($quarantine->campaign_payment_qr_binding_id))
            ->map(function (Collection $quarantines): array {
                /** @var CampaignPaymentEvidenceQuarantine $latest */
                $latest = $quarantines->first();

                return [
                    'count' => $quarantines->count(),
                    'status' => 'needs_attention',
                    'label' => 'Needs attention',
                    'latest_reason' => $latest->reason_code,
                    'latest_opened_at' => $latest->opened_at?->toIso8601String(),
                ];
            })
            ->all();
    }
}
