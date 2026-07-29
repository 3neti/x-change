<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignBankTransferBatch;
use LBHurtado\XChange\Actions\Campaigns\PlanCampaignDirectBankTransferFallbacks;
use RuntimeException;

class CockpitCampaignWorksheetBankTransferDispatchController extends Controller
{
    public function __construct(private readonly PlanCampaignDirectBankTransferFallbacks $planner, private readonly DispatchCampaignBankTransferBatch $dispatcher) {}

    public function store(Request $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();
        $authorization = CampaignWorksheetAuthorization::query()->whereHas('worksheet', fn ($query) => $query->where('reference', $worksheet)->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getAuthIdentifier())->where('fulfillment_mode', 'direct_bank_transfer'))->latest('id')->first();
        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            abort(404);
        }

        try {
            $this->planner->handle((string) $authorization->reference, 100);
            $result = $this->dispatcher->handle((string) $authorization->reference, 100);
        } catch (RuntimeException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', $exception->getMessage());
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', sprintf('NetBank dispatch: %d dispatched, %d blocked, %d failed.', $result['dispatched'], $result['blocked'], $result['failed']));
    }
}
