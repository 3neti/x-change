<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\ClaimReviewedFundingPayCode;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ClaimReviewedFundingPayCodeRequest;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final class CockpitReviewedFundingPayCodeClaimController extends Controller
{
    public function __invoke(
        ClaimReviewedFundingPayCodeRequest $request,
        FundingRequest $fundingRequest,
        ClaimReviewedFundingPayCode $claim,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof Model) {
            throw new RuntimeException(
                'Reviewed Funding requires a persisted Account owner.',
            );
        }

        $claim->handle($fundingRequest, $actor);

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with(
                'funding_notice',
                'Reviewed Funding Pay Code claimed. Client Funds are now available.',
            );
    }
}
