<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CampaignPaymentMonitoringMode;
use LBHurtado\XChange\Models\CampaignPaymentMonitoringControl;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Services\Campaigns\CampaignPaymentMonitoringEligibility;

final readonly class SetCampaignPaymentMonitoring
{
    public function __construct(private CampaignPaymentMonitoringEligibility $eligibility) {}

    public function handle(
        CampaignPaymentQrBinding $binding,
        CampaignPaymentMonitoringMode $mode,
        int $expectedGeneration,
        string $reason,
        string $actorType,
        string $actorId,
    ): CampaignPaymentMonitoringControl {
        return DB::transaction(function () use ($binding, $mode, $expectedGeneration, $reason, $actorType, $actorId): CampaignPaymentMonitoringControl {
            $lockedBinding = CampaignPaymentQrBinding::query()
                ->whereKey($binding->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $control = CampaignPaymentMonitoringControl::query()
                ->where('campaign_payment_qr_binding_id', $lockedBinding->getKey())
                ->lockForUpdate()
                ->first();
            $generation = $control?->generation ?? 0;

            if ($generation !== $expectedGeneration) {
                throw new \LogicException('Campaign payment monitoring generation is stale.');
            }

            if ($control?->mode === $mode) {
                return $control;
            }

            if ($mode === CampaignPaymentMonitoringMode::Live) {
                $ineligibleReason = $this->eligibility->reason($lockedBinding);

                if ($ineligibleReason !== null) {
                    throw new \DomainException("Campaign payment monitoring is unavailable: {$ineligibleReason}.");
                }
            }

            $control ??= new CampaignPaymentMonitoringControl([
                'campaign_payment_qr_binding_id' => $lockedBinding->getKey(),
            ]);
            $control->forceFill([
                'mode' => $mode,
                'generation' => $generation + 1,
                'transition_reason' => trim($reason),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'transitioned_at' => now(),
            ])->save();

            return $control->refresh();
        });
    }
}
