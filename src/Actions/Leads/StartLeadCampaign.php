<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Leads;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use LBHurtado\XCampaign\Contracts\EndpointCampaignRepository;
use LBHurtado\XCampaign\Services\EndpointCampaignAvailability;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Services\Leads\LeadCampaignTemplateVersionId;

final readonly class StartLeadCampaign
{
    public function __construct(
        private GeneratePayCode $generatePayCode,
        private EndpointCampaignRepository $endpoints,
        private EndpointCampaignAvailability $availability,
        private LeadCampaignTemplateVersionId $templateVersions,
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
        $templateVersionId = $campaign->active_template_version_id
            ?: $this->templateVersions->forTemplate($template);

        data_set($payload, 'metadata.issuer_id', (string) $owner->getKey());
        data_set($payload, 'metadata.campaign.planning_key', $campaign->endpoint_slug);
        data_set($payload, 'metadata.campaign.campaign_id', $campaign->reference);
        data_set($payload, 'metadata.campaign.source', 'lead_campaign');
        data_set($payload, 'metadata.campaign.template_reference', $template->reference);
        data_set($payload, 'metadata.campaign.template_version_id', $templateVersionId);
        $leadCampaignMetadata = (array) data_get($payload, 'metadata.custom.lead_campaign', []);
        $settings = (array) $campaign->settings;

        data_set($payload, 'metadata.custom.lead_campaign', [
            ...$leadCampaignMetadata,
            'schema' => 'x-change.lead-campaign-attribution.v1',
            'kind' => data_get($settings, 'usage_key', data_get($settings, 'kind', 'lead')),
            'usage_label' => data_get($settings, 'usage_label'),
            'capabilities' => array_values((array) data_get($settings, 'capabilities', [])),
            'campaign_reference' => $campaign->reference,
            'campaign_title' => $campaign->title,
            'merchant_display_name' => $campaign->merchant_display_name,
            'merchant_slug' => $campaign->merchant_slug,
            'endpoint_slug' => $campaign->endpoint_slug,
            'template_reference' => $template->reference,
            'template_version_id' => $templateVersionId,
            'entry_point' => data_get($settings, 'entry_point', 'public_qr_link'),
            'person_type' => data_get($settings, 'person_type', 'prospect'),
            'pay_code_generation' => data_get($settings, 'pay_code_generation', 'on_scan'),
        ]);
        data_set($payload, '_meta.source', 'lead_campaign.public_endpoint');
        data_set($payload, '_meta.lead_campaign_reference', $campaign->reference);

        $result = $this->generatePayCode->handle($payload);

        $this->endpoints->recordSuccessfulStart($campaign);

        return $result;
    }

    public function ensureStartable(LeadCampaign $campaign): void
    {
        $this->availability->ensureStartable($campaign);

        if (! Arr::has((array) $campaign->payCodeTemplate?->instructions_ciphertext, 'cash')) {
            throw ValidationException::withMessages([
                'template' => 'The Lead Campaign template is missing Pay Code cash instructions.',
            ]);
        }
    }
}
