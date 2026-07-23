<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\RotateNetbankFundingToken;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RotateCockpitNetbankTokenRequest;

class CockpitNetbankTokenRotationController extends Controller
{
    public function __invoke(
        RotateCockpitNetbankTokenRequest $request,
        RotateNetbankFundingToken $rotate,
    ): RedirectResponse {
        $rotate->handle($request->user());

        return back()->with('funding_account_notice', 'NetBank VCA token rotated.');
    }
}
