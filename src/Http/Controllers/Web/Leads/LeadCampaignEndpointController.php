<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Leads;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Leads\StartLeadCampaign;
use LBHurtado\XChange\Models\LeadCampaign;

final class LeadCampaignEndpointController extends Controller
{
    public function __invoke(
        string $merchant_slug,
        string $endpoint_slug,
        StartLeadCampaign $startLeadCampaign,
    ): RedirectResponse {
        $campaign = LeadCampaign::query()
            ->where('merchant_slug', $merchant_slug)
            ->where('endpoint_slug', $endpoint_slug)
            ->firstOrFail();

        try {
            $result = $startLeadCampaign->handle($campaign);
        } catch (ValidationException) {
            abort(404);
        }

        return redirect()->route('x-change.claim.show', [
            'code' => $result->code,
        ]);
    }
}
