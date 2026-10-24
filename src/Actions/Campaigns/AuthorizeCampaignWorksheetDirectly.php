<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Models\CampaignBatchFulfillmentOutbox;
use LBHurtado\XChange\Services\Campaigns\CampaignLifecycleJournal;
use RuntimeException;

final class AuthorizeCampaignWorksheetDirectly
{
    public function __construct(
        private readonly PlanCampaignWorksheetFulfillment $fulfillmentPlanner,
        private readonly CampaignLifecycleJournal $journal,
    ) {}

    public function handle(string $worksheetReference, Model $owner): CampaignWorksheetAuthorization
    {
        $authorization = DB::transaction(function () use ($worksheetReference, $owner): CampaignWorksheetAuthorization {
            $worksheet = CampaignWorksheet::query()
                ->with('rows')
                ->where('reference', trim($worksheetReference))
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getKey())
                ->lockForUpdate()
                ->first();

            if (! $worksheet instanceof CampaignWorksheet
                || $worksheet->rows_hash === null
                || $worksheet->manifest_hash === null
                || $worksheet->instruction_blueprint_hash === null) {
                throw new RuntimeException('A frozen campaign worksheet is required for direct authorization.');
            }

            $authorization = $worksheet->authorizations()
                ->where('manifest_hash', $worksheet->manifest_hash)
                ->lockForUpdate()
                ->first();

            if ($authorization instanceof CampaignWorksheetAuthorization) {
                if ($worksheet->status !== 'authorized'
                    || $authorization->status !== 'authorized'
                    || $authorization->approval_pay_code !== null
                    || $authorization->approved_by_type !== $owner->getMorphClass()
                    || (string) $authorization->approved_by_id !== (string) $owner->getKey()) {
                    throw new RuntimeException('This campaign worksheet already has another authorization path.');
                }

                $this->fulfillmentPlanner->handle((string) $authorization->reference);

                return $authorization;
            }

            if ($worksheet->status !== 'awaiting_authorization') {
                throw new RuntimeException('A frozen campaign worksheet is required for direct authorization.');
            }

            $authorization = $worksheet->authorizations()->create([
                'manifest_hash' => $worksheet->manifest_hash,
                'rows_hash' => $worksheet->rows_hash,
                'instruction_blueprint_ciphertext' => $worksheet->instruction_blueprint_ciphertext ?? [],
                'instruction_blueprint_hash' => $worksheet->instruction_blueprint_hash,
                'instruction_blueprint_schema' => $worksheet->instruction_blueprint_schema,
                'beneficiary_count' => $worksheet->rows->count(),
                'principal_minor' => $worksheet->rows->sum('amount_minor'),
                'currency' => $worksheet->currency,
                'status' => 'authorized',
                'approved_by_type' => $owner->getMorphClass(),
                'approved_by_id' => (string) $owner->getKey(),
                'approved_at' => now(),
            ]);

            $worksheet->forceFill(['status' => 'authorized'])->save();
            $this->fulfillmentPlanner->handle((string) $authorization->reference);

            if (data_get($worksheet->metadata, 'lifecycle.automatic_fulfillment') === true) {
                $mayFulfill = $worksheet->fulfillment_mode === 'direct_bank_transfer'
                    ? data_get($worksheet->metadata, 'lifecycle.live_provider_authorized') === true
                        && data_get($worksheet->metadata, 'lifecycle.live_transfer_confirmed') === true
                    : data_get($worksheet->metadata, 'lifecycle.live_feedback_authorized') === true;
                if ($mayFulfill) {
                    CampaignBatchFulfillmentOutbox::query()->firstOrCreate(
                        ['campaign_worksheet_authorization_id' => $authorization->getKey()],
                        ['status' => 'pending', 'attempts' => 0, 'available_at' => now()],
                    );
                }
            }

            return $authorization->refresh();
        });

        $this->journal->recordAuthorization('campaign.owner.authorized', $authorization, $owner);

        return $authorization;
    }
}
