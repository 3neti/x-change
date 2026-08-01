<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\Cockpit\CockpitSystemReadinessAccess;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitRuntimeProfilePageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly CockpitSystemReadinessAccess $access,
    ) {}

    public function __invoke(): Response
    {
        abort_unless($this->access->isVisible(), 404);

        return Inertia::render('x-change/cockpit/RuntimeProfile', $this->props->toRuntimeProfileArray());
    }
}
