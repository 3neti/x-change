<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Leads;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Leads\StartLeadCampaign;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;

final class LeadCampaignEndpointController extends Controller
{
    public function __invoke(
        string $merchant_slug,
        string $endpoint_slug,
        StartLeadCampaign $startLeadCampaign,
        Request $request,
        CampaignDisplaySessions $displays,
    ): RedirectResponse {
        $campaign = LeadCampaign::query()
            ->where('merchant_slug', $merchant_slug)
            ->where('endpoint_slug', $endpoint_slug)
            ->firstOrFail();

        try {
            if ($request->has('display')) {
                $token = $request->query('display');
                abort_unless(is_string($token), 404);
                $code = $displays->pair($campaign, $token, $request);

                return redirect()->route('x-change.claim.show', ['code' => $code]);
            }
            $result = $startLeadCampaign->handle($campaign);
        } catch (ValidationException) {
            abort(404);
        }

        return redirect()->route('x-change.claim.show', [
            'code' => $result->code,
        ]);
    }
}
