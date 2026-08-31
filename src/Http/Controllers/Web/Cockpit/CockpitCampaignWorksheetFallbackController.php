<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\PlanCampaignPayCodeFallbacks;
use LBHurtado\XChange\Actions\Campaigns\PlanCampaignPayoutRecoveryFallbacks;
use LBHurtado\XChange\Services\Campaigns\CampaignLifecycleJournal;

class CockpitCampaignWorksheetFallbackController extends Controller
{
    public function __construct(
        private readonly PlanCampaignPayCodeFallbacks $fallbacks,
        private readonly PlanCampaignPayoutRecoveryFallbacks $payoutRecoveries,
        private readonly CampaignLifecycleJournal $journal,
    ) {}

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

        if ($authorization->worksheet instanceof CampaignWorksheet
            && $authorization->worksheet->fulfillment_mode === 'direct_bank_transfer') {
            $result = $this->payoutRecoveries->handle($authorization, $owner, 100);
            $this->journal->recordAuthorization('campaign.recovery.opened', $authorization, $owner, [
                'planned_count' => $result['planned'],
                'queued_count' => $result['queued'],
                'skipped_count' => $result['skipped'],
            ]);

            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->with('campaign_notice', sprintf(
                    'Recovery SMS: %d opened, %d queued, %d already prepared.',
                    $result['planned'],
                    $result['queued'],
                    $result['skipped'],
                ));
        }

        $planned = $this->fallbacks->handle((string) $authorization->reference, 100);

        return to_route('x-change.cockpit.campaigns.show', $worksheet)
            ->with('campaign_notice', sprintf('%d failed transfers are planned for explicit Pay Code fallback.', $planned));
    }
}
