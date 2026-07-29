<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\PlanCampaignPayCodeFallbacks;

class CockpitCampaignWorksheetFallbackController extends Controller
{
    public function __construct(private readonly PlanCampaignPayCodeFallbacks $fallbacks) {}

    public function store(Request $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();
        $authorization = CampaignWorksheetAuthorization::query()
            ->whereHas('worksheet', fn ($query) => $query
                ->where('reference', $worksheet)
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getAuthIdentifier()))
            ->where('status', 'authorized')
            ->latest('id')
            ->firstOrFail();

        $planned = $this->fallbacks->handle((string) $authorization->reference, 100);

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', sprintf('%d failed transfers are planned for explicit Pay Code fallback.', $planned));
    }
}
