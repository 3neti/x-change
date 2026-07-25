<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\ClaimAccountFundingCode;
use LBHurtado\XChange\Models\AccountFundingCode;
use LBHurtado\XChange\Services\Funding\FundingRequestAccess;

class CockpitAccountFundingCodeClaimController extends Controller
{
    public function __invoke(
        Request $request,
        AccountFundingCode $fundingCode,
        ClaimAccountFundingCode $claim,
        FundingRequestAccess $access,
    ): RedirectResponse {
        $actor = $request->user();
        $access->authorizeOwner($fundingCode->fundingRequest, $actor);
        $claim->handle(
            $fundingCode,
            $actor::class,
            (string) $actor->getAuthIdentifier(),
        );

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('funding_notice', 'Account Funding Code claimed. Client Funds are now available.');
    }
}
