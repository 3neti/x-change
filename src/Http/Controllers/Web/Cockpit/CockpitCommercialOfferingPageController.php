<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\Commercial\CommercialPricingWorkspace;

final class CockpitCommercialOfferingPageController extends Controller
{
    public function __construct(
        private readonly CommercialPricingWorkspace $workspace,
    ) {}

    public function __invoke(Request $request): Response
    {
        $operator = $request->user();

        abort_unless($operator instanceof Model && $this->workspace->canView($operator), 404);

        $profile = $request->string('profile')->value();

        return Inertia::render('x-change/cockpit/CommercialOfferings', [
            'commercial_offering' => $this->workspace->build($operator, $profile),
        ]);
    }
}
