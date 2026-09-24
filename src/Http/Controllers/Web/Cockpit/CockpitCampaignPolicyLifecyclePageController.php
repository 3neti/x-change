<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Data\Settlement\CampaignPolicyLifecycleData;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Services\Cockpit\CampaignPolicyLifecycleReadModel;

final class CockpitCampaignPolicyLifecyclePageController extends Controller
{
    public function __construct(private readonly CampaignPolicyLifecycleReadModel $lifecycles) {}

    public function __invoke(Request $request): Response
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model, 403);

        $reference = $request->query('campaign');
        abort_if($reference !== null && (! is_string($reference) || strlen($reference) > 100), 404);
        $campaign = $reference === null ? null : LeadCampaign::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())
            ->where('reference', $reference)->firstOrFail();

        return Inertia::render('x-change/cockpit/CampaignPolicyLifecycle', [
            'campaign_filter' => $campaign === null ? null : [
                'reference' => $campaign->reference,
                'title' => $campaign->title,
                'endpoint_slug' => $campaign->endpoint_slug,
            ],
            'lifecycles' => array_map(
                fn (CampaignPolicyLifecycleData $lifecycle): array => $lifecycle->toSafeArray(),
                $this->lifecycles->forOwner($owner, campaignReference: $reference),
            ),
        ]);
    }
}
