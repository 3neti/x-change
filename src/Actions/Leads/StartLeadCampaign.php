<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Leads;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Models\LeadCampaign;

final readonly class StartLeadCampaign
{
    public function __construct(
        private GeneratePayCode $generatePayCode,
    ) {}

    public function handle(LeadCampaign $campaign): GeneratePayCodeResultData
    {
        $this->ensureStartable($campaign);

        $template = $campaign->payCodeTemplate()->firstOrFail();
        $owner = $campaign->owner()->first();

        if ($owner === null) {
            throw ValidationException::withMessages([
                'campaign' => 'This Lead Campaign no longer has an owner account.',
            ]);
        }

        $payload = (array) $template->instructions_ciphertext;

        data_set($payload, 'metadata.issuer_id', (string) $owner->getKey());
        data_set($payload, 'metadata.campaign.planning_key', $campaign->endpoint_slug);
        data_set($payload, 'metadata.campaign.campaign_id', $campaign->reference);
        data_set($payload, 'metadata.campaign.source', 'lead_campaign');
        $leadCampaignMetadata = (array) data_get($payload, 'metadata.custom.lead_campaign', []);

        data_set($payload, 'metadata.custom.lead_campaign', [
            ...$leadCampaignMetadata,
            'schema' => 'x-change.lead-campaign-attribution.v1',
            'kind' => 'lead',
            'campaign_reference' => $campaign->reference,
            'campaign_title' => $campaign->title,
            'merchant_display_name' => $campaign->merchant_display_name,
            'merchant_slug' => $campaign->merchant_slug,
            'endpoint_slug' => $campaign->endpoint_slug,
            'template_reference' => $template->reference,
            'entry_point' => 'public_qr_link',
            'person_type' => 'prospect',
            'pay_code_generation' => 'on_scan',
        ]);
        data_set($payload, '_meta.source', 'lead_campaign.public_endpoint');
        data_set($payload, '_meta.lead_campaign_reference', $campaign->reference);

        $result = $this->generatePayCode->handle($payload);

        $campaign->newQuery()
            ->whereKey($campaign->getKey())
            ->update([
                'usage_count' => DB::raw('usage_count + 1'),
                'last_started_at' => now(),
                'updated_at' => now(),
            ]);

        return $result;
    }

    private function ensureStartable(LeadCampaign $campaign): void
    {
        if ($campaign->status !== 'active') {
            throw ValidationException::withMessages([
                'campaign' => 'This Lead Campaign is not accepting new prospects.',
            ]);
        }

        if ($campaign->expires_at !== null && $campaign->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'campaign' => 'This Lead Campaign has expired.',
            ]);
        }

        if (
            $campaign->starts_limit !== null
            && $campaign->usage_count >= $campaign->starts_limit
        ) {
            throw ValidationException::withMessages([
                'campaign' => 'This Lead Campaign has reached its prospect limit.',
            ]);
        }

        if (! Arr::has((array) $campaign->payCodeTemplate?->instructions_ciphertext, 'cash')) {
            throw ValidationException::withMessages([
                'template' => 'The Lead Campaign template is missing Pay Code cash instructions.',
            ]);
        }
    }
}
