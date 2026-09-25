<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Cockpit\PublishCampaignWorkflowDraft;
use LBHurtado\XChange\Actions\Cockpit\SaveCampaignWorkflowDraft;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\PublishCampaignWorkflowDraftRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StoreCampaignWorkflowDraftRequest;

final class CockpitCampaignWorkflowDraftController extends Controller
{
    public function publish(PublishCampaignWorkflowDraftRequest $request, string $draft, PublishCampaignWorkflowDraft $publish): RedirectResponse
    {
        $publish->handle($request->user(), $draft, $request->validated('expected_snapshot_hash'));

        return to_route('x-change.cockpit.campaigns.index')->with('campaign_notice', 'Demo campaign published. No payment QR, Pay Code or SMS was generated.');
    }

    public function store(StoreCampaignWorkflowDraftRequest $request, SaveCampaignWorkflowDraft $save): RedirectResponse
    {
        $save->handle($request->user(), $request->validated());

        return to_route('x-change.cockpit.campaigns.index')->with('campaign_notice', 'Workflow draft saved. No public endpoint, payment QR or Pay Code was created.');
    }
}
