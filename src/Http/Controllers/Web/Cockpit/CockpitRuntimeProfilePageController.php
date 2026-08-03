<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Services\Cockpit\CockpitSystemReadinessAccess;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitRuntimeProfilePageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly CockpitSystemReadinessAccess $access,
        private readonly CockpitTreasuryAccessContract $treasuryAccess,
    ) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($this->access->isVisible(), 404);

        return Inertia::render('x-change/cockpit/RuntimeProfile', $this->props->toRuntimeProfileArray(
            includeCommercialAccounting: $request->user() !== null
                && $this->treasuryAccess->canViewTreasuryControls($request->user()),
        ));
    }
}
