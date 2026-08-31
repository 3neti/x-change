<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Actions\Campaigns\ExecuteCampaignWorksheetDirectTransfers;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetPayCodes;
use LBHurtado\XChange\Services\Campaigns\CampaignLifecycleJournal;
use RuntimeException;

class CockpitCampaignWorksheetBankTransferDispatchController extends Controller
{
    public function __construct(
        private readonly IssueCampaignWorksheetPayCodes $payCodes,
        private readonly ExecuteCampaignWorksheetDirectTransfers $directTransfers,
        private readonly CampaignLifecycleJournal $journal,
    ) {}

    public function store(Request $request, string $worksheet): RedirectResponse
    {
        $owner = $request->user();
        $authorization = CampaignWorksheetAuthorization::query()->whereHas('worksheet', fn ($query) => $query->where('reference', $worksheet)->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getAuthIdentifier())->where('fulfillment_mode', 'direct_bank_transfer'))->latest('id')->first();
        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            abort(404);
        }

        if (! (bool) config('x-change.campaigns.netbank_dispatch.enabled', false)) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->with('campaign_notice', 'NetBank live transfer dispatch is not enabled for this environment.');
        }

        if ($request->input('confirm_live_transfer') !== 'I APPROVE LIVE BANK TRANSFERS') {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)
                ->withErrors(['confirm_live_transfer' => 'Type I APPROVE LIVE BANK TRANSFERS before executing a live payroll batch.']);
        }

        try {
            $this->enableCockpitScenarioRunner($authorization);

            $issued = $this->payCodes->handle((string) $authorization->reference, $owner, 100);
            $authorization->refresh()->load(['worksheet', 'fulfillments.row']);
            $result = $this->directTransfers->handle($authorization, $owner, 100);
            $this->journal->recordAuthorization('campaign.direct_transfer.executed', $authorization, $owner, [
                'issued_count' => $issued,
                'completed_count' => $result['completed'],
                'indeterminate_count' => $result['indeterminate'],
                'skipped_count' => $result['skipped'],
            ]);
        } catch (RuntimeException $exception) {
            return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', $exception->getMessage());
        }

        return to_route('x-change.cockpit.campaigns.show', $worksheet)->with('campaign_notice', sprintf(
            'Live payroll runner: %d Pay Codes issued, %d paid, %d require review, %d skipped.',
            $issued,
            $result['completed'],
            $result['indeterminate'],
            $result['skipped'],
        ));
    }

    private function enableCockpitScenarioRunner(CampaignWorksheetAuthorization $authorization): void
    {
        $worksheet = $authorization->worksheet;
        if (! $worksheet instanceof CampaignWorksheet) {
            throw new RuntimeException('Campaign worksheet authorization is missing its worksheet.');
        }

        $metadata = (array) $worksheet->metadata;
        data_set($metadata, 'lifecycle.schema', 'x-change.campaign-browser-runner.v1');
        data_set($metadata, 'lifecycle.scenario', 'campaign_payroll_direct_transfer');
        data_set($metadata, 'lifecycle.automatic_fulfillment', true);
        data_set($metadata, 'lifecycle.live_provider_authorized', true);
        data_set($metadata, 'lifecycle.live_transfer_confirmed', true);
        data_set($metadata, 'lifecycle.failure_disposition', 'same_pay_code_sms_recovery');
        data_set($metadata, 'lifecycle.browser_runner_enabled_at', now()->toIso8601String());

        $worksheet->forceFill(['metadata' => $metadata])->save();
    }
}
