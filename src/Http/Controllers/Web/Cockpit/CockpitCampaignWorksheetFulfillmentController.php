<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetPayCodes;
use LBHurtado\XChange\Actions\Campaigns\PlanCampaignWorksheetFulfillment;
use RuntimeException;

class CockpitCampaignWorksheetFulfillmentController extends Controller
{
    public function __construct(private readonly PlanCampaignWorksheetFulfillment $planner, private readonly IssueCampaignWorksheetPayCodes $issuer) {}

    public function store(Request $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();
        $authorization = CampaignWorksheetAuthorization::query()->whereHas('worksheet', fn ($query) => $query->where('reference', $worksheet)->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getAuthIdentifier()))->latest('id')->first();
        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            abort(404);
        }

        try {
            $this->planner->handle((string) $authorization->reference);
            $issued = $this->issuer->handle((string) $authorization->reference, $owner, 100);
        } catch (RuntimeException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', $exception->getMessage());
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', sprintf('%d beneficiary Pay Codes issued. No messages or transfers were sent.', $issued));
    }
}
