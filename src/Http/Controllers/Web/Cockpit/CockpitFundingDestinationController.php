<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\UpdateFundingDestination;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\UpdateCockpitFundingDestinationRequest;

class CockpitFundingDestinationController extends Controller
{
    public function __invoke(
        UpdateCockpitFundingDestinationRequest $request,
        string $provider,
        UpdateFundingDestination $update,
    ): RedirectResponse {
        $update->handle($request->user(), $provider, $request->validated());

        return back()->with('funding_account_notice', 'Funding destination updated.');
    }
}
