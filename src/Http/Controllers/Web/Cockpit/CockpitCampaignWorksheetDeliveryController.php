<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignPayCodeDeliveries;

class CockpitCampaignWorksheetDeliveryController extends Controller
{
    public function __construct(private readonly DispatchCampaignPayCodeDeliveries $deliveries) {}

    public function store(Request $request, string $worksheet, string $channel): RedirectResponse
    {
        abort_unless(in_array($channel, ['sms', 'email'], true), 404);
        abort_unless((bool) config("x-change.campaigns.delivery.{$channel}.enabled", false), 403);
        $owner = $request->user();
        abort_unless($owner instanceof Model, 403);

        $authorization = CampaignWorksheetAuthorization::query()
            ->where('status', 'authorized')
            ->whereHas('worksheet', fn ($query) => $query
                ->where('reference', $worksheet)
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getAuthIdentifier()))
            ->latest('id')
            ->firstOrFail();

        $result = $this->deliveries->handle(
            $authorization,
            $owner,
            $channel,
            (int) config('x-change.campaigns.delivery.batch_size', 100),
        );

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', sprintf(
                '%s delivery: %d queued, %d blocked, %d already attempted.',
                strtoupper($channel),
                $result['queued'],
                $result['blocked'],
                $result['skipped'],
            ));
    }
}
