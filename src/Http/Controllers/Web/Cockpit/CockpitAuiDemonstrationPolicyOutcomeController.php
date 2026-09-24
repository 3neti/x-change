<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Settlement\PrepareCampaignPolicyCompletion;
use LBHurtado\XChange\Actions\Settlement\RecordCampaignPolicyCompletionOutcome;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PolicyCompletionRequest;
use LBHurtado\XChange\Services\Settlement\GenerateAuiDemonstrationPolicyResponse;

final class CockpitAuiDemonstrationPolicyOutcomeController extends Controller
{
    public function __invoke(
        Request $request,
        string $campaign,
        PrepareCampaignPolicyCompletion $prepare,
        GenerateAuiDemonstrationPolicyResponse $respond,
        RecordCampaignPolicyCompletionOutcome $record,
    ): RedirectResponse {
        abort_unless((bool) config('x-change.leads.scenario_runner.demonstration_policy_response_enabled', ! app()->isProduction()), 404);

        $owner = $request->user();
        $leadCampaign = LeadCampaign::query()
            ->where('reference', $campaign)
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->firstOrFail();

        abort_unless(data_get($leadCampaign->settings, 'scenario_run.schema') === 'x-change.lead-campaign-lifecycle-run.v1', 404);

        $policyRequest = PolicyCompletionRequest::query()
            ->with('projection')
            ->whereHas('projection.issuance.coverage', fn ($query) => $query->where('endpoint_campaign_id', $leadCampaign->getKey()))
            ->firstOrFail();

        $response = $respond->handle($prepare->handle($policyRequest->projection));
        $record->handle($policyRequest, $owner, $response->outcome());

        return to_route('x-change.cockpit.campaigns.lead-scenario-runner.runs.show', [
            'campaign' => $leadCampaign->reference,
        ]);
    }
}
