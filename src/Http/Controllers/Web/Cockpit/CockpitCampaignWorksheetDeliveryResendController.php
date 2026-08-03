<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignPayCodeDeliveries;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use RuntimeException;

class CockpitCampaignWorksheetDeliveryResendController extends Controller
{
    public function __construct(private readonly DispatchCampaignPayCodeDeliveries $deliveries) {}

    public function store(Request $request, string $worksheet, string $attempt): RedirectResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model, 403);

        $deliveryAttempt = CampaignDeliveryAttempt::query()
            ->where('reference', $attempt)
            ->whereHas('authorization.worksheet', fn ($query) => $query
                ->where('reference', $worksheet)
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', (string) $owner->getAuthIdentifier()))
            ->firstOrFail();
        abort_unless((bool) config("x-change.campaigns.delivery.{$deliveryAttempt->channel}.enabled", false), 403);

        try {
            $outcome = $this->deliveries->resendCompleted($deliveryAttempt, $owner);
        } catch (RuntimeException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->with('campaign_notice', $exception->getMessage());
        }

        $notice = $outcome === 'already_queued'
            ? sprintf('%s delivery was already resent.', strtoupper($deliveryAttempt->channel))
            : sprintf('%s delivery resend queued.', strtoupper($deliveryAttempt->channel));

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', $notice);
    }
}
