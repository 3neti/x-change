<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StoreCampaignDisplaySessionRequest;
use LBHurtado\XChange\Models\CampaignDisplaySession;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;

final class CockpitCampaignDisplaySessionController extends Controller
{
    public function __construct(private readonly CampaignDisplaySessions $displays) {}

    public function store(StoreCampaignDisplaySessionRequest $request): JsonResponse
    {
        $owner = $request->user();
        $campaign = LeadCampaign::query()->where('reference', $request->validated('campaign_reference'))
            ->where('owner_type', $owner->getMorphClass())->where('owner_id', (string) $owner->getKey())->firstOrFail();

        return $this->response($this->displays->create($campaign));
    }

    public function show(Request $request, CampaignDisplaySession $displaySession): JsonResponse
    {
        $this->displays->authorize($displaySession, $request->user());

        return $this->response($displaySession);
    }

    public function end(Request $request, CampaignDisplaySession $displaySession): JsonResponse
    {
        $this->displays->authorize($displaySession, $request->user());
        $this->displays->end($displaySession);

        return $this->response($displaySession->fresh());
    }

    public function reset(Request $request, CampaignDisplaySession $displaySession): JsonResponse
    {
        $this->displays->authorize($displaySession, $request->user());
        $next = $this->displays->synchronized($displaySession, fn () => DB::transaction(function () use ($displaySession): CampaignDisplaySession {
            $locked = CampaignDisplaySession::query()->lockForUpdate()->findOrFail($displaySession->getKey());
            abort_if($locked->ended_at !== null, 409, 'This display was already ended.');
            $locked->update(['ended_at' => now()]);

            return $this->displays->create($locked->campaign);
        }));

        return $this->response($next);
    }

    private function response(CampaignDisplaySession $session): JsonResponse
    {
        return response()->json(['session' => $this->displays->present($session)])
            ->header('Cache-Control', 'no-store, private');
    }
}
