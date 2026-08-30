<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignPayCodeDeliveries;
use LBHurtado\XChange\Actions\Campaigns\ExecuteCampaignWorksheetDirectTransfers;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetPayCodes;
use LBHurtado\XChange\Models\CampaignBatchFulfillmentOutbox;
use RuntimeException;
use Throwable;

final readonly class CampaignBatchFulfillmentOutboxProcessor
{
    public function __construct(
        private IssueCampaignWorksheetPayCodes $payCodes,
        private DispatchCampaignPayCodeDeliveries $deliveries,
        private ExecuteCampaignWorksheetDirectTransfers $directTransfers,
    ) {}

    public function process(CampaignBatchFulfillmentOutbox $outbox): string
    {
        $claimed = DB::transaction(function () use ($outbox): ?CampaignBatchFulfillmentOutbox {
            $locked = CampaignBatchFulfillmentOutbox::query()->lockForUpdate()->find($outbox->getKey());
            if (! $locked instanceof CampaignBatchFulfillmentOutbox || $locked->status !== 'pending') {
                return null;
            }

            $locked->forceFill([
                'status' => 'processing',
                'attempts' => $locked->attempts + 1,
                'locked_at' => now(),
            ])->saveQuietly();

            return $locked;
        }, attempts: 5);
        if (! $claimed instanceof CampaignBatchFulfillmentOutbox) {
            return 'skipped';
        }

        try {
            $authorization = CampaignWorksheetAuthorization::query()
                ->with(['worksheet.owner', 'fulfillments.row'])
                ->findOrFail($claimed->campaign_worksheet_authorization_id);
            $worksheet = $authorization->worksheet;
            $owner = $worksheet?->owner;
            if ($authorization->status !== 'authorized'
                || $worksheet === null
                || ! $owner instanceof Model
                || data_get($worksheet->metadata, 'lifecycle.automatic_fulfillment') !== true) {
                throw new RuntimeException('Campaign batch fulfillment authority is unavailable.');
            }

            $this->payCodes->handle((string) $authorization->reference, $owner, 500);
            $authorization->refresh()->load('fulfillments.row');

            if ($worksheet->fulfillment_mode === 'direct_bank_transfer') {
                if (data_get($worksheet->metadata, 'lifecycle.live_provider_authorized') !== true
                    || data_get($worksheet->metadata, 'lifecycle.live_transfer_confirmed') !== true) {
                    throw new RuntimeException('Campaign batch live-transfer authority is unavailable.');
                }
                $result = $this->directTransfers->handle($authorization, $owner, 500);
                if ($result['indeterminate'] > 0) {
                    return $this->finish($claimed, 'indeterminate');
                }
            } else {
                if (data_get($worksheet->metadata, 'lifecycle.live_feedback_authorized') !== true) {
                    throw new RuntimeException('Campaign batch live-feedback authority is unavailable.');
                }
                $this->deliveries->handle($authorization, $owner, 'sms', 500);
            }

            return $this->finish($claimed, 'completed');
        } catch (Throwable $exception) {
            DB::transaction(function () use ($claimed, $exception): void {
                CampaignBatchFulfillmentOutbox::query()
                    ->lockForUpdate()
                    ->findOrFail($claimed->getKey())
                    ->forceFill([
                        'status' => 'failed',
                        'last_error_class' => $exception::class,
                    ])
                    ->saveQuietly();
            }, attempts: 5);

            throw $exception;
        }
    }

    private function finish(CampaignBatchFulfillmentOutbox $outbox, string $status): string
    {
        DB::transaction(function () use ($outbox, $status): void {
            CampaignBatchFulfillmentOutbox::query()
                ->lockForUpdate()
                ->findOrFail($outbox->getKey())
                ->forceFill([
                    'status' => $status,
                    'completed_at' => now(),
                    'last_error_class' => null,
                ])
                ->saveQuietly();
        }, attempts: 5);

        return $status;
    }
}
