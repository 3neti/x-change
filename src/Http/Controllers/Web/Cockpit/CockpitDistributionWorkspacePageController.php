<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitDistributionWorkspacePageController extends Controller
{
    public function __construct(private readonly CockpitReadOnlyPageProps $props) {}

    public function __invoke(string $code): Response
    {
        return Inertia::render('x-change/cockpit/DistributionWorkspace', $this->props->toDistributionWorkspaceArray($code));
    }
}
