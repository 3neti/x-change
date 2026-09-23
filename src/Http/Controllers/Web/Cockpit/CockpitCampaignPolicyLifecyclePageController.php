<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Data\Settlement\CampaignPolicyLifecycleData;
use LBHurtado\XChange\Services\Cockpit\CampaignPolicyLifecycleReadModel;

final class CockpitCampaignPolicyLifecyclePageController extends Controller
{
    public function __construct(private readonly CampaignPolicyLifecycleReadModel $lifecycles) {}

    public function __invoke(Request $request): Response
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model, 403);

        return Inertia::render('x-change/cockpit/CampaignPolicyLifecycle', [
            'lifecycles' => array_map(
                fn (CampaignPolicyLifecycleData $lifecycle): array => $lifecycle->toSafeArray(),
                $this->lifecycles->forOwner($owner),
            ),
        ]);
    }
}
