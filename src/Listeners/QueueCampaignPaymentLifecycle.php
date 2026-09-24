<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use LBHurtado\XChange\Events\CampaignPaymentRecognized;
use LBHurtado\XChange\Jobs\Campaigns\AdvanceCampaignPaymentLifecycleJob;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;

final class QueueCampaignPaymentLifecycle
{
    public function handle(CampaignPaymentRecognized $event): void
    {
        $recognition = CampaignPaymentRecognition::query()
            ->where('reference', $event->recognition->recognitionReference)
            ->whereNotNull('campaign_payment_qr_binding_id')
            ->first();

        if ($recognition !== null) {
            AdvanceCampaignPaymentLifecycleJob::dispatch($recognition->reference)->afterCommit();
        }
    }
}
