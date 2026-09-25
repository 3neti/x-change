<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use LBHurtado\XCampaign\Models\EndpointCampaign;
use LBHurtado\XChange\Models\CampaignWorkflowPublication;

final readonly class CampaignWorkflowPublicationResolver
{
    public function __construct(private CampaignWorkflowPublicationSnapshot $snapshots) {}

    /** @return array<string, mixed>|null */
    public function forCampaignRevision(EndpointCampaign $campaign, string $revision): ?array
    {
        $query = CampaignWorkflowPublication::query()->where('endpoint_campaign_id', $campaign->getKey());
        $publication = (clone $query)->where('campaign_revision_id', $revision)->first();
        if ($publication === null) {
            if ($query->exists() || data_get($campaign->settings, 'workflow_publication') !== null) {
                throw new DomainException('The campaign workflow publication for this exact revision is unavailable.');
            }

            return null;
        }
        $snapshot = $publication->snapshot;
        if (! is_array($snapshot) || ! hash_equals($publication->snapshot_hash, $this->snapshots->hash($snapshot))) {
            throw new DomainException('The campaign workflow publication snapshot is invalid.');
        }

        return $this->snapshots->validate($snapshot) + ['publication_reference' => $publication->reference];
    }
}
